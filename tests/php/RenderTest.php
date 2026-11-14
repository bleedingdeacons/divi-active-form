<?php

declare(strict_types=1);

use DiviActiveForm\Form\FieldDefinition;
use DiviActiveForm\Form\FieldType;
use DiviActiveForm\Render\FieldRenderer;
use DiviActiveForm\Render\FormRenderer;

describe('FieldRenderer', function (): void {
    it('renders a JSON dropdown with its options, escaped', function (): void {
        $field = new FieldDefinition('category', 'Category', FieldType::Json, true, emptyLabel: 'Choose a category');
        $html  = FieldRenderer::render($field, [ [ 'value' => '12', 'label' => 'Tea & "Toast"' ] ], 'daf-0-category');

        expect($html)
            ->toContain('data-daf-field="category"')
            ->toContain('<label class="daf-field__label" for="daf-0-category">Category')
            ->toContain('<select class="daf-field__input daf-field__select" id="daf-0-category" name="category" aria-describedby="daf-0-category-error" required aria-required="true">')
            ->toContain('<option value="">Choose a category</option>')
            ->toContain('<option value="12">Tea &amp; &quot;Toast&quot;</option>')
            ->not->toContain('data-daf-empty');
    });

    it('uses the placeholder as the first option of a dropdown when First Option Text is blank', function (): void {
        $options = [ [ 'value' => 's', 'label' => 'Small' ] ];

        expect(FieldRenderer::render(new FieldDefinition('size', 'Size', FieldType::Select, placeholder: 'Pick a size'), $options, 's'))
            ->toContain('<option value="">Pick a size</option>')
            ->and(FieldRenderer::render(new FieldDefinition('size', 'Size', FieldType::Json, placeholder: 'Pick one'), $options, 's'))
            ->toContain('<option value="">Pick one</option>')
            ->and(FieldRenderer::render(new FieldDefinition('size', 'Size', FieldType::Select, placeholder: 'Pick', emptyLabel: 'First'), $options, 's'))
            ->toContain('<option value="">First</option>')
            ->and(FieldRenderer::render(new FieldDefinition('size', 'Size', FieldType::Select), $options, 's'))
            ->toContain('<option value="">Choose an option</option>');
    });

    it('shows the placeholder inside phone and paragraph fields', function (): void {
        expect(FieldRenderer::render(new FieldDefinition('phone', 'Phone', FieldType::Tel, placeholder: '01234 567890'), [], 'p'))
            ->toContain('placeholder="01234 567890"')
            ->and(FieldRenderer::render(new FieldDefinition('note', 'Note', FieldType::Textarea, placeholder: 'Tell us'), [], 'n'))
            ->toContain('placeholder="Tell us"');
    });

    it('flags a JSON dropdown whose source returned nothing', function (): void {
        $html = FieldRenderer::render(new FieldDefinition('category', 'Category', FieldType::Json), [], 'g');

        expect($html)->toContain('data-daf-empty=');
    });

    it('renders text inputs by type, with placeholder and width', function (): void {
        $html = FieldRenderer::render(new FieldDefinition('email', 'Email', FieldType::Email, placeholder: 'you@example.org', width: '50'), [], 'e');

        expect($html)
            ->toContain('daf-field--w50')
            ->toContain('daf-field--label-top')
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

describe('FieldRenderer label positions', function (): void {
    it('marks the field with its label position, leaving the order of the markup to CSS', function (): void {
        foreach ([ 'top', 'left', 'below' ] as $position) {
            $html = FieldRenderer::render(new FieldDefinition('name', 'Name', FieldType::Text, labelPosition: $position), [], 'n');

            expect($html)
                ->toContain('daf-field--label-' . $position)
                ->toContain('<label class="daf-field__label" for="n">Name</label>');
        }
    });

    it('keeps a hidden label for screen readers', function (): void {
        $html = FieldRenderer::render(new FieldDefinition('name', 'Name', FieldType::Text, true, 'Your name', labelPosition: 'hidden'), [], 'n');

        expect($html)
            ->toContain('daf-field--label-hidden')
            ->toContain('<label class="daf-field__label daf-screen-reader-text" for="n">Name')
            ->toContain('placeholder="Your name"');
    });

    it('hides a checkbox\'s text the same way', function (): void {
        expect(FieldRenderer::render(new FieldDefinition('agree', 'I agree', FieldType::Checkbox, labelPosition: 'hidden'), [], 'a'))
            ->toContain('<span class="daf-screen-reader-text">I agree</span>');
    });
});

describe('FieldRenderer hints', function (): void {
    it('puts the hint under the input by default and links it for screen readers', function (): void {
        $html = FieldRenderer::render(new FieldDefinition('email', 'Email', FieldType::Email, hint: 'We only use this to reply.'), [], 'e');

        expect($html)
            ->toContain('aria-describedby="e-error e-hint"')
            ->toMatch('/<input[^>]*>\s*<p class="daf-field__hint daf-field__hint--below" id="e-hint">We only use this to reply\.<\/p>/');
    });

    it('puts the hint between the label and the input when it is above', function (): void {
        $html = FieldRenderer::render(new FieldDefinition('phone', 'Phone', FieldType::Tel, hint: 'Daytime', hintPosition: 'above'), [], 'p');

        expect($html)
            ->toContain('daf-field--hint-above')
            ->toMatch('/<\/label><p class="daf-field__hint daf-field__hint--above" id="p-hint">Daytime<\/p><input/');
    });

    it('escapes the hint and keeps its line breaks', function (): void {
        expect(FieldRenderer::render(new FieldDefinition('n', 'N', FieldType::Text, hint: "<b>One</b>\nTwo"), [], 'n'))
            ->toContain('&lt;b&gt;One&lt;/b&gt;<br>' . "\n" . 'Two');
    });

    it('adds no hint markup, and no extra describedby, when there is no hint', function (): void {
        expect(FieldRenderer::render(new FieldDefinition('n', 'N', FieldType::Text), [], 'n'))
            ->not->toContain('daf-field__hint')
            ->toContain('aria-describedby="n-error"');
    });

    it('places a checkbox\'s hint above or below the box', function (): void {
        expect(FieldRenderer::render(new FieldDefinition('a', 'Agree', FieldType::Checkbox, hint: 'Required by law', hintPosition: 'above'), [], 'a'))
            ->toMatch('/daf-field__hint--above" id="a-hint">Required by law<\/p><label class="daf-field__checkbox"/');
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
