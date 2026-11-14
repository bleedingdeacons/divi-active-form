<?php

declare(strict_types=1);

use DiviActiveForm\Form\Conditions;
use DiviActiveForm\Form\FieldDefinition;
use DiviActiveForm\Form\FieldType;
use DiviActiveForm\Form\FormSchema;
use DiviActiveForm\Form\Options\OptionProvider;
use DiviActiveForm\Form\Submission\Validator;
use DiviActiveForm\Render\FieldRenderer;

/** Small / Medium / Large, as the Dropdown Options example offers them. */
final class SizeOptions implements OptionProvider
{
    public function options(FieldDefinition $field): array
    {
        return [ [ 'value' => 'small', 'label' => 'Small' ], [ 'value' => 'large', 'label' => 'Large' ] ];
    }
}

describe('Conditions::parse', function (): void {
    it('reads each kind of rule', function (): void {
        $rules = Conditions::parse("size = large\nsize != 'small'\nemail is empty\nemail is not empty\nagree is checked\nagree is not checked")->rules;

        expect($rules)->toBe([
            [ 'field' => 'size', 'op' => '=', 'value' => 'large' ],
            [ 'field' => 'size', 'op' => '!=', 'value' => 'small' ],
            [ 'field' => 'email', 'op' => 'empty', 'value' => '' ],
            [ 'field' => 'email', 'op' => 'filled', 'value' => '' ],
            [ 'field' => 'agree', 'op' => 'filled', 'value' => '' ],
            [ 'field' => 'agree', 'op' => 'empty', 'value' => '' ],
        ]);
    });

    it('drops lines it cannot read, and blank lines', function (): void {
        expect(Conditions::parse("\n  \nsize > 3\njust words\nsize=large")->rules)
            ->toBe([ [ 'field' => 'size', 'op' => '=', 'value' => 'large' ] ]);
    });

    it('reads the match setting', function (): void {
        expect(Conditions::parse('a = 1', 'any')->any)->toBeTrue()
            ->and(Conditions::parse('a = 1', 'all')->any)->toBeFalse()
            ->and(Conditions::parse('a = 1', '')->any)->toBeFalse();
    });
});

describe('Conditions::passes', function (): void {
    it('always passes with no rules', function (): void {
        expect(( new Conditions() )->passes([]))->toBeTrue();
    });

    it('compares trimmed text, ignoring case, and treats a missing field as empty', function (): void {
        $c = Conditions::parse('size = Large');

        expect($c->passes([ 'size' => ' large ' ]))->toBeTrue()
            ->and($c->passes([ 'size' => 'small' ]))->toBeFalse()
            ->and($c->passes([]))->toBeFalse()
            ->and(Conditions::parse('size is empty')->passes([]))->toBeTrue();
    });

    it('needs every rule, or any rule', function (): void {
        $text   = "size = large\nemail is not empty";
        $values = [ 'size' => 'large', 'email' => '' ];

        expect(Conditions::parse($text, 'all')->passes($values))->toBeFalse()
            ->and(Conditions::parse($text, 'any')->passes($values))->toBeTrue();
    });

    it('survives the signed schema round trip, dropping anything malformed', function (): void {
        $c = Conditions::parse("size = large\nagree is checked", 'any');

        expect(Conditions::fromArray($c->toArray()))->toEqual($c)
            ->and(Conditions::fromArray([ 'rules' => [ [ 'field' => 'x', 'op' => 'like', 'value' => '1' ], 'junk' ] ])->rules)->toBe([]);
    });
});

describe('Validator with Conditional Logic', function (): void {
    $schema = new FormSchema('f', 'post', 'pending', '', false, [
        new FieldDefinition('size', 'Size', FieldType::Select),
        new FieldDefinition('reason', 'Why large?', FieldType::Text, true, visibleWhen: Conditions::parse('size = large')),
        new FieldDefinition('callback', 'Call me back', FieldType::Checkbox),
        new FieldDefinition('phone', 'Phone', FieldType::Tel, true, visibleWhen: Conditions::parse('callback is checked')),
        new FieldDefinition('note', 'Note', FieldType::Text, enabledWhen: Conditions::parse('phone is not empty')),
    ]);
    $validate = static fn (array $input): array => ( new Validator(new SizeOptions()) )->validate($schema, $input);

    it('does not require, check or keep a hidden field', function () use ($validate): void {
        $result = $validate([ 'size' => 'small', 'reason' => 'sneaked in', 'phone' => 'not a phone' ]);

        expect($result['errors'])->toBe([])
            ->and($result['values'])->toBe([ 'size' => 'small', 'callback' => '' ]);
    });

    it('requires a field once its rules show it', function () use ($validate): void {
        expect($validate([ 'size' => 'large', 'callback' => '1' ])['errors'])->toBe([
            'reason' => 'Why large? is required.',
            'phone'  => 'Phone is required.',
        ]);
    });

    it('drops a disabled field\'s value', function () use ($validate): void {
        expect($validate([ 'size' => 'small', 'note' => 'hello' ])['values'])->not->toHaveKey('note');
    });

    it('treats a hidden field as empty in other fields\' rules', function () use ($validate): void {
        // phone is filled but hidden (no callback), so note stays disabled.
        $result = $validate([ 'size' => 'small', 'phone' => '0123', 'note' => 'hello' ]);

        expect($result['values'])->not->toHaveKey('phone')
            ->and($result['values'])->not->toHaveKey('note');

        // With callback ticked, phone counts, so note is enabled and kept.
        expect($validate([ 'size' => 'small', 'callback' => 'on', 'phone' => '0123', 'note' => 'hello' ])['values'])
            ->toMatchArray([ 'phone' => '0123', 'note' => 'hello' ]);
    });

    it('stops rules that depend on each other in a circle', function (): void {
        $loop = new FormSchema('f', 'post', 'pending', '', false, [
            new FieldDefinition('a', 'A', FieldType::Text, visibleWhen: Conditions::parse('b is empty')),
            new FieldDefinition('b', 'B', FieldType::Text, visibleWhen: Conditions::parse('a is empty')),
        ]);

        expect(Validator::activeFields($loop, [ 'a' => 'x', 'b' => 'y' ]))->toBeArray();
    });
});

describe('FieldRenderer with Conditional Logic', function (): void {
    it('writes the parsed rules for the front-end script, and hides nothing itself', function (): void {
        $html = FieldRenderer::render(
            new FieldDefinition('reason', 'Why?', FieldType::Text, enabledWhen: Conditions::parse('size = large', 'any')),
            [],
            'r'
        );

        expect($html)
            ->toContain('data-daf-enabled="{&quot;any&quot;:true,&quot;rules&quot;:[{&quot;field&quot;:&quot;size&quot;,&quot;op&quot;:&quot;=&quot;,&quot;value&quot;:&quot;large&quot;}]}"')
            ->not->toContain('data-daf-visible')
            ->not->toMatch('/data-daf-field="reason"[^>]*\shidden/')
            ->not->toContain('disabled');
    });
});
