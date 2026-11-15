<?php

/**
 * An ACF Dropdown's options: the values of one field across every published post of one
 * post type.
 *
 * Each distinct value is one option, used as both value and label, in natural order.
 * Posts where the field is empty are skipped. The field is read with ACF's get_field()
 * when ACF is active, so formatted values (a Select's chosen label, a Post Object's
 * title, a Taxonomy's term names) come out readable; without ACF it is plain post meta.
 *
 * Built on the server when the page renders and again on submit, so a submitted value
 * must be one the field currently holds on some post.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form\Options;

if (! defined('ABSPATH')) {
    exit;
}

final class AcfFieldOptions
{
    /** More posts than this are not read; the dropdown would be unusable long before. */
    private const MAX_POSTS = 2000;

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(string $postType, string $fieldName): array
    {
        $postType  = sanitize_key($postType);
        $fieldName = trim($fieldName);
        if ('' === $postType || '' === $fieldName || ! post_type_exists($postType)) {
            return [];
        }

        $posts = get_posts(
            [
                'post_type'        => $postType,
                'post_status'      => 'publish',
                'posts_per_page'   => self::MAX_POSTS,
                'fields'           => 'ids',
                'no_found_rows'    => true,
                'suppress_filters' => false,
            ]
        );

        $values = [];
        foreach ($posts as $post) {
            $postId = $post instanceof \WP_Post ? $post->ID : ( is_numeric($post) ? (int) $post : 0 );
            if ($postId <= 0) {
                continue;
            }
            $raw  = function_exists('get_field') ? get_field($fieldName, $postId) : get_post_meta($postId, $fieldName, true);
            $text = self::text($raw);
            if ('' !== $text) {
                $values[ $text ] = true;
            }
        }

        $texts = array_map('strval', array_keys($values));
        natcasesort($texts);

        $options = [];
        foreach ($texts as $text) {
            $options[] = [
                'value' => $text,
                'label' => $text,
            ];
        }

        return $options;
    }

    /**
     * A field value as one line of text: scalars as they are; a Select's
     * { value, label } as its label; a post as its title; a term as its name; a list
     * of any of these joined with commas. Anything else is empty.
     */
    public static function text(mixed $raw): string
    {
        if (is_bool($raw)) {
            return $raw ? '1' : '';
        }
        if (is_scalar($raw)) {
            return trim(wp_strip_all_tags((string) $raw));
        }
        if ($raw instanceof \WP_Post) {
            return trim($raw->post_title);
        }
        if (is_object($raw) && isset($raw->name) && is_scalar($raw->name)) {
            return trim((string) $raw->name);
        }
        if (is_array($raw)) {
            if (array_key_exists('label', $raw) && is_scalar($raw['label'])) {
                return trim((string) $raw['label']);
            }
            $parts = array_filter(array_map([ self::class, 'text' ], array_values($raw)), static fn (string $s): bool => '' !== $s);

            return implode(', ', $parts);
        }

        return '';
    }
}
