<?php

/**
 * A sample JSON source for dropdowns, served at
 * GET /wp-json/divi-active-form/v1/example-options.
 *
 * It is the default JSON Source for a Dropdown, so one switched to Options From: JSON
 * Source shows working options on any site, and its two shapes show the JSON settings
 * at work:
 *
 *   (default)      [{"value": "red", "label": "Red"}, ...]
 *                  read with no keys set
 *   ?shape=nested  {"data": {"items": [{"code": "red", "name": "Red"}, ...]}}
 *                  Items Path data.items, Value Key code, Label Key name
 *
 * Filter `divi_active_form/example_options` changes the list (value => label).
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form\Options;

if (! defined('ABSPATH')) {
    exit;
}

final class ExampleOptions
{
    /**
     * @return array<string, string> Value => label.
     */
    public static function items(): array
    {
        $items = [
            'red'    => __('Red', 'divi-active-form'),
            'orange' => __('Orange', 'divi-active-form'),
            'yellow' => __('Yellow', 'divi-active-form'),
            'green'  => __('Green', 'divi-active-form'),
            'blue'   => __('Blue', 'divi-active-form'),
            'purple' => __('Purple', 'divi-active-form'),
        ];

        /**
         * Filters the example options endpoint's list.
         *
         * @param array<string, string> $items Value => label.
         */
        $filtered = apply_filters('divi_active_form/example_options', $items);
        if (! is_array($filtered)) {
            return $items;
        }

        $clean = [];
        foreach ($filtered as $value => $label) {
            if (is_scalar($label) && '' !== trim((string) $value)) {
                $clean[ (string) $value ] = (string) $label;
            }
        }

        return $clean;
    }

    /**
     * The list in the requested shape: `nested`, or the default flat list.
     *
     * @return array<mixed>
     */
    public static function shaped(string $shape): array
    {
        $items = self::items();

        if ('nested' === $shape) {
            $nested = [];
            foreach ($items as $value => $label) {
                $nested[] = [
                    'code' => $value,
                    'name' => $label,
                ];
            }

            return [ 'data' => [ 'items' => $nested ] ];
        }

        $flat = [];
        foreach ($items as $value => $label) {
            $flat[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $flat;
    }
}
