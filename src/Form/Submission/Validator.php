<?php

/**
 * Checks a submission against its form's schema, field by field.
 *
 * Only fields in the schema are read; anything else in the request is ignored. A
 * dropdown's value must be one of the options the field offers now.
 *
 * A field whose Conditional Logic leaves it hidden or disabled, judged on the submitted
 * values, takes no part: it is not required, not checked and not returned, whatever
 * the browser sent for it.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form\Submission;

use DiviActiveForm\Form\FieldDefinition;
use DiviActiveForm\Form\FieldType;
use DiviActiveForm\Form\FormSchema;
use DiviActiveForm\Form\Options\OptionProvider;

if (! defined('ABSPATH')) {
    exit;
}

final class Validator
{
    private const MAX_LENGTH = [
        'textarea' => 10000,
        'default'  => 1000,
    ];

    public function __construct(private readonly OptionProvider $options)
    {
    }

    /**
     * @param array<string, mixed> $input Unslashed request parameters.
     * @return array{values: array<string, string>, errors: array<string, string>}
     */
    public function validate(FormSchema $schema, array $input): array
    {
        $cleaned = [];
        foreach ($schema->fields as $field) {
            $raw                     = $input[ $field->name ] ?? '';
            $cleaned[ $field->name ] = is_scalar($raw) ? $this->clean($field, (string) $raw) : '';
        }
        $active = self::activeFields($schema, $cleaned);

        $values = [];
        $errors = [];

        foreach ($schema->fields as $field) {
            if (! isset($active[ $field->name ])) {
                continue;
            }
            $value = $cleaned[ $field->name ];
            $error = $this->check($field, $value);

            if (null !== $error) {
                $errors[ $field->name ] = $error;
                continue;
            }
            $values[ $field->name ] = $value;
        }

        return [
            'values' => $values,
            'errors' => $errors,
        ];
    }

    /**
     * The fields that are visible and enabled. A field's rules see an inactive field as
     * empty, so a chain (C shows when B is filled, B shows when A is "yes") settles by
     * repeating until nothing changes. A chain settles within one pass per field; the
     * pass limit only stops rules that depend on each other in a circle from looping.
     * The browser (form-core.js) does the same.
     *
     * @param array<string, string> $cleaned
     * @return array<string, true>
     */
    public static function activeFields(FormSchema $schema, array $cleaned): array
    {
        $active = array_fill_keys(array_keys($cleaned), true);

        for ($pass = 0; $pass <= count($schema->fields); $pass++) {
            $seen = [];
            foreach ($cleaned as $name => $value) {
                $seen[ $name ] = isset($active[ $name ]) ? $value : '';
            }

            $next = [];
            foreach ($schema->fields as $field) {
                if ($field->isActive($seen)) {
                    $next[ $field->name ] = true;
                }
            }
            if ($next === $active) {
                break;
            }
            $active = $next;
        }

        return $active;
    }

    private function clean(FieldDefinition $field, string $raw): string
    {
        return match ($field->type) {
            FieldType::Email    => sanitize_email($raw),
            FieldType::Textarea => sanitize_textarea_field($raw),
            FieldType::Checkbox => '' !== trim($raw) && '0' !== trim($raw) ? '1' : '',
            FieldType::Select, FieldType::Json => trim($raw),
            default             => sanitize_text_field($raw),
        };
    }

    private function check(FieldDefinition $field, string $value): ?string
    {
        $label = '' !== $field->label ? $field->label : $field->name;

        if ('' === $value) {
            return $field->required
                /* translators: %s: field label */
                ? sprintf(__('%s is required.', 'divi-active-form'), $label)
                : null;
        }

        $max = self::MAX_LENGTH[ FieldType::Textarea === $field->type ? 'textarea' : 'default' ];
        if (mb_strlen($value) > $max) {
            /* translators: 1: field label, 2: maximum number of characters */
            return sprintf(__('%1$s must be at most %2$d characters.', 'divi-active-form'), $label, $max);
        }

        if (FieldType::Email === $field->type && ! is_email($value)) {
            /* translators: %s: field label */
            return sprintf(__('%s must be a valid email address.', 'divi-active-form'), $label);
        }

        if ($field->type->hasChoices() && ! $this->isOffered($field, $value)) {
            /* translators: %s: field label */
            return sprintf(__('Choose one of the options for %s.', 'divi-active-form'), $label);
        }

        return null;
    }

    private function isOffered(FieldDefinition $field, string $value): bool
    {
        foreach ($this->options->options($field) as $option) {
            if ($option['value'] === $value) {
                return true;
            }
        }

        return false;
    }
}
