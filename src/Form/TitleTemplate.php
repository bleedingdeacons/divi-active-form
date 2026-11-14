<?php

/**
 * The created post's title: the form's Post Title template with `{field_name}`
 * placeholders replaced by the submitted values.
 *
 * Dropdown placeholders show the option's label, not its value, so `{category}` gives
 * the category's name rather than its ID.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form;

if (! defined('ABSPATH')) {
    exit;
}

final class TitleTemplate
{
    /**
     * @param array<string, string> $replacements Field name => text to show.
     */
    public static function render(string $template, array $replacements, string $fallback): string
    {
        $title = (string) preg_replace_callback(
            '/\{([a-z0-9_\-]+)\}/i',
            static fn (array $m): string => $replacements[ strtolower($m[1]) ] ?? '',
            $template
        );
        $title = trim((string) preg_replace('/\s+/', ' ', $title));

        return '' !== $title ? $title : $fallback;
    }
}
