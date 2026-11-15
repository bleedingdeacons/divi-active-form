<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use DiviActiveForm\Form\AcfFields;
use DiviActiveForm\Form\FieldDefinition;
use DiviActiveForm\Form\FieldType;
use DiviActiveForm\Form\Options\AcfFieldOptions;
use DiviActiveForm\Form\Options\FieldOptions;

/** Projects 10–14 and their `region` values, as get_field() would return them. */
function dafRegions(): void
{
    $regions = [ 10 => 'Bristol', 11 => 'Bath', 12 => '', 13 => 'bristol', 14 => 'Bristol' ];
    Functions\when('post_type_exists')->alias(static fn (string $type): bool => 'project' === $type);
    Functions\when('get_posts')->alias(static function (array $args) use ($regions): array {
        $GLOBALS['dafGetPostsArgs'] = $args;

        return array_keys($regions);
    });
    Functions\when('get_field')->alias(static fn (string $name, int $id): mixed => 'region' === $name ? $regions[ $id ] : null);
}

describe('AcfFieldOptions', function (): void {
    it('lists each distinct value of the field across the post type, in natural order, skipping empty ones', function (): void {
        dafRegions();

        expect(AcfFieldOptions::options('project', 'region'))->toBe([
            [ 'value' => 'Bath', 'label' => 'Bath' ],
            [ 'value' => 'Bristol', 'label' => 'Bristol' ],
            [ 'value' => 'bristol', 'label' => 'bristol' ],
        ]);
    });

    it('reads published posts of the chosen post type', function (): void {
        dafRegions();
        AcfFieldOptions::options('project', 'region');

        expect($GLOBALS['dafGetPostsArgs'])->toMatchArray([ 'post_type' => 'project', 'post_status' => 'publish', 'fields' => 'ids' ]);
    });

    it('returns nothing without a post type and field, or for an unknown post type', function (): void {
        dafRegions();

        expect(AcfFieldOptions::options('', 'region'))->toBe([])
            ->and(AcfFieldOptions::options('project', ''))->toBe([])
            ->and(AcfFieldOptions::options('nope', 'region'))->toBe([]);
    });

    it('turns formatted ACF values into readable text', function (): void {
        $post             = new WP_Post();
        $post->post_title = 'Harbourside';
        $term             = (object) [ 'name' => 'Events' ];

        expect(AcfFieldOptions::text(' <b>Bath</b> '))->toBe('Bath')
            ->and(AcfFieldOptions::text(12))->toBe('12')
            ->and(AcfFieldOptions::text(true))->toBe('1')
            ->and(AcfFieldOptions::text(false))->toBe('')
            ->and(AcfFieldOptions::text(null))->toBe('')
            ->and(AcfFieldOptions::text([ 'value' => 'b', 'label' => 'Bath' ]))->toBe('Bath')
            ->and(AcfFieldOptions::text($post))->toBe('Harbourside')
            ->and(AcfFieldOptions::text($term))->toBe('Events')
            ->and(AcfFieldOptions::text([ 'Red', '', 'Blue' ]))->toBe('Red, Blue');
    });

    it('serves the ACF Dropdown through FieldOptions, so submissions are checked against the same list', function (): void {
        dafRegions();
        $field = new FieldDefinition('region', 'Region', FieldType::Acf, acfPostType: 'project', acfField: 'region');

        expect(array_column(( new FieldOptions() )->options($field), 'value'))->toBe([ 'Bath', 'Bristol', 'bristol' ]);
    });
});

