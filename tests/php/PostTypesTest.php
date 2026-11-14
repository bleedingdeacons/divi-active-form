<?php

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DiviActiveForm\Form\PostTypes;

function dafPostType(string $name, string $label): WP_Post_Type
{
    $type        = new WP_Post_Type($name);
    $type->label = $label;

    return $type;
}

describe('PostTypes::options', function (): void {
    beforeEach(function (): void {
        Functions\when('get_post_types')->justReturn([
            'post'          => dafPostType('post', 'Posts'),
            'page'          => dafPostType('page', 'Pages'),
            'attachment'    => dafPostType('attachment', 'Media'),
            'wp_block'      => dafPostType('wp_block', 'Patterns'),
            'acf-field-group' => dafPostType('acf-field-group', 'Field Groups'),
            'et_pb_layout'  => dafPostType('et_pb_layout', 'Layouts'),
            'answer'        => dafPostType('answer', 'Answers'),
            'nameless_type' => dafPostType('nameless_type', ''),
        ]);
    });

    it('lists content post types with an admin screen as "Label (name)", by label', function (): void {
        expect(PostTypes::options())->toBe([
            [ 'value' => 'answer', 'label' => 'Answers (answer)' ],
            [ 'value' => 'nameless_type', 'label' => 'nameless_type (nameless_type)' ],
            [ 'value' => 'page', 'label' => 'Pages (page)' ],
            [ 'value' => 'post', 'label' => 'Posts (post)' ],
        ]);
    });

    it('can be changed with a filter, dropping anything unusable', function (): void {
        Filters\expectApplied('divi_active_form/post_types')->andReturn([
            [ 'value' => 'event', 'label' => 'Events' ],
            [ 'value' => '' ],
            'junk',
            [ 'value' => 'note' ],
        ]);

        expect(PostTypes::options())->toBe([
            [ 'value' => 'event', 'label' => 'Events' ],
            [ 'value' => 'note', 'label' => 'note' ],
        ]);
    });
});
