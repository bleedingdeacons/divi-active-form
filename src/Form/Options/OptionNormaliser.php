<?php

/**
 * Turns decoded JSON into dropdown options.
 *
 * Accepted shapes, after `itemsPath` has picked the list out of the document:
 *
 *   ["Bath", "Bristol"]                                  value = label = the string
 *   [{"value": 12, "label": "Tuesday Group"}, ...]       keys from valueKey / labelKey
 *   [{"id": 12, "title": {"rendered": "Tuesday"}}, ...]  dot paths, e.g. labelKey "title.rendered"
 *   {"12": "Tuesday Group", "15": "Friday Group"}        an object map: key = value, entry = label
 *
 * When an object item lacks the configured key, the usual alternatives are tried
 * (id / ID / slug for the value, name / title / title.rendered for the label), so
 * WordPress's own REST collections work with no keys configured.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form\Options;

use DiviActiveForm\Support\Attrs;

if (! defined('ABSPATH')) {
    exit;
}

final class OptionNormaliser
{
    private const VALUE_FALLBACKS = [ 'value', 'id', 'ID', 'slug' ];
    private const LABEL_FALLBACKS = [ 'label', 'name', 'title.rendered', 'title', 'post_title' ];

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function normalise(mixed $data, string $itemsPath = '', string $valueKey = 'value', string $labelKey = 'label'): array
    {
        if ('' !== $itemsPath) {
            $data = is_array($data) ? self::path($data, $itemsPath) : null;
        }
        if (! is_array($data)) {
            return [];
        }

        $options = [];
        $seen    = [];

        foreach ($data as $key => $item) {
            $option = array_is_list($data)
                ? self::fromItem($item, $valueKey, $labelKey)
                : self::fromMapEntry((string) $key, $item, $valueKey, $labelKey);

            if (null === $option || isset($seen[ $option['value'] ])) {
                continue;
            }
            $seen[ $option['value'] ] = true;
            $options[]                = $option;
        }

        return $options;
    }

    /**
     * @return array{value: string, label: string}|null
     */
    private static function fromItem(mixed $item, string $valueKey, string $labelKey): ?array
    {
        if (is_scalar($item)) {
            return self::option((string) $item, (string) $item);
        }
        if (! is_array($item)) {
            return null;
        }

        $value = self::firstScalar($item, array_merge([ $valueKey ], self::VALUE_FALLBACKS));
        if (null === $value) {
            return null;
        }
        $label = self::firstScalar($item, array_merge([ $labelKey ], self::LABEL_FALLBACKS)) ?? $value;

        return self::option($value, $label);
    }

    /**
     * @return array{value: string, label: string}|null
     */
    private static function fromMapEntry(string $key, mixed $item, string $valueKey, string $labelKey): ?array
    {
        if (is_scalar($item)) {
            return self::option($key, (string) $item);
        }

        // An object of objects keyed by something: treat each entry as an item, with the
        // key as the value of last resort.
        $option = self::fromItem($item, $valueKey, $labelKey);
        if (null !== $option) {
            return $option;
        }

        return is_array($item) ? self::option($key, self::firstScalar($item, self::LABEL_FALLBACKS) ?? $key) : null;
    }

    /**
     * @return array{value: string, label: string}|null
     */
    private static function option(string $value, string $label): ?array
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }
        $label = trim(html_entity_decode(wp_strip_all_tags($label), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return [
            'value' => $value,
            'label' => '' !== $label ? $label : $value,
        ];
    }

    /**
     * @param array<mixed> $item
     * @param list<string> $paths
     */
    private static function firstScalar(array $item, array $paths): ?string
    {
        foreach ($paths as $path) {
            if ('' === $path) {
                continue;
            }
            $value = self::path($item, $path);
            if (is_scalar($value) && '' !== trim((string) $value)) {
                return is_bool($value) ? ( $value ? 'true' : 'false' ) : (string) $value;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $data
     */
    private static function path(array $data, string $path): mixed
    {
        // Keys that themselves contain a dot win over a nested lookup.
        if (array_key_exists($path, $data)) {
            return $data[ $path ];
        }

        return Attrs::at($data, $path);
    }
}