describe('AcfFieldOptions on post fields and taxonomies', function (): void {
    beforeEach(function (): void {
        Functions\when('post_type_exists')->justReturn(true);
        Functions\when('get_posts')->justReturn([ 20, 21, 22 ]);
    });

    it('reads the post\'s own fields, with dates as YYYY-MM-DD and authors by name', function (): void {
        $posts = [
            20 => [ 'post_title' => 'Bath Tuesday', 'post_date' => '2026-11-03 19:30:00', 'post_author' => 1 ],
            21 => [ 'post_title' => 'Bristol Friday', 'post_date' => '2026-11-03 10:00:00', 'post_author' => 2 ],
            22 => [ 'post_title' => '', 'post_date' => '2026-12-01 09:00:00', 'post_author' => 2 ],
        ];
        Functions\when('get_post')->alias(static function (int $id) use ($posts): WP_Post {
            $post = new WP_Post();
            foreach ($posts[ $id ] as $key => $value) {
                $post->{$key} = $value;
            }

            return $post;
        });
        Functions\when('get_the_author_meta')->alias(static fn (string $field, int $id): string => [ 1 => 'Ann', 2 => 'Bob' ][ $id ]);

        expect(array_column(AcfFieldOptions::options('tsml_group', 'post_title'), 'value'))->toBe([ 'Bath Tuesday', 'Bristol Friday' ])
            ->and(array_column(AcfFieldOptions::options('tsml_group', 'post_date'), 'value'))->toBe([ '2026-11-03', '2026-12-01' ])
            ->and(array_column(AcfFieldOptions::options('tsml_group', 'post_author'), 'value'))->toBe([ 'Ann', 'Bob' ]);
    });

    it('lists each term of a taxonomy once, across every post', function (): void {
        Functions\when('wp_get_post_terms')->alias(static fn (int $id, string $taxonomy): array => 'category' === $taxonomy
            ? [ 20 => [ 'News', 'Events' ], 21 => [ 'News' ], 22 => [] ][ $id ]
            : []);

        expect(array_column(AcfFieldOptions::options('post', 'taxonomy:category'), 'value'))->toBe([ 'Events', 'News' ]);
    });
});

describe('ACF Dropdown rendering', function (): void {
    it('renders a select with the options, and flags an empty one', function (): void {
        $field = new FieldDefinition('region', 'Region', FieldType::Acf, placeholder: 'Choose a region', acfPostType: 'project', acfField: 'region');

        expect(DiviActiveForm\Render\FieldRenderer::render($field, [ [ 'value' => 'Bath', 'label' => 'Bath' ] ], 'r'))
            ->toContain('<select class="daf-field__input daf-field__select" id="r" name="region"')
            ->toContain('<option value="">Choose a region</option><option value="Bath">Bath</option>')
            ->not->toContain('type="acf"')
            ->and(DiviActiveForm\Render\FieldRenderer::render($field, [], 'r'))->toContain('data-daf-empty=');
    });
});

describe('ACF Dropdown settings', function (): void {
    it('reads the post type and field from a Form Field', function (): void {
        $attrs = [ 'fieldItem' => [
            'innerContent' => [ 'desktop' => [ 'value' => 'Region' ] ],
            'advanced'     => [
                'type'        => [ 'desktop' => [ 'value' => 'acf' ] ],
                'acfPostType' => [ 'desktop' => [ 'value' => 'Project' ] ],
                'acfField'    => [ 'desktop' => [ 'value' => ' daf_region ' ] ],
            ],
        ] ];
        $field = FieldDefinition::fromAttrs($attrs);

        expect($field->type)->toBe(FieldType::Acf)
            ->and($field->type->hasChoices())->toBeTrue()
            ->and($field->acfPostType)->toBe('project')
            ->and($field->acfField)->toBe('daf_region')
            ->and(FieldDefinition::fromArray($field->toArray()))->toEqual($field);
    });
});

describe('AcfFields', function (): void {
    it('lists every ACF field name once, labelled with its group', function (): void {
        Functions\when('acf_get_field_groups')->justReturn([ [ 'title' => 'Projects' ], [ 'title' => 'Other' ] ]);
        Functions\when('acf_get_fields')->alias(static fn (array $group): array => 'Projects' === $group['title']
            ? [ [ 'name' => 'region', 'label' => 'Region' ], [ 'name' => 'code', 'label' => '' ] ]
            : [ [ 'name' => 'region', 'label' => 'Duplicate' ] ]);

        expect(AcfFields::options())->toBe([
            [ 'value' => 'code', 'label' => 'code (code) · Projects' ],
            [ 'value' => 'region', 'label' => 'Region (region) · Projects' ],
        ]);
    });

    it('is empty when ACF has no field groups', function (): void {
        Functions\when('acf_get_field_groups')->justReturn(null);

        expect(AcfFields::options())->toBe([]);
    });
});
