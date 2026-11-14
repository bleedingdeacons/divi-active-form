<?php

/**
 * The post types a Divi Active Form can save submissions as, for the builder's Post Type
 * dropdown.
 *
 * Every post type with an admin screen (`show_ui`), so custom post types are listed
 * whether or not they are exposed to the REST API. Left out are attachments, and the
 * types that hold configuration rather than content: WordPress's block-editor
 * internals (`wp_*`), ACF's field groups, post types and taxonomies (`acf-*`) and
 * Divi's Library (`et_pb_*`). Any of them can still be typed, or added back with the
 * `divi_active_form/post_types` filter.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form;

if (! defined('ABSPATH')) {
    exit;
}

final class PostTypes
{
    /** Name prefixes of post types that hold configuration, not content. */
    private const CONFIGURATION_PREFIXES = [ 'wp_', 'acf-', 'et_pb_' ];

    /**
     * @return list<array{value: string, label: string}> Name and "Label (name)", by label.
     */
    public static function options(): array
    {
        $options = [];
        foreach (get_post_types([ 'show_ui' => true ], 'objects') as $name => $type) {
            $name = (string) $name;
            if ('attachment' === $name || self::isConfiguration($name)) {
                continue;
            }
            $label     = $type instanceof \WP_Post_Type && is_string($type->label) && '' !== $type->label ? $type->label : $name;
            $options[] = [
                'value' => $name,
                'label' => sprintf('%s (%s)', $label, $name),
            ];
        }
        usort($options, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        /**
         * Filters the post types the builder's Post Type dropdown offers.
         *
         * @param list<array{value: string, label: string}> $options
         */
        $filtered = apply_filters('divi_active_form/post_types', $options);
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

    private static function isConfiguration(string $name): bool
    {
        foreach (self::CONFIGURATION_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
