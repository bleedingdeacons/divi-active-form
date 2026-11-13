<?php

/**
 * The options a dropdown field offers, from the editor's own list (Select) or from a JSON
 * source (JSON dropdown).
 *
 * A JSON source is a URL or a site path. A path into this site's REST API
 * (`/wp-json/...` or `?rest_route=...`) is answered in-process with rest_do_request(), so
 * it needs no loopback HTTP request and runs with the visitor's own permissions; its
 * result is not cached, because it can differ by user. Anything else is fetched over
 * HTTP with wp_safe_remote_get() and cached in a transient for five minutes
 * (filter: `divi_active_form/json_cache_ttl`).
 *
 * The same class serves the page render and the submission, so a submitted value is
 * checked against the list the visitor was offered.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form\Options;

use DiviActiveForm\Form\FieldDefinition;
use DiviActiveForm\Form\FieldType;

if (! defined('ABSPATH')) {
    exit;
}

final class FieldOptions implements OptionProvider
{
    /** @var array<string, mixed> Decoded documents, per request. */
    private array $documents = [];

    public function options(FieldDefinition $field): array
    {
        $options = match ($field->type) {
            FieldType::Select => StaticOptions::parse($field->options),
            FieldType::Json   => OptionNormaliser::normalise(
                $this->document($field->source),
                $field->itemsPath,
                $field->valueKey,
                $field->labelKey
            ),
            default           => [],
        };

        /**
         * Filters a dropdown's options, after they have been read and normalised.
         *
         * @param list<array{value: string, label: string}> $options
         * @param FieldDefinition                           $field
         */
        $filtered = apply_filters('divi_active_form/field_options', $options, $field);

        return is_array($filtered) ? self::clean($filtered) : $options;
    }

    /**
     * The decoded JSON at $source, or null when it cannot be read.
     */
    public function document(string $source): mixed
    {
        $source = trim($source);
        if ('' === $source) {
            return null;
        }
        if (! array_key_exists($source, $this->documents)) {
            $rest                       = self::restRequest($source);
            $this->documents[ $source ] = null !== $rest ? self::fromRest($rest[0], $rest[1]) : self::fromHttp($source);
        }

        return $this->documents[ $source ];
    }

    /**
     * When $source addresses this site's REST API, the route and query to send it.
     *
     * @return array{0: string, 1: array<string, string>}|null
     */
    public static function restRequest(string $source): ?array
    {
        $home      = (string) home_url('/');
        $homeHost  = strtolower((string) wp_parse_url($home, PHP_URL_HOST));
        $homePath  = rtrim((string) wp_parse_url($home, PHP_URL_PATH), '/');
        $parts     = wp_parse_url($source);
        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        if ('' !== $host && $host !== $homeHost) {
            return null;
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $params = [];
        foreach ($query as $key => $value) {
            if (is_scalar($value)) {
                $params[ (string) $key ] = (string) $value;
            }
        }

        if (isset($params['rest_route'])) {
            $route = $params['rest_route'];
            unset($params['rest_route']);

            return [ '/' . ltrim($route, '/'), $params ];
        }

        $path = $parts['path'] ?? '';
        if ('' !== $homePath && str_starts_with($path, $homePath . '/')) {
            $path = substr($path, strlen($homePath));
        }
        $prefix = '/' . trim(rest_get_url_prefix(), '/') . '/';
        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        return [ '/' . substr($path, strlen($prefix)), $params ];
    }

    /**
     * @param array<string, string> $params
     */
    private static function fromRest(string $route, array $params): mixed
    {
        $request = new \WP_REST_Request('GET', $route);
        $request->set_query_params($params);
        $response = rest_do_request($request);

        if ($response->is_error() || $response->get_status() >= 400) {
            return null;
        }

        return rest_get_server()->response_to_data($response, false);
    }

    private static function fromHttp(string $source): mixed
    {
        $url = str_starts_with($source, '/') && ! str_starts_with($source, '//') ? home_url($source) : $source;
        if (! wp_http_validate_url($url)) {
            return null;
        }

        $cacheKey = 'daf_json_' . md5($url);
        $cached   = get_transient($cacheKey);
        if (is_array($cached) && array_key_exists('data', $cached)) {
            return $cached['data'];
        }

        $response = wp_safe_remote_get(
            $url,
            [
                'timeout' => 5,
                'headers' => [ 'Accept' => 'application/json' ],
            ]
        );
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return null;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (null === $data) {
            return null;
        }

        /**
         * Seconds to cache JSON fetched over HTTP. 0 turns caching off.
         *
         * @param int    $ttl
         * @param string $url
         */
        $ttl = (int) apply_filters('divi_active_form/json_cache_ttl', 5 * MINUTE_IN_SECONDS, $url);
        if ($ttl > 0) {
            set_transient($cacheKey, [ 'data' => $data ], $ttl);
        }

        return $data;
    }

    /**
     * @param array<mixed> $options
     * @return list<array{value: string, label: string}>
     */
    private static function clean(array $options): array
    {
        $clean = [];
        foreach ($options as $option) {
            if (is_array($option) && is_scalar($option['value'] ?? null) && '' !== (string) $option['value']) {
                $clean[] = [
                    'value' => (string) $option['value'],
                    'label' => is_scalar($option['label'] ?? null) ? (string) $option['label'] : (string) $option['value'],
                ];
            }
        }

        return $clean;
    }
}
