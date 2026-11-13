<?php

/**
 * Front-end markup for one Form Field.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Render;

use DiviActiveForm\Form\FieldDefinition;
use DiviActiveForm\Form\FieldType;

if (! defined('ABSPATH')) {
    exit;
}

final class FieldRenderer
{
    /**
     * @param list<array{value: string, label: string}> $options Dropdowns only.
     * @param string                                    $idBase  Unique per field on the page.
     */
    public static function render(FieldDefinition $field, array $options, string $idBase): string
    {
        if ('' === $field->name) {
            return '<p class="daf-field daf-field--unconfigured">'
                . esc_html__('This field has no label or field name, so it is not shown.', 'divi-active-form')
                . '</p>';
        }

        $id      = sanitize_html_class($idBase);
        $errorId = $id . '-error';
        $classes = [
            'daf-field',
            'daf-field--' . $field->type->value,
            $field->fullwidth ? 'daf-field--full' : 'daf-field--half',
            $field->required ? 'daf-field--required' : '',
        ];

        $common = sprintf(
            'id="%1$s" name="%2$s" aria-describedby="%3$s"%4$s',
            esc_attr($id),
            esc_attr($field->name),
            esc_attr($errorId),
            $field->required ? ' required aria-required="true"' : ''
        );

        if (FieldType::Checkbox === $field->type) {
            $control = sprintf(
                '<label class="daf-field__checkbox" for="%1$s"><input type="checkbox" value="1" %2$s> <span>%3$s</span></label>',
                esc_attr($id),
                $common,
                esc_html($field->label)
            );
        } else {
            $control = self::label($field, $id) . self::control($field, $options, $common);
        }

        return sprintf(
            '<div class="%1$s" data-daf-field="%2$s">%3$s<span class="daf-field__error" id="%4$s" role="alert" hidden></span></div>',
            esc_attr(implode(' ', array_filter($classes))),
            esc_attr($field->name),
            $control,
            esc_attr($errorId)
        );
    }

    private static function label(FieldDefinition $field, string $id): string
    {
        if ('' === $field->label) {
            return '';
        }

        return sprintf(
            '<label class="daf-field__label" for="%1$s">%2$s%3$s</label>',
            esc_attr($id),
            esc_html($field->label),
            $field->required ? ' <span class="daf-field__required" aria-hidden="true">*</span>' : ''
        );
    }

    /**
     * @param list<array{value: string, label: string}> $options
     */
    private static function control(FieldDefinition $field, array $options, string $common): string
    {
        $placeholder = '' !== $field->placeholder ? sprintf(' placeholder="%s"', esc_attr($field->placeholder)) : '';

        return match ($field->type) {
            FieldType::Textarea => sprintf('<textarea class="daf-field__input" rows="5" %1$s%2$s></textarea>', $common, $placeholder),
            FieldType::Select, FieldType::Json => self::select($field, $options, $common),
            default => sprintf(
                '<input class="daf-field__input" type="%1$s" %2$s%3$s%4$s>',
                esc_attr($field->type->value),
                $common,
                $placeholder,
                FieldType::Email === $field->type ? ' autocomplete="email"' : ''
            ),
        };
    }

    /**
     * @param list<array{value: string, label: string}> $options
     */
    private static function select(FieldDefinition $field, array $options, string $common): string
    {
        $empty = '' !== $field->emptyLabel
            ? $field->emptyLabel
            : ( '' !== $field->placeholder ? $field->placeholder : __('Select…', 'divi-active-form') );

        $html = sprintf('<option value="">%s</option>', esc_html($empty));
        foreach ($options as $option) {
            $html .= sprintf('<option value="%1$s">%2$s</option>', esc_attr($option['value']), esc_html($option['label']));
        }

        $unavailable = FieldType::Json === $field->type && [] === $options
            ? sprintf(' data-daf-empty="%s"', esc_attr__('No options are available right now.', 'divi-active-form'))
            : '';

        return sprintf('<select class="daf-field__input daf-field__select" %1$s%2$s>%3$s</select>', $common, $unavailable, $html);
    }
}
