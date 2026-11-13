<?php

/**
 * Options typed into a Select field: one per line, `value|label`, or just `label` when the
 * value and the label are the same.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form\Options;

if (! defined('ABSPATH')) {
    exit;
}

final class StaticOptions
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function parse(string $text): array
    {
        $map = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            $parts = explode('|', $line, 2);
            $value = trim($parts[0]);
            $label = trim($parts[1] ?? $parts[0]);
            if ('' === $value || isset($map[ $value ])) {
                continue;
            }
            $map[ $value ] = '' !== $label ? $label : $value;
        }

        // A list rather than a map, so the order typed is kept even for numeric values.
        $options = [];
        foreach ($map as $value => $label) {
            $options[] = [
                'value' => (string) $value,
                'label' => $label,
            ];
        }

        return $options;
    }
}
