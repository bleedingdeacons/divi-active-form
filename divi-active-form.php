<?php

/**
 * Plugin Name:       Divi Active Form
 * Description:       Divi 5 form builder: a Form module whose Form Field children include dropdowns filled from a JSON source. Each submission creates a post and fires an after-insert hook.
 * Version:           1.3.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * GitHub Plugin URI: https://github.com/bleedingdeacons/divi-active-form
 * GitHub Branch:     main
 * Author:            The Bleeding Deacons
 * Author URI:        https://github.com/bleedingdeacons/divi-active-form
 * Contact:           thebleedingdeacons@gmail.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       divi-active-form
 * Domain Path:       /languages
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

// Kill switch: define( 'DIVI_ACTIVE_FORM_KILL', true ); in wp-config.php to stand the plugin down without deactivating it.
if (defined('DIVI_ACTIVE_FORM_KILL') && DIVI_ACTIVE_FORM_KILL) {
    return;
}

// The Version: header above is the single source of truth; CI's release job raises it on merge.
define('DIVI_ACTIVE_FORM_VERSION', (string) ( get_file_data(__FILE__, [ 'Version' => 'Version' ])['Version'] ?? '0.0.0' ));
define('DIVI_ACTIVE_FORM_FILE', __FILE__);
define('DIVI_ACTIVE_FORM_DIR', plugin_dir_path(__FILE__));
define('DIVI_ACTIVE_FORM_URL', plugin_dir_url(__FILE__));

// Composer autoloader when present (dev checkouts), otherwise a minimal PSR-4 loader so a
// release zip does not need vendor/.
if (is_readable(DIVI_ACTIVE_FORM_DIR . 'vendor/autoload.php')) {
    require_once DIVI_ACTIVE_FORM_DIR . 'vendor/autoload.php';
} else {
    spl_autoload_register(
        static function (string $class): void {
            if (! str_starts_with($class, 'DiviActiveForm\\')) {
                return;
            }
            $file = DIVI_ACTIVE_FORM_DIR . 'src/' . str_replace('\\', '/', substr($class, strlen('DiviActiveForm\\'))) . '.php';
            if (is_readable($file)) {
                require_once $file;
            }
        }
    );
}

( new DiviActiveForm\Plugin() )->boot();
