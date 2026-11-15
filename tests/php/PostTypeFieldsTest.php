<?php

declare(strict_types=1);

use BleedingDeacons\WpMocks\Doubles\FakeWpdb;
use Brain\Monkey\Functions;
use DiviActiveForm\Form\PostTypeFields;

describe('PostTypeFields::options', function (): void {
    beforeEach(function (): void {
        Functions\when('post_type_exists')->alias(static fn (string $type): bool => in_array($type, [ 'project', 'tsml_group' ], true));
        $GLOBALS['wpdb'] = new FakeWpdb();
    });

    afterEach(function (): void {
        unset($GLOBALS['wpdb']);
    });

    it('lists the ACF fields assigned to the post type, then the meta keys its posts use', function (): void {
        Functions\when('acf_get_field_groups')->alias(static fn (array $filter): array => 'project' === ( $filter['post_type'] ?? '' )
            ? [ [ 'title' => 'Projects' ] ]
            : []);
        Functions\when('acf_get_fields')->justReturn([ [ 'name' => 'region', 'label' => 'Region' ], [ 'name' => 'budget', 'label' => 'Budget' ] ]);
        $GLOBALS['wpdb']->col = [ 'budget', 'colour', '_edit_lock' ];

        expect(PostTypeFields::options('project'))->toBe([
            [ 'value' => 'budget', 'label' => 'Budget (budget) · Projects' ],
            [ 'value' => 'region', 'label' => 'Region (region) · Projects' ],
            [ 'value' => 'colour', 'label' => 'colour (post meta)' ],
        ]);
    });

    it('lists plain post meta for a post type with no ACF fields, such as tsml_group', function (): void {
        Functions\when('acf_get_field_groups')->justReturn([]);
        $GLOBALS['wpdb']->col = [ 'email', 'phone', 'website' ];

        expect(array_column(PostTypeFields::options('tsml_group'), 'label'))
            ->toBe([ 'email (post meta)', 'phone (post meta)', 'website (post meta)' ]);
    });

    it('asks the database only for this post type\'s non-private keys', function (): void {
        Functions\when('acf_get_field_groups')->justReturn([]);
        PostTypeFields::options('tsml_group');

        $sql = (string) end($GLOBALS['wpdb']->queries);
        expect($sql)->toContain("p.post_type = 'tsml_group'")
            ->toContain('NOT LIKE')
            ->toContain('LIMIT 200');
    });

    it('returns nothing for a missing or unknown post type', function (): void {
        expect(PostTypeFields::options(''))->toBe([])
            ->and(PostTypeFields::options('nope'))->toBe([]);
    });
});
