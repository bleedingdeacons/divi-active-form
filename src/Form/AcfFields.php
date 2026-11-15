<?php

/**
 * The ACF field names the builder's ACF Field dropdown offers: every top-level field in
 * every ACF field group, as `name` with "Label (name) · Group" for a label.
 *
 * Only a suggestion list: any field name can be typed, which also covers plain post
 * meta when ACF is not active (the list is then empty). Filter:
 * `divi_active_form/acf_fields`.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form;

if (! defined('ABSPATH')) {
    exit;
}

final class AcfFields
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];

        if (function_exists('acf_get_field_groups') && function_exists('acf_get_fields')) {
            $groups = acf_get_field_groups();
            foreach (is_array($groups) ? $groups : [] as $group) {
                if (! is_array($group)) {
                    continue;
                }
                $groupTitle = is_string($group['title'] ?? null) ? $group['title'] : '';
                $fields     = acf_get_fields($group);
                foreach (is_array($fields) ? $fields : [] as $field) {
                    $name = is_array($field) && is_string($field['name'] ?? null) ? $field['name'] : '';
                    if ('' === $name || isset($options[ $name ])) {
                        continue;
                    }
                    $label            = is_string($field['label'] ?? null) && '' !== $field['label'] ? $field['label'] : $name;
                    $options[ $name ] = [
                        'value' => $name,
                        'label' => '' !== $groupTitle ? sprintf('%s (%s) · %s', $label, $name, $groupTitle) : sprintf('%s (%s)', $label, $name),
                    ];
                }
            }
        }

        $options = array_values($options);
        usort($options, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        /**
         * Filters the field names the builder's ACF Field dropdown offers.
         *
         * @param list<array{value: string, label: string}> $options
         */
        $filtered = apply_filters('divi_active_form/acf_fields', $options);
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
}
