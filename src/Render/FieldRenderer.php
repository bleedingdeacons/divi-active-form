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
        $hintId  = $id . '-hint';
        $classes = [
            'daf-field',
            'daf-field--' . $field->type->value,
            'daf-field--w' . $field->width,
            'daf-field--label-' . $field->labelPosition,
            '' !== $field->hint ? 'daf-field--hint-' . $field->hintPosition : '',
            $field->required ? 'daf-field--required' : '',
        ];

        $common = sprintf(
            'id="%1$s" name="%2$s" aria-describedby="%3$s"%4$s',
            esc_attr($id),
            esc_attr($field->name),
            esc_attr('' !== $field->hint ? $errorId . ' ' . $hintId : $errorId),
            $field->required ? ' required aria-required="true"' : ''
        );

        $hint  = self::hint($field, $hintId);
        $above = 'above' === $field->hintPosition ? $hint : '';
        $below = 'above' === $field->hintPosition ? '' : $hint;

        if (FieldType::Checkbox === $field->type) {
            $control = $above . sprintf(
                '<label class="daf-field__checkbox" for="%1$s"><input type="checkbox" value="1" %2$s> <span%4$s>%3$s</span></label>',
                esc_attr($id),
                $common,
                esc_html($field->label),
                'hidden' === $field->labelPosition ? ' class="daf-screen-reader-text"' : ''
            ) . $below;
        } else {
            $control = self::label($field, $id) . $above . self::control($field, $options, $common) . $below;
        }

        return sprintf(
            '<div class="%1$s" data-daf-field="%2$s"%5$s>%3$s<span class="daf-field__error" id="%4$s" role="alert" hidden></span></div>',
            esc_attr(implode(' ', array_filter($classes))),
            esc_attr($field->name),
            $control,
            esc_attr($errorId),
            self::conditionAttrs($field)
        );
    }

    /**
     * The field's Conditional Logic, already parsed, for form-core.js to apply. Nothing
     * is hidden or disabled here: without JavaScript every field shows, and the server
     * ignores those whose rules fail.
     */
    private static function conditionAttrs(FieldDefinition $field): string
    {
        $attrs = '';
        foreach ([ 'visible' => $field->visibleWhen, 'enabled' => $field->enabledWhen ] as $kind => $conditions) {
            if (! $conditions->isEmpty()) {
                $attrs .= sprintf(' data-daf-%s="%s"', $kind, esc_attr((string) wp_json_encode($conditions->toArray())));
            }
        }

        return $attrs;
    }

    /**
     * The hint paragraph: grey by default (form.css), or the Hint Colour, which Divi
     * prints for this field's order class.
     */
    private static function hint(FieldDefinition $field, string $hintId): string
    {
        if ('' === $field->hint) {
            return '';
        }

        return sprintf(
            '<p class="daf-field__hint daf-field__hint--%1$s" id="%2$s">%3$s</p>',
            esc_attr($field->hintPosition),
            esc_attr($hintId),
            nl2br(esc_html($field->hint), false)
        );
    }

    private static function label(FieldDefinition $field, string $id): string
    {
        if ('' === $field->label) {
            return '';
        }

        // A hidden label stays in the markup for screen readers; CSS moves the others.
        return sprintf(
            '<label class="daf-field__label%4$s" for="%1$s">%2$s%3$s</label>',
            esc_attr($id),
            esc_html($field->label),
            $field->required ? ' <span class="daf-field__required" aria-hidden="true">*</span>' : '',
            'hidden' === $field->labelPosition ? ' daf-screen-reader-text' : ''
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
            FieldType::Select, FieldType::Json, FieldType::Acf => self::select($field, $options, $common),
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
        // The leading "nothing chosen" option: First Option Text, else the Placeholder, as
        // a text field would show it, else a generic prompt.
        $empty = '' !== $field->emptyLabel
            ? $field->emptyLabel
            : ( '' !== $field->placeholder ? $field->placeholder : __('Choose an option', 'divi-active-form') );

        $html = sprintf('<option value="">%s</option>', esc_html($empty));
        foreach ($options as $option) {
            $html .= sprintf('<option value="%1$s">%2$s</option>', esc_attr($option['value']), esc_html($option['label']));
        }

        $unavailable = ( FieldType::Json === $field->type || FieldType::Acf === $field->type ) && [] === $options
            ? sprintf(' data-daf-empty="%s"', esc_attr__('No options are available right now.', 'divi-active-form'))
            : '';

        return sprintf('<select class="daf-field__input daf-field__select" %1$s%2$s>%3$s</select>', $common, $unavailable, $html);
    }
}
