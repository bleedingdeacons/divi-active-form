<?php

/**
 * One field of a Register Form, as configured on its Form Field module.
 *
 * Built from the child module's Divi attributes when the form renders, then carried
 * (signed) in the form's schema so the submission is validated against exactly what the
 * editor configured, not against what the browser sends.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form;

use DiviActiveForm\Support\Attrs;

if (! defined('ABSPATH')) {
    exit;
}

final class FieldDefinition
{
    /**
     * @param string $name        Meta / ACF field name the value is saved under; also the input name.
     * @param string $options     Select only: one option per line, `value|label` or just `label`.
     * @param string $source      JSON dropdown only: URL or site path of the JSON.
     * @param string $itemsPath   JSON dropdown only: dot path to the list inside the JSON, blank for the top level.
     * @param string $valueKey    JSON dropdown only: dot path of each item's value.
     * @param string $labelKey    JSON dropdown only: dot path of each item's label.
     * @param string $emptyLabel  Dropdowns: text of the leading "nothing chosen" option.
     * @param Conditions $visibleWhen When the field is shown; hidden fields are not sent or saved.
     * @param Conditions $enabledWhen When the field can be filled in; disabled fields are not sent or saved.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly FieldType $type,
        public readonly bool $required = false,
        public readonly string $placeholder = '',
        public readonly bool $fullwidth = true,
        public readonly string $options = '',
        public readonly string $source = '',
        public readonly string $itemsPath = '',
        public readonly string $valueKey = 'value',
        public readonly string $labelKey = 'label',
        public readonly string $emptyLabel = '',
        public readonly Conditions $visibleWhen = new Conditions(),
        public readonly Conditions $enabledWhen = new Conditions(),
    ) {
    }

    /**
     * From a Form Field module's attributes (already merged with module.json defaults).
     *
     * @param array<string, mixed> $attrs
     */
    public static function fromAttrs(array $attrs): self
    {
        $setting = static fn (string $key): string => trim(Attrs::string(Attrs::desktop($attrs, 'fieldItem.advanced.' . $key)));

        $label = trim(wp_strip_all_tags(Attrs::string(Attrs::desktop($attrs, 'fieldItem.innerContent'))));
        $name  = self::sanitizeName('' !== $setting('name') ? $setting('name') : $label);

        return new self(
            name: $name,
            label: $label,
            type: FieldType::parse($setting('type')),
            required: 'on' === $setting('required'),
            placeholder: $setting('placeholder'),
            fullwidth: 'off' !== $setting('fullwidth'),
            options: Attrs::string(Attrs::desktop($attrs, 'fieldItem.advanced.options')),
            source: $setting('source'),
            itemsPath: $setting('itemsPath'),
            valueKey: '' !== $setting('valueKey') ? $setting('valueKey') : 'value',
            labelKey: '' !== $setting('labelKey') ? $setting('labelKey') : 'label',
            emptyLabel: $setting('emptyLabel'),
            visibleWhen: Conditions::parse(Attrs::string(Attrs::desktop($attrs, 'fieldItem.advanced.visibleRules')), $setting('visibleMatch')),
            enabledWhen: Conditions::parse(Attrs::string(Attrs::desktop($attrs, 'fieldItem.advanced.enabledRules')), $setting('enabledMatch')),
        );
    }

    /**
     * Whether the field takes part in a submission, given the other fields' values: it
     * must be both visible and enabled.
     *
     * @param array<string, string> $values
     */
    public function isActive(array $values): bool
    {
        return $this->visibleWhen->passes($values) && $this->enabledWhen->passes($values);
    }

    /**
     * A meta key: lower case, `a-z 0-9 _ -`. A leading underscore is dropped, because
     * underscore keys are WordPress's protected meta and must never be writable from a
     * public form.
     */
    public static function sanitizeName(string $raw): string
    {
        $key = strtolower(trim($raw));
        $key = (string) preg_replace('/[\s]+/', '_', $key);
        $key = (string) preg_replace('/[^a-z0-9_\-]/', '', $key);

        return ltrim($key, '_-');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'label'       => $this->label,
            'type'        => $this->type->value,
            'required'    => $this->required,
            'placeholder' => $this->placeholder,
            'fullwidth'   => $this->fullwidth,
            'options'     => $this->options,
            'source'      => $this->source,
            'itemsPath'   => $this->itemsPath,
            'valueKey'    => $this->valueKey,
            'labelKey'    => $this->labelKey,
            'emptyLabel'  => $this->emptyLabel,
            'visibleWhen' => $this->visibleWhen->toArray(),
            'enabledWhen' => $this->enabledWhen->toArray(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $string = static fn (string $key, string $default = ''): string => Attrs::string($data[$key] ?? null, $default);

        return new self(
            name: self::sanitizeName($string('name')),
            label: $string('label'),
            type: FieldType::parse($data['type'] ?? null),
            required: true === ( $data['required'] ?? false ),
            placeholder: $string('placeholder'),
            fullwidth: false !== ( $data['fullwidth'] ?? true ),
            options: $string('options'),
            source: $string('source'),
            itemsPath: $string('itemsPath'),
            valueKey: $string('valueKey', 'value'),
            labelKey: $string('labelKey', 'label'),
            emptyLabel: $string('emptyLabel'),
            visibleWhen: Conditions::fromArray($data['visibleWhen'] ?? null),
            enabledWhen: Conditions::fromArray($data['enabledWhen'] ?? null),
        );
    }
}
