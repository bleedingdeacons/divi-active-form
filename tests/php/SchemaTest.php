<?php

declare(strict_types=1);

use DiviActiveForm\Form\FieldDefinition;
use DiviActiveForm\Form\FieldType;
use DiviActiveForm\Form\FormSchema;
use DiviActiveForm\Form\SchemaSigner;
use DiviActiveForm\Form\TitleTemplate;

function dafField(string $label, array $advanced): array
{
    $settings = [];
    foreach ($advanced as $key => $value) {
        $settings[ $key ] = [ 'desktop' => [ 'value' => $value ] ];
    }

    return [ 'fieldItem' => [ 'innerContent' => [ 'desktop' => [ 'value' => $label ] ], 'advanced' => $settings ] ];
}

describe('FieldDefinition', function (): void {
    it('reads a Form Field module\'s attributes', function (): void {
        $field = FieldDefinition::fromAttrs(dafField('Category', [
            'name'       => 'category',
            'type'       => 'json',
            'required'   => 'on',
            'source'     => '/wp-json/wp/v2/categories',
            'width'      => '75',
            'labelPosition' => 'left',
            'emptyLabel' => 'Choose',
        ]));

        expect($field->name)->toBe('category')
            ->and($field->label)->toBe('Category')
            ->and($field->type)->toBe(FieldType::Json)
            ->and($field->required)->toBeTrue()
            ->and($field->width)->toBe('75')
            ->and($field->labelPosition)->toBe('left')
            ->and($field->source)->toBe('/wp-json/wp/v2/categories')
            ->and($field->valueKey)->toBe('value')
            ->and($field->labelKey)->toBe('label')
            ->and($field->emptyLabel)->toBe('Choose');
    });

    it('derives the field name from the label when none is set', function (): void {
        expect(FieldDefinition::fromAttrs(dafField('Email Address!', []))->name)->toBe('email_address');
    });

    it('never yields a protected (underscore) meta key', function (): void {
        expect(FieldDefinition::sanitizeName('_edit_lock'))->toBe('edit_lock')
            ->and(FieldDefinition::sanitizeName('__-x'))->toBe('x');
    });

    it('falls back to full width and a top label for unknown or missing settings', function (): void {
        $field = FieldDefinition::fromAttrs(dafField('A', [ 'width' => '33', 'labelPosition' => 'right' ]));

        expect($field->width)->toBe('100')
            ->and($field->labelPosition)->toBe('top')
            ->and(FieldDefinition::fromAttrs(dafField('A', []))->width)->toBe('100');
    });

    it('reads the hint and its position, defaulting to below', function (): void {
        $attrs = dafField('Email', []) + [ 'hint' => [ 'advanced' => [
            'text'     => [ 'desktop' => [ 'value' => '  We only use this to reply.  ' ] ],
            'position' => [ 'desktop' => [ 'value' => 'above' ] ],
        ] ] ];

        expect(FieldDefinition::fromAttrs($attrs)->hint)->toBe('We only use this to reply.')
            ->and(FieldDefinition::fromAttrs($attrs)->hintPosition)->toBe('above')
            ->and(FieldDefinition::fromAttrs(dafField('Email', []))->hintPosition)->toBe('below')
            ->and(FieldDefinition::hintPosition('sideways'))->toBe('below');
    });

    it('reads a Dropdown fed from a JSON source as the JSON type, and keeps the old JSON Dropdown working', function (): void {
        expect(FieldDefinition::type('select', 'json'))->toBe(FieldType::Json)
            ->and(FieldDefinition::type('select', 'list'))->toBe(FieldType::Select)
            ->and(FieldDefinition::type('select', ''))->toBe(FieldType::Select)
            ->and(FieldDefinition::type('json', ''))->toBe(FieldType::Json)
            ->and(FieldDefinition::type('text', 'json'))->toBe(FieldType::Text)
            ->and(FieldDefinition::type('select', 'acf'))->toBe(FieldType::Acf)
            ->and(FieldDefinition::type('acf', ''))->toBe(FieldType::Acf)
            ->and(FieldDefinition::type('select', 'other'))->toBe(FieldType::Select)
            ->and(FieldDefinition::fromAttrs(dafField('Colour', [ 'type' => 'select', 'optionsFrom' => 'json', 'source' => '/x' ]))->type)
            ->toBe(FieldType::Json);
    });

    it('treats an unknown type as text', function (): void {
        expect(FieldDefinition::fromAttrs(dafField('A', [ 'type' => 'file' ]))->type)->toBe(FieldType::Text);
    });

    it('survives a round trip through an array', function (): void {
        $field = new FieldDefinition('second_category', 'Second', FieldType::Json, false, 'p', '25', '', '/src', 'items', 'id', 'name', 'None', 'below', 'A hint', 'above');

        expect(FieldDefinition::fromArray($field->toArray()))->toEqual($field);
    });
});

