<?php

/**
 * The fields a Dropdown from an ACF field can read on one post type, for the builder's
 * ACF Field picker:
 *
 * - ACF fields in the field groups whose location rules include that post type, as
 *   "Label (name) · Group";
 * - then the post meta keys posts of that type actually use, as "name (post meta)", for
 *   fields that are not ACF (12 Step Meeting List keeps a group's details this way).
 *   Keys starting with `_` are WordPress's and plugins' private meta, and are left out.
 *
 * Only suggestions: any field name can still be typed. Filter:
 * `divi_active_form/post_type_fields`.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form;

if (! defined('ABSPATH')) {
    exit;
}

final class PostTypeFields
{
    /** At most this many meta keys are listed. */
    private const MAX_META_KEYS = 200;

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(string $postType): array
    {
        $postType = sanitize_key($postType);
        if ('' === $postType || ! post_type_exists($postType)) {
            return [];
        }

        $options = self::acfFields($postType);
        foreach (self::metaKeys($postType) as $key) {
            if (! isset($options[ $key ])) {
                $options[ $key ] = [
                    'value' => $key,
                    'label' => sprintf('%s (post meta)', $key),
                ];
            }
        }
        $options = array_values($options);

        /**
         * Filters the fields the builder's ACF Field picker offers for one post type.
         *
         * @param list<array{value: string, label: string}> $options
         * @param string                                    $postType
         */
        $filtered = apply_filters('divi_active_form/post_type_fields', $options, $postType);
        if (! is_array($filtered)) {
            return $options;
        }

        $clean = [];
        foreach ($filtered as $option) {
            if (is_array($option) && is_string($option['value'] ?? null) && '' !== $option['value']) {
                $clean[] = [
                    'value' => $option['value'],
                    'label' => is_string($option['label'] ?? null) ? $option['label'] : $option['value'],
                ];
            }
        }

        return $clean;
    }

    /**
     * ACF fields in the groups assigned to this post type, by label.
     *
     * @return array<string, array{value: string, label: string}> Keyed by field name.
     */
    private static function acfFields(string $postType): array
    {
        if (! function_exists('acf_get_field_groups') || ! function_exists('acf_get_fields')) {
            return [];
        }

        $fields = [];
        $groups = acf_get_field_groups([ 'post_type' => $postType ]);
        foreach (is_array($groups) ? $groups : [] as $group) {
            if (! is_array($group)) {
                continue;
            }
            $groupTitle = is_string($group['title'] ?? null) ? $group['title'] : '';
            $groupFields = acf_get_fields($group);
            foreach (is_array($groupFields) ? $groupFields : [] as $field) {
                $name = is_array($field) && is_string($field['name'] ?? null) ? $field['name'] : '';
                if ('' === $name || isset($fields[ $name ])) {
                    continue;
                }
                $label           = is_string($field['label'] ?? null) && '' !== $field['label'] ? $field['label'] : $name;
                $fields[ $name ] = [
                    'value' => $name,
                    'label' => '' !== $groupTitle ? sprintf('%s (%s) · %s', $label, $name, $groupTitle) : sprintf('%s (%s)', $label, $name),
                ];
            }
        }
        uasort($fields, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $fields;
    }

    /**
     * The non-private meta keys used by posts of this type, alphabetically.
     *
     * @return list<string>
     */
    private static function metaKeys(string $postType): array
    {
        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'get_col') || ! method_exists($wpdb, 'prepare')) {
            return [];
        }

        $postmeta = is_string($wpdb->postmeta ?? null) ? $wpdb->postmeta : 'wp_postmeta';
        $posts    = is_string($wpdb->posts ?? null) ? $wpdb->posts : 'wp_posts';
        $sql      = $wpdb->prepare(
            "SELECT DISTINCT pm.meta_key FROM {$postmeta} pm INNER JOIN {$posts} p ON p.ID = pm.post_id"
            . " WHERE p.post_type = %s AND pm.meta_key NOT LIKE %s ORDER BY pm.meta_key LIMIT %d",
            $postType,
            '\\_%',
            self::MAX_META_KEYS
        );
        $keys = is_string($sql) ? $wpdb->get_col($sql) : [];

        $clean = [];
        foreach (is_array($keys) ? $keys : [] as $key) {
            if (is_string($key) && '' !== $key && ! str_starts_with($key, '_')) {
                $clean[] = $key;
            }
        }

        return $clean;
    }
}
