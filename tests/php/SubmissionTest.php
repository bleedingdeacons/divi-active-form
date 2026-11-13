<?php

declare(strict_types=1);

use BleedingDeacons\WpMocks\WpState;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use DiviActiveForm\Form\FieldDefinition;
use DiviActiveForm\Form\FieldType;
use DiviActiveForm\Form\FormSchema;
use DiviActiveForm\Form\Options\OptionProvider;
use DiviActiveForm\Form\SchemaSigner;
use DiviActiveForm\Form\Submission\PostWriter;
use DiviActiveForm\Form\Submission\SubmissionHandler;
use DiviActiveForm\Form\Submission\Validator;

/** Groups 12 and 15, as the groups endpoint would list them. */
final class FakeGroupOptions implements OptionProvider
{
    public function options(FieldDefinition $field): array
    {
        return match ($field->type) {
            FieldType::Json   => [
                [ 'value' => '12', 'label' => 'Bath Tuesday' ],
                [ 'value' => '15', 'label' => 'Bristol Friday' ],
            ],
            FieldType::Select => [ [ 'value' => 'yes', 'label' => 'Yes' ] ],
            default           => [],
        };
    }
}

function dafSchema(array $overrides = []): FormSchema
{
    $groups = '/wp-json/divi-active-form/v1/groups';

    return new FormSchema(
        formId: $overrides['formId'] ?? 'signup',
        postType: $overrides['postType'] ?? 'signup',
        postStatus: 'publish',
        titleTemplate: $overrides['titleTemplate'] ?? '',
        fireLegacyHook: $overrides['fireLegacyHook'] ?? true,
        fields: [
            new FieldDefinition('group', 'Your group', FieldType::Json, true, source: $groups),
            new FieldDefinition('second_group', 'Second group', FieldType::Json, false, source: $groups),
            new FieldDefinition('email', 'Email', FieldType::Email, true),
        ],
        successMessage: $overrides['successMessage'] ?? '',
        redirectUrl: $overrides['redirectUrl'] ?? '',
    );
}

function dafHandler(): SubmissionHandler
{
    $options = new FakeGroupOptions();

    return new SubmissionHandler(new SchemaSigner('test-key'), new Validator($options), new PostWriter($options));
}

function dafInput(FormSchema $schema, array $values): array
{
    return [ SubmissionHandler::SCHEMA_FIELD => ( new SchemaSigner('test-key') )->sign($schema) ] + $values;
}

describe('Validator', function (): void {
    $validate = static fn (array $input): array => ( new Validator(new FakeGroupOptions()) )->validate(dafSchema(), $input);

    it('accepts a value the dropdown offers, and an empty optional one', function () use ($validate): void {
        expect($validate([ 'group' => '12', 'second_group' => '', 'email' => 'sec@example.org' ]))->toBe([
            'values' => [ 'group' => '12', 'second_group' => '', 'email' => 'sec@example.org' ],
            'errors' => [],
        ]);
    });

    it('rejects a dropdown value the source does not offer', function () use ($validate): void {
        expect($validate([ 'group' => '999', 'email' => 'sec@example.org' ])['errors'])
            ->toBe([ 'group' => 'Choose one of the options for Your group.' ]);
    });

    it('requires required fields and checks emails', function () use ($validate): void {
        expect($validate([ 'email' => 'not-an-email' ])['errors'])->toBe([
            'group' => 'Your group is required.',
            'email' => 'Email is required.',
        ]);
    });

    it('ignores anything in the request that is not a field', function () use ($validate): void {
        $result = $validate([ 'group' => '12', 'email' => 'a@b.co', 'post_status' => 'publish', '_edit_lock' => 'x' ]);

        expect(array_keys($result['values']))->toBe([ 'group', 'second_group', 'email' ]);
    });

    it('does not accept arrays in place of values', function () use ($validate): void {
        expect($validate([ 'group' => [ '12' ], 'email' => 'a@b.co' ])['errors'])->toHaveKey('group');
    });

    it('sanitises text and normalises checkboxes', function (): void {
        $schema = new FormSchema('f', 'post', 'publish', '', false, [
            new FieldDefinition('name', 'Name', FieldType::Text),
            new FieldDefinition('agree', 'Agree', FieldType::Checkbox, true),
        ]);

        $result = ( new Validator(new FakeGroupOptions()) )->validate($schema, [ 'name' => " <b>Ann</b>\n", 'agree' => 'on' ]);

        expect($result['values'])->toBe([ 'name' => 'Ann', 'agree' => '1' ]);
    });
});

