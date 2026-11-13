<?php
/**
 * Seeds the dev site with sample TSML groups and a Register Form demo page.
 *
 * Run through WP-CLI (bin/dev.ps1 seed does this):
 *   wp eval-file bin/seed.php            create sample data (idempotent: replaces earlier samples)
 *   wp eval-file bin/seed.php reset      remove sample data only
 *
 * Everything created is tagged with the `_daf_sample` meta key, so reset never touches
 * real content. Submissions made on the demo page are not samples and are left alone.
 *
 * The demo form saves each submission as a draft post titled from the chosen group, so
 * it can be submitted end to end without publishing anything.
 *
 * @package DiviActiveForm
 */

// No declare(strict_types=1): wp eval-file runs this through eval(), where it is a fatal error.

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 'Run with wp eval-file.' );
}

if ( ! defined( 'DAF_SAMPLE_META' ) ) {
	define( 'DAF_SAMPLE_META', '_daf_sample' );
}

$daf_mode = $args[0] ?? 'seed';

$daf_reset = static function (): void {
	$ids = get_posts(
		[
			'post_type'      => [ 'tsml_group', 'page' ],
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => DAF_SAMPLE_META, // phpcs:ignore WordPress.DB.SlowDBQuery
		]
	);
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	WP_CLI::log( sprintf( 'Removed %d sample post(s).', count( $ids ) ) );
};

$daf_reset();
if ( 'reset' === $daf_mode ) {
	WP_CLI::success( 'Sample data removed.' );
	return;
}

// Sample groups, only when 12 Step Meeting List provides the post type.
if ( post_type_exists( 'tsml_group' ) ) {
	foreach ( [ 'Bath Tuesday Group', 'Bristol Central Friday', 'Clifton Big Book Study', 'Keynsham Saturday Morning', 'Weston-super-Mare Step Group' ] as $daf_name ) {
		$daf_group = wp_insert_post(
			[
				'post_type'   => 'tsml_group',
				'post_status' => 'publish',
				'post_title'  => $daf_name,
				'meta_input'  => [ DAF_SAMPLE_META => 1 ],
			],
			true
		);
		if ( is_wp_error( $daf_group ) ) {
			WP_CLI::warning( "Could not create {$daf_name}: " . $daf_group->get_error_message() );
		}
	}
	WP_CLI::log( 'Created 5 sample groups.' );
} else {
	WP_CLI::warning( '12 Step Meeting List is not active, so there are no groups and the group dropdowns will be empty.' );
}

$daf_version = defined( 'ET_BUILDER_PRODUCT_VERSION' ) ? ET_BUILDER_PRODUCT_VERSION : '5.0.0';

/**
 * One Form Field block.
 *
 * @param array<string, string> $settings fieldItem.advanced settings.
 */
$daf_field = static function ( string $label, array $settings ) use ( $daf_version ): string {
	$advanced = [];
	foreach ( $settings as $key => $value ) {
		$advanced[ $key ] = [ 'desktop' => [ 'value' => $value ] ];
	}
	$attrs = [
		'fieldItem'      => [
			'innerContent' => [ 'desktop' => [ 'value' => $label ] ],
			'advanced'     => $advanced,
		],
		'builderVersion' => $daf_version,
	];

	return '<!-- wp:divi-active-form/form-field ' . wp_json_encode( $attrs ) . ' --><!-- /wp:divi-active-form/form-field -->';
};

$daf_groups = '/wp-json/divi-active-form/v1/groups';

$daf_fields = $daf_field(
	'Your group',
	[
		'name'       => 'group',
		'type'       => 'json',
		'required'   => 'on',
		'source'     => $daf_groups,
		'emptyLabel' => 'Choose your group',
	]
)
	. $daf_field(
		'Topic',
		[
			'name'       => 'topic',
			'type'       => 'select',
			'options'    => "general|General enquiry\nservice|Service\nliterature|Literature",
			'emptyLabel' => 'Choose a topic',
		]
	)
	. $daf_field(
		'Email address',
		[
			'name'        => 'email',
			'type'        => 'email',
			'required'    => 'on',
			'placeholder' => 'you@example.org',
			'fullwidth'   => 'off',
		]
	)
	. $daf_field(
		'Phone',
		[
			'name'      => 'phone',
			'type'      => 'tel',
			'fullwidth' => 'off',
		]
	)
	. $daf_field(
		'Message',
		[
			'name' => 'message',
			'type' => 'textarea',
		]
	);

$daf_form_attrs = [
	'form'           => [
		'advanced' => [
			'title'          => [ 'desktop' => [ 'value' => 'Get in touch' ] ],
			'successMessage' => [ 'desktop' => [ 'value' => 'Thank you. We will be in touch soon.' ] ],
			'postType'       => [ 'desktop' => [ 'value' => 'post' ] ],
			'postStatus'     => [ 'desktop' => [ 'value' => 'draft' ] ],
			'titleTemplate'  => [ 'desktop' => [ 'value' => 'Submission from {group}' ] ],
		],
	],
	'builderVersion' => $daf_version,
];

$daf_content = str_replace(
	'__V__',
	$daf_version,
	'<!-- wp:divi/placeholder -->'
	. '<!-- wp:divi/section {"builderVersion":"__V__"} -->'
	. '<!-- wp:divi/row {"module":{"advanced":{"columnStructure":{"desktop":{"value":"4_4"}},"flexColumnStructure":{"desktop":{"value":"equal-columns_1"}}},"decoration":{"layout":{"desktop":{"value":{"flexWrap":"nowrap"}}}}},"builderVersion":"__V__"} -->'
	. '<!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"4_4"}}},"decoration":{"sizing":{"desktop":{"value":{"flexType":"24_24"}}}}},"builderVersion":"__V__"} -->'
	. '<!-- wp:divi-active-form/register-form ' . wp_json_encode( $daf_form_attrs ) . ' -->'
	. $daf_fields
	. '<!-- /wp:divi-active-form/register-form -->'
	. '<!-- /wp:divi/column -->'
	. '<!-- /wp:divi/row -->'
	. '<!-- /wp:divi/section -->'
	. '<!-- /wp:divi/placeholder -->'
);

$daf_page = wp_insert_post(
	[
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Register Form Demo',
		'post_name'    => 'divi-active-form-demo',
		'post_content' => wp_slash( $daf_content ),
		'meta_input'   => [
			DAF_SAMPLE_META        => 1,
			'_et_pb_use_builder'   => 'on',
			'_et_pb_page_layout'   => 'et_full_width_page',
			'_et_pb_side_nav'      => 'off',
			'_et_pb_post_hide_nav' => 'default',
		],
	],
	true
);

if ( is_wp_error( $daf_page ) ) {
	WP_CLI::error( $daf_page->get_error_message() );
}

WP_CLI::success(
	sprintf(
		'Demo page: %s (submissions create draft posts).',
		get_permalink( (int) $daf_page )
	)
);
