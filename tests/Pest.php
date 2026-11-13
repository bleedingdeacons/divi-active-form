<?php

declare(strict_types=1);

// Pest configuration.
//
// Every test under tests/php runs on wp-mocks' TestCase, which runs Brain
// Monkey's setUp/tearDown and resets WpState between tests. A test placed
// anywhere else runs on plain PHPUnit and has to be named here if it touches
// WordPress.

use BleedingDeacons\WpMocks\TestCase;
use Brain\Monkey\Functions;

pest()
    ->extend(TestCase::class)
    ->beforeEach(function (): void {
        // WordPress functions this plugin calls that wp-mocks does not stub.
        // Each behaves as WordPress's own does for the inputs the tests use;
        // a test that cares sets its own.
        Functions\when('sanitize_html_class')->alias(
            static fn (string $class): string => (string) preg_replace('/[^A-Za-z0-9_-]/', '', $class)
        );
        Functions\when('post_type_exists')->alias(
            static fn (string $type): bool => in_array($type, [ 'post', 'page', 'signup' ], true)
        );
        Functions\when('wp_parse_url')->alias(
            static fn (string $url, int $component = -1): mixed => parse_url($url, $component)
        );
        Functions\when('rest_get_url_prefix')->justReturn('wp-json');
    })
    ->in('php');
