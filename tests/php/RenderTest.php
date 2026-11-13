<?php

declare(strict_types=1);

use DiviActiveForm\Form\FieldDefinition;
use DiviActiveForm\Form\FieldType;
use DiviActiveForm\Render\FieldRenderer;
use DiviActiveForm\Render\FormRenderer;

describe('FieldRenderer', function (): void {
    it('renders a JSON dropdown with its options, escaped', function (): void {
        $field = new FieldDefinition('group', 'Your group', FieldType::Json, true, emptyLabel: 'Choose your group');
        $html  = FieldRenderer::render($field, [ [ 'value' => '12', 'label' => 'Tea & "Toast"' ] ], 'daf-0-group');

        expect($html)
            ->toContain('data-daf-field="group"')
            ->toContain('<label class="daf-field__label" for="daf-0-group">Your group')
            ->toContain('<select class="daf-field__input daf-field__select" id="daf-0-group" name="group" aria-describedby="daf-0-group-error" required aria-required="true">')
            ->toContain('<option value="">Choose your group</option>')
            ->toContain('<option value="12">Tea &amp; &quot;Toast&quot;</option>')
            ->not->toContain('data-daf-empty');
    });

    it('flags a JSON dropdown whose source returned nothing', function (): void {
        $html = FieldRenderer::render(new FieldDefinition('group', 'Group', FieldType::Json), [], 'g');

        expect($html)->toContain('data-daf-empty=');
    });

    it('renders text inputs by type, with placeholder and width', function (): void {
        $html = FieldRenderer::render(new FieldDefinition('email', 'Email', FieldType::Email, placeholder: 'you@example.org', fullwidth: false), [], 'e');

        expect($html)
            ->toContain('daf-field--half')
            ->toContain('type="email"')
            ->toContain('placeholder="you@example.org"')
            ->toContain('autocomplete="email"')
            ->not->toContain('required');
    });

    it('renders a checkbox with its label beside it', function (): void {
        expect(FieldRenderer::render(new FieldDefinition('agree', 'I agree', FieldType::Checkbox, true), [], 'a'))
            ->toContain('<input type="checkbox" value="1" id="a" name="agree"')
            ->toContain('<span>I agree</span>');
    });

    it('says so rather than rendering an input with no name', function (): void {
        expect(FieldRenderer::render(new FieldDefinition('', '', FieldType::Text), [], 'x'))
            ->toContain('daf-field--unconfigured')
            ->not->toContain('<input');
    });
});

describe('FormRenderer', function (): void {
    it('renders a form that works without JavaScript and carries the signed schema', function (): void {
        $html = FormRenderer::render([
            'token'         => 'payload.mac',
            'fields'        => '<div>FIELDS</div>',
            'title'         => 'Register',
            'submitLabel'   => '',
            'restUrl'       => 'https://example.test/wp-json/divi-active-form/v1/submit',
            'actionUrl'     => 'https://example.test/wp-admin/admin-post.php',
            'returnUrl'     => 'https://example.test/register/',
            'notice'        => '',
            'noticeIsError' => false,
        ]);

        expect($html)
            ->toContain('<h3 class="daf-form__title">Register</h3>')
            ->toContain('method="post" action="https://example.test/wp-admin/admin-post.php?action=divi_active_form_submit"')
            ->toContain('data-rest="https://example.test/wp-json/divi-active-form/v1/submit"')
            ->toContain('<div>FIELDS</div>')
            ->not->toContain('name="action"')
            ->toContain('name="_daf_schema" value="payload.mac"')
            ->toContain('name="_daf_website"')
            ->toContain('>Submit</button>')
            ->toContain('role="status" aria-live="polite" hidden');
    });

    it('shows the outcome of a no-JavaScript submission', function (): void {
        $html = FormRenderer::render([
            'token' => 't', 'fields' => '', 'title' => '', 'submitLabel' => 'Go', 'restUrl' => '', 'actionUrl' => '',
            'returnUrl' => '', 'notice' => 'Email is required.', 'noticeIsError' => true,
        ]);

        expect($html)
            ->toContain('daf-form__message--error" role="status" aria-live="polite">Email is required.</div>')
            ->not->toContain('daf-form__title');
    });
});
