<?php

declare(strict_types=1);

use Brain\Monkey\Filters;
use DiviActiveForm\Form\Options\ExampleOptions;
use DiviActiveForm\Form\Options\OptionNormaliser;

describe('ExampleOptions', function (): void {
    it('serves a flat value / label list by default', function (): void {
        $flat = ExampleOptions::shaped('');

        expect($flat[0])->toBe([ 'value' => 'red', 'label' => 'Red' ])
            ->and($flat)->toHaveCount(6);
    });

    it('serves the same options nested, for the Items Path and key settings', function (): void {
        $nested = ExampleOptions::shaped('nested');

        expect($nested['data']['items'][0])->toBe([ 'code' => 'red', 'name' => 'Red' ])
            ->and(OptionNormaliser::normalise($nested, 'data.items', 'code', 'name'))
            ->toBe(OptionNormaliser::normalise(ExampleOptions::shaped('flat')));
    });

    it('reads the flat list with no keys set', function (): void {
        expect(OptionNormaliser::normalise(ExampleOptions::shaped(''))[1])->toBe([ 'value' => 'orange', 'label' => 'Orange' ]);
    });

    it('takes its list from a filter, dropping anything unusable', function (): void {
        Filters\expectApplied('divi_active_form/example_options')->andReturn([ 'uk' => 'United Kingdom', '' => 'No value', 'x' => [ 'bad' ] ]);

        expect(ExampleOptions::shaped(''))->toBe([ [ 'value' => 'uk', 'label' => 'United Kingdom' ] ]);
    });
});
