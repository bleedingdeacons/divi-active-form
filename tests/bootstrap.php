<?php

declare(strict_types=1);

// PHPUnit bootstrap.
//
// The WordPress surface Divi Active Form touches (posts, post meta,
// transients, escaping, sanitising, i18n) comes from bleedingdeacons/wp-mocks,
// the suite's shared test doubles, rather than being stubbed by hand in each
// test. Tests seed WpState and assert on the result.
//
// wp-mocks' bootstrap loads Patchwork before anything patchable, so anything
// below that defines WordPress functions or classes of its own must stay after
// the Bootstrap::load() call, not before it.
//
// Only the `wordpress` group is loaded, so update_field() does not exist and
// PostWriter takes its plain post-meta path. The ACF path is the same call with
// ACF's function, exercised on the dev site. The REST controller is exercised
// there too.

use BleedingDeacons\WpMocks\Bootstrap;
use BleedingDeacons\WpMocks\WpState;

require_once dirname(__DIR__) . '/vendor/autoload.php';

Bootstrap::load(['wordpress']);

// Makes plugins_url()/plugin_dir_url() answer with Divi Active Form's own path.
WpState::$pluginSlug = 'divi-active-form';

// Every source file begins with `if (!defined('ABSPATH')) { exit; }`, so the
// constant has to exist before any of them is loaded.
if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/divi-active-form-test-wp/');
}
