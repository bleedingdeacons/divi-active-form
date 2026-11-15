<?php

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DiviActiveForm\Form\PostTypes;

function dafPostType(string $name, string $label, bool $public = true, bool $showUi = true): WP_Post_Type
{
    $type          = new WP_Post_Type($name);
    $type->label   = $label;
    $type->public  = $public;
    $type->show_ui = $showUi;

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
            'tsml_group'    => dafPostType('tsml_group', 'Groups', true, false),
            'internal_log'  => dafPostType('internal_log', 'Log', false, false),
            'admin_only'    => dafPostType('admin_only', 'Admin Only', false, true),
            'nameless_type' => dafPostType('nameless_type', ''),
        ]);
    });

    it('lists public or admin-screen content post types as "Label (name)", by label', function (): void {
        expect(PostTypes::options())->toBe([
            [ 'value' => 'admin_only', 'label' => 'Admin Only (admin_only)' ],
            [ 'value' => 'answer', 'label' => 'Answers (answer)' ],
            [ 'value' => 'tsml_group', 'label' => 'Groups (tsml_group)' ],
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
