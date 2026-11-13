<?php

declare(strict_types=1);

use DiviActiveForm\Form\Options\FieldOptions;
use DiviActiveForm\Form\Options\OptionNormaliser;
use DiviActiveForm\Form\Options\StaticOptions;

describe('OptionNormaliser', function (): void {
    it('reads value / label objects', function (): void {
        expect(OptionNormaliser::normalise([
            [ 'value' => 12, 'label' => 'Tuesday Group' ],
            [ 'value' => '15', 'label' => 'Friday Group' ],
        ]))->toBe([
            [ 'value' => '12', 'label' => 'Tuesday Group' ],
            [ 'value' => '15', 'label' => 'Friday Group' ],
        ]);
    });

    it('uses a list of strings as both value and label', function (): void {
        expect(OptionNormaliser::normalise([ 'Bath', 'Bristol' ]))->toBe([
            [ 'value' => 'Bath', 'label' => 'Bath' ],
            [ 'value' => 'Bristol', 'label' => 'Bristol' ],
        ]);
    });

    it('reads an object map as value => label', function (): void {
        expect(OptionNormaliser::normalise([ '12' => 'Tuesday', 'b' => 'Friday' ]))->toBe([
            [ 'value' => '12', 'label' => 'Tuesday' ],
            [ 'value' => 'b', 'label' => 'Friday' ],
        ]);
    });

    it('follows configured dot-path keys and an items path', function (): void {
        $json = [ 'data' => [ 'groups' => [
            [ 'meta' => [ 'code' => 'G1' ], 'info' => [ 'title' => 'Group One' ] ],
        ] ] ];

        expect(OptionNormaliser::normalise($json, 'data.groups', 'meta.code', 'info.title'))
            ->toBe([ [ 'value' => 'G1', 'label' => 'Group One' ] ]);
    });

    it('falls back to id and title.rendered, so WordPress REST collections work as they are', function (): void {
        expect(OptionNormaliser::normalise([
            [ 'id' => 7, 'title' => [ 'rendered' => 'Tea &amp; Biscuits <em>Group</em>' ] ],
        ]))->toBe([ [ 'value' => '7', 'label' => 'Tea & Biscuits Group' ] ]);
    });

    it('drops items with no value, and repeats of a value', function (): void {
        expect(OptionNormaliser::normalise([
            [ 'label' => 'No value' ],
            [ 'value' => '1', 'label' => 'First' ],
            [ 'value' => '1', 'label' => 'Again' ],
            '',
            null,
        ]))->toBe([ [ 'value' => '1', 'label' => 'First' ] ]);
    });

    it('returns nothing for JSON that is not a list or map, or a missing items path', function (): void {
        expect(OptionNormaliser::normalise('text'))->toBe([])
            ->and(OptionNormaliser::normalise(null))->toBe([])
            ->and(OptionNormaliser::normalise([ 'a' => [ 'b' => 1 ] ], 'missing.path'))->toBe([]);
    });
});

describe('StaticOptions', function (): void {
    it('parses value|label lines and bare labels, in order', function (): void {
        expect(StaticOptions::parse("10|Ten\r\n\n  Plain  \n2|Two\n10|Duplicate"))->toBe([
            [ 'value' => '10', 'label' => 'Ten' ],
            [ 'value' => 'Plain', 'label' => 'Plain' ],
            [ 'value' => '2', 'label' => 'Two' ],
        ]);
    });

    it('uses the value as the label when the label is blank', function (): void {
        expect(StaticOptions::parse('x|'))->toBe([ [ 'value' => 'x', 'label' => 'x' ] ]);
    });
});

describe('FieldOptions::restRequest', function (): void {
    it('answers a site path into the REST API in-process', function (): void {
        expect(FieldOptions::restRequest('/wp-json/divi-active-form/v1/groups'))
            ->toBe([ '/divi-active-form/v1/groups', [] ]);
    });

    it('keeps the query string as request parameters', function (): void {
        expect(FieldOptions::restRequest('https://example.test/wp-json/wp/v2/categories?per_page=100'))
            ->toBe([ '/wp/v2/categories', [ 'per_page' => '100' ] ]);
    });

    it('understands ?rest_route= for sites without pretty permalinks', function (): void {
        expect(FieldOptions::restRequest('/?rest_route=/divi-active-form/v1/groups&x=1'))
            ->toBe([ '/divi-active-form/v1/groups', [ 'x' => '1' ] ]);
    });

    it('leaves other hosts and non-REST paths to HTTP', function (): void {
        expect(FieldOptions::restRequest('https://other.example/wp-json/wp/v2/posts'))->toBeNull()
            ->and(FieldOptions::restRequest('/wp-content/uploads/groups.json'))->toBeNull();
    });
});
