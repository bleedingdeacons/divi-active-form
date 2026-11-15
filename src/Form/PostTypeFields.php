<?php

/**
 * Every field a Dropdown fed from a post type can read, for the builder's Source Field
 * picker, in this order:
 *
 * - the post's own fields (CORE): Title, Slug and Date, and Excerpt and Author where the
 *   post type supports them, named by their WP_Post property (`post_title`, ...);
 * - its taxonomies, as `taxonomy:<name>`, whose options are the posts' term names;
 * - ACF fields in the field groups whose location rules include that post type, as
 *   "Label (name) · Group";
 * - then every other post meta key posts of that type use, as "name (post meta)", for
 *   fields that are not ACF (12 Step Meeting List keeps a group's details this way).
 *   Keys starting with `_` are WordPress's and plugins' private bookkeeping (edit locks,
 *   ACF's field references), not fields, and are left out.
 *
 * AcfFieldOptions reads each kind.
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

    /** Taxonomy fields are named with this prefix and the taxonomy's name. */
    public const TAXONOMY_PREFIX = 'taxonomy:';

    /**
     * The post's own fields: WP_Post property => [ label, the post type feature it needs ].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const CORE = [
        'post_title'   => [ 'Title', '' ],
        'post_name'    => [ 'Slug', '' ],
        'post_date'    => [ 'Date', '' ],
        'post_excerpt' => [ 'Excerpt', 'excerpt' ],
        'post_author'  => [ 'Author', 'author' ],
    ];

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(string $postType): array
    {
        $postType = sanitize_key($postType);
        if ('' === $postType || ! post_type_exists($postType)) {
            return [];
        }

        $options = self::coreFields($postType) + self::taxonomies($postType) + self::acfFields($postType);
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
         * Filters the fields the builder's Source Field picker offers for one post type.
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
     * The post's own fields this post type has.
     *
     * @return array<string, array{value: string, label: string}>
     */
    private static function coreFields(string $postType): array
    {
        $fields = [];
        foreach (self::CORE as $name => [ $label, $feature ]) {
            if ('' === $feature || post_type_supports($postType, $feature)) {
                $fields[ $name ] = [
                    'value' => $name,
                    'label' => sprintf('%s (%s)', $label, $name),
                ];
            }
        }

        return $fields;
    }

    /**
     * The taxonomies attached to this post type that are public or have an admin screen.
     *
     * @return array<string, array{value: string, label: string}>
     */
    private static function taxonomies(string $postType): array
    {
        $fields = [];
        foreach (get_object_taxonomies($postType, 'objects') as $name => $taxonomy) {
            if (! $taxonomy->public && ! $taxonomy->show_ui) {
                continue;
            }
            $value            = self::TAXONOMY_PREFIX . $name;
            $label            = '' !== $taxonomy->label ? $taxonomy->label : (string) $name;
            $fields[ $value ] = [
                'value' => $value,
                'label' => sprintf('%s (taxonomy)', $label),
            ];
        }

        return $fields;
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