describe('SubmissionHandler', function (): void {
    it('creates the post, saves each field and fires both after-insert hooks', function (): void {
        Actions\expectDone('divi_active_form/after_insert_post')
            ->once()
            ->with('signup', 100, [ 'group' => '12', 'second_group' => '15', 'email' => 'sec@example.org' ]);
        Actions\expectDone('df_after_insert_post')->once()->with('signup', 100);

        $result = dafHandler()->handle(
            dafInput(dafSchema(), [ 'group' => '12', 'second_group' => '15', 'email' => 'sec@example.org' ]),
            '203.0.113.9'
        );

        expect($result->ok)->toBeTrue()
            ->and($result->status)->toBe(201)
            ->and($result->postId)->toBe(100)
            ->and($result->message)->toBe('Thank you. Your submission has been received.')
            ->and(WpState::$insertedPosts[0])->toMatchArray([ 'post_type' => 'signup', 'post_status' => 'publish' ])
            ->and(get_post_meta(100, 'group', true))->toBe('12')
            ->and(get_post_meta(100, 'second_group', true))->toBe('15')
            ->and(get_post_meta(100, 'email', true))->toBe('sec@example.org');
    });

    it('titles the post from the template, with dropdown labels rather than values', function (): void {
        dafHandler()->handle(
            dafInput(dafSchema([ 'titleTemplate' => 'Submission from {group}' ]), [ 'group' => '15', 'email' => 'a@b.co' ]),
            ''
        );

        expect(WpState::$insertedPosts[0]['post_title'])->toBe('Submission from Bristol Friday');
    });

    it('skips the Divi Form Builder hook when the form turns it off', function (): void {
        Actions\expectDone('df_after_insert_post')->never();

        dafHandler()->handle(dafInput(dafSchema([ 'fireLegacyHook' => false ]), [ 'group' => '12', 'email' => 'a@b.co' ]), '');
    });

    it('returns the form\'s own message and redirect', function (): void {
        $result = dafHandler()->handle(
            dafInput(dafSchema([ 'successMessage' => 'Check your inbox.', 'redirectUrl' => 'https://example.test/thanks/' ]), [ 'group' => '12', 'email' => 'a@b.co' ]),
            ''
        );

        expect($result->toArray())->toMatchArray([ 'ok' => true, 'message' => 'Check your inbox.', 'redirect' => 'https://example.test/thanks/' ]);
    });

    it('saves nothing and reports each field when validation fails', function (): void {
        $result = dafHandler()->handle(dafInput(dafSchema(), [ 'group' => '999' ]), '');

        expect($result->status)->toBe(422)
            ->and(array_keys($result->errors))->toBe([ 'group', 'email' ])
            ->and(WpState::$insertedPosts)->toBe([]);
    });

    it('refuses a missing or forged schema', function (): void {
        $forged = ( new SchemaSigner('another-site') )->sign(dafSchema([ 'postType' => 'page' ]));

        expect(dafHandler()->handle([ 'group' => '12' ], '')->status)->toBe(400)
            ->and(dafHandler()->handle([ SubmissionHandler::SCHEMA_FIELD => $forged ], '')->status)->toBe(400)
            ->and(WpState::$insertedPosts)->toBe([]);
    });

    it('refuses a form whose post type does not exist', function (): void {
        expect(dafHandler()->handle(dafInput(dafSchema([ 'postType' => 'nope' ]), [ 'group' => '12', 'email' => 'a@b.co' ]), '')->status)
            ->toBe(500);
    });

    it('pretends to accept a bot that fills in the honeypot, and saves nothing', function (): void {
        $result = dafHandler()->handle(
            dafInput(dafSchema(), [ 'group' => '12', 'email' => 'a@b.co', SubmissionHandler::HONEYPOT_FIELD => 'http://spam' ]),
            ''
        );

        expect($result->ok)->toBeTrue()
            ->and($result->postId)->toBe(0)
            ->and(WpState::$insertedPosts)->toBe([]);
    });

    it('rate-limits one sender', function (): void {
        Filters\expectApplied('divi_active_form/rate_limit')->andReturn(2);
        $input = dafInput(dafSchema(), [ 'group' => '12', 'email' => 'a@b.co' ]);

        $statuses = array_map(static fn (): int => dafHandler()->handle($input, '198.51.100.7')->status, range(1, 3));

        expect($statuses)->toBe([ 201, 201, 429 ])
            ->and(dafHandler()->handle($input, '198.51.100.8')->status)->toBe(201);
    });
});