describe('FormSchema', function (): void {
    it('defaults to a pending post, with daf_after_insert_post off', function (): void {
        $schema = FormSchema::fromAttrs([], []);

        expect($schema->postStatus)->toBe('pending')
            ->and($schema->fireLegacyHook)->toBeFalse()
            ->and(FormSchema::fromArray([])->fireLegacyHook)->toBeFalse();
    });

    it('reads the form settings and drops unnamed and repeated fields', function (): void {
        $attrs = [ 'form' => [ 'advanced' => [
            'formId'     => [ 'desktop' => [ 'value' => 'signup' ] ],
            'postType'   => [ 'desktop' => [ 'value' => 'signup' ] ],
            'postStatus' => [ 'desktop' => [ 'value' => 'bogus' ] ],
            'legacyHook' => [ 'desktop' => [ 'value' => 'on' ] ],
        ] ] ];
        $fields = [
            new FieldDefinition('email', 'Email', FieldType::Email),
            new FieldDefinition('', 'Nameless', FieldType::Text),
            new FieldDefinition('email', 'Second email', FieldType::Email),
        ];

        $schema = FormSchema::fromAttrs($attrs, $fields);

        expect($schema->formId)->toBe('signup')
            ->and($schema->postType)->toBe('signup')
            ->and($schema->postStatus)->toBe('pending')
            ->and($schema->fireLegacyHook)->toBeTrue()
            ->and($schema->fields)->toHaveCount(1)
            ->and($schema->fields[0]->label)->toBe('Email');
    });
});

describe('SchemaSigner', function (): void {
    $schema = new FormSchema('signup', 'signup', 'publish', '', true, [
        new FieldDefinition('category', 'Category', FieldType::Json, true, source: '/wp-json/wp/v2/categories'),
    ], 'Thanks', 'https://example.test/done/');

    it('opens what it signed', function () use ($schema): void {
        $signer = new SchemaSigner('key');

        expect($signer->verify($signer->sign($schema)))->toEqual($schema);
    });

    it('refuses a token whose schema was changed', function () use ($schema): void {
        $signer = new SchemaSigner('key');
        [ , $mac ] = explode('.', $signer->sign($schema));
        $forged = rtrim(strtr(base64_encode((string) json_encode([ 'postType' => 'page' ] + $schema->toArray())), '+/', '-_'), '=');

        expect($signer->verify($forged . '.' . $mac))->toBeNull();
    });

    it('refuses a token signed with another key, and junk', function () use ($schema): void {
        expect(( new SchemaSigner('key') )->verify(( new SchemaSigner('other') )->sign($schema)))->toBeNull()
            ->and(( new SchemaSigner('key') )->verify('not-a-token'))->toBeNull()
            ->and(( new SchemaSigner('key') )->verify(''))->toBeNull();
    });
});

describe('TitleTemplate', function (): void {
    it('replaces {field} placeholders and squeezes the gaps left by empty ones', function (): void {
        expect(TitleTemplate::render('Submission from {category} {second_category} ({EMAIL})', [ 'category' => 'News', 'email' => 'a@b.c' ], 'fallback'))
            ->toBe('Submission from News (a@b.c)');
    });

    it('falls back when the result is empty', function (): void {
        expect(TitleTemplate::render('{missing}', [], 'fallback'))->toBe('fallback')
            ->and(TitleTemplate::render('', [], 'fallback'))->toBe('fallback');
    });
});
