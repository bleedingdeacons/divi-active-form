<?php

/**
 * Everything a submission needs to know about its form: where the post goes and which
 * fields it has.
 *
 * Rendered into the page, signed (SchemaSigner), and read back on submit. Nothing the
 * browser sends can change it.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form;

use DiviActiveForm\Support\Attrs;

if (! defined('ABSPATH')) {
    exit;
}

final class FormSchema
{
    public const STATUSES = [ 'publish', 'pending', 'draft', 'private' ];

    /**
     * @param list<FieldDefinition> $fields
     */
    public function __construct(
        public readonly string $formId,
        public readonly string $postType,
        public readonly string $postStatus,
        public readonly string $titleTemplate,
        public readonly bool $fireLegacyHook,
        public readonly array $fields,
        public readonly string $successMessage = '',
        public readonly string $redirectUrl = '',
    ) {
    }

    /**
     * From the Register Form module's attributes plus its children's field definitions.
     *
     * @param array<string, mixed>  $attrs
     * @param list<FieldDefinition> $fields
     */
    public static function fromAttrs(array $attrs, array $fields): self
    {
        $setting = static fn (string $key): string => trim(Attrs::string(Attrs::desktop($attrs, 'form.advanced.' . $key)));

        return new self(
            formId: sanitize_text_field($setting('formId')),
            postType: sanitize_key($setting('postType')),
            postStatus: self::status($setting('postStatus')),
            titleTemplate: $setting('titleTemplate'),
            fireLegacyHook: 'on' === $setting('legacyHook'),
            fields: self::uniqueFields($fields),
            successMessage: $setting('successMessage'),
            redirectUrl: esc_url_raw($setting('redirectUrl')),
        );
    }

    public function field(string $name): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'formId'         => $this->formId,
            'postType'       => $this->postType,
            'postStatus'     => $this->postStatus,
            'titleTemplate'  => $this->titleTemplate,
            'fireLegacyHook' => $this->fireLegacyHook,
            'fields'         => array_map(static fn (FieldDefinition $f): array => $f->toArray(), $this->fields),
            'successMessage' => $this->successMessage,
            'redirectUrl'    => $this->redirectUrl,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $fields = [];
        foreach (is_array($data['fields'] ?? null) ? $data['fields'] : [] as $field) {
            if (is_array($field)) {
                $fields[] = FieldDefinition::fromArray(Attrs::map($field));
            }
        }

        return new self(
            formId: Attrs::string($data['formId'] ?? null),
            postType: sanitize_key(Attrs::string($data['postType'] ?? null)),
            postStatus: self::status(Attrs::string($data['postStatus'] ?? null)),
            titleTemplate: Attrs::string($data['titleTemplate'] ?? null),
            fireLegacyHook: true === ( $data['fireLegacyHook'] ?? false ),
            fields: self::uniqueFields($fields),
            successMessage: Attrs::string($data['successMessage'] ?? null),
            redirectUrl: Attrs::string($data['redirectUrl'] ?? null),
        );
    }

    private static function status(string $status): string
    {
        return in_array($status, self::STATUSES, true) ? $status : 'pending';
    }

    /**
     * Drops fields with no name and all but the first of any repeated name: two inputs
     * with one name would overwrite each other's meta.
     *
     * @param list<FieldDefinition> $fields
     * @return list<FieldDefinition>
     */
    private static function uniqueFields(array $fields): array
    {
        $seen   = [];
        $unique = [];
        foreach ($fields as $field) {
            if ('' === $field->name || isset($seen[ $field->name ])) {
                continue;
            }
            $seen[ $field->name ] = true;
            $unique[]             = $field;
        }

        return $unique;
    }
}
