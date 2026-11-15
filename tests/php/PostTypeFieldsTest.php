<?php

declare(strict_types=1);

use BleedingDeacons\WpMocks\Doubles\FakeWpdb;
use Brain\Monkey\Functions;
use DiviActiveForm\Form\PostTypeFields;

describe('PostTypeFields::options', function (): void {
    beforeEach(function (): void {
        Functions\when('post_type_exists')->alias(static fn (string $type): bool => in_array($type, [ 'project', 'tsml_group' ], true));
        $GLOBALS['wpdb'] = new FakeWpdb();
        Functions\when('post_type_supports')->justReturn(false);
        Functions\when('get_object_taxonomies')->justReturn([]);
    });

    afterEach(function (): void {
        unset($GLOBALS['wpdb']);
    });

    it('lists the post\'s own fields, then ACF fields assigned to the post type, then the meta keys its posts use', function (): void {
        Functions\when('acf_get_field_groups')->alias(static fn (array $filter): array => 'project' === ( $filter['post_type'] ?? '' )
            ? [ [ 'title' => 'Projects' ] ]
            : []);
        Functions\when('acf_get_fields')->justReturn([ [ 'name' => 'region', 'label' => 'Region' ], [ 'name' => 'budget', 'label' => 'Budget' ] ]);
        $GLOBALS['wpdb']->col = [ 'budget', 'colour', '_edit_lock' ];

        expect(PostTypeFields::options('project'))->toBe([
            [ 'value' => 'post_title', 'label' => 'Title (post_title)' ],
            [ 'value' => 'post_name', 'label' => 'Slug (post_name)' ],
            [ 'value' => 'post_date', 'label' => 'Date (post_date)' ],
            [ 'value' => 'budget', 'label' => 'Budget (budget) · Projects' ],
            [ 'value' => 'region', 'label' => 'Region (region) · Projects' ],
            [ 'value' => 'colour', 'label' => 'colour (post meta)' ],
        ]);
    });

    it('lists plain post meta for a post type with no ACF fields, such as tsml_group', function (): void {
        Functions\when('acf_get_field_groups')->justReturn([]);
        $GLOBALS['wpdb']->col = [ 'email', 'phone', 'website' ];

        expect(array_column(PostTypeFields::options('tsml_group'), 'label'))
            ->toBe([ 'Title (post_title)', 'Slug (post_name)', 'Date (post_date)', 'email (post meta)', 'phone (post meta)', 'website (post meta)' ]);
    });

    it('adds Excerpt and Author where supported, and public or on-screen taxonomies', function (): void {
        Functions\when('acf_get_field_groups')->justReturn([]);
        Functions\when('post_type_supports')->alias(static fn (string $type, string $feature): bool => in_array($feature, [ 'excerpt', 'author' ], true));
        Functions\when('get_object_taxonomies')->justReturn([
            'category' => (object) [ 'label' => 'Categories', 'public' => true, 'show_ui' => true ],
            'post_tag' => (object) [ 'label' => 'Tags', 'public' => true, 'show_ui' => true ],
            'internal' => (object) [ 'label' => 'Internal', 'public' => false, 'show_ui' => false ],
        ]);
        $GLOBALS['wpdb']->col = [];

        expect(array_column(PostTypeFields::options('project'), 'value'))->toBe([
            'post_title', 'post_name', 'post_date', 'post_excerpt', 'post_author', 'taxonomy:category', 'taxonomy:post_tag',
        ])
            ->and(PostTypeFields::options('project')[5]['label'])->toBe('Categories (taxonomy)');
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
