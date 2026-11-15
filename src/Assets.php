<?php

/**
 * Script and style registration for the front end and the Divi Visual Builder.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm;

use DiviActiveForm\Rest\FormController;

if (! defined('ABSPATH')) {
    exit;
}

final class Assets
{
    public const FRONTEND_HANDLE = 'divi-active-form-form';
    public const BUILDER_HANDLE  = 'divi-active-form-builder';

    /**
     * Register (not enqueue) the front-end form script and styles; the module enqueues them
     * when it renders.
     */
    public static function registerFrontend(): void
    {
        wp_register_style(
            self::FRONTEND_HANDLE,
            DIVI_ACTIVE_FORM_URL . 'assets/build/form.css',
            [],
            self::version('assets/build/form.css')
        );
        wp_register_script(
            self::FRONTEND_HANDLE,
            DIVI_ACTIVE_FORM_URL . 'assets/build/form.js',
            [],
            self::version('assets/build/form.js'),
            [
                'in_footer' => true,
                'strategy'  => 'defer',
            ]
        );
    }

    public static function enqueueFrontend(): void
    {
        if (! wp_script_is(self::FRONTEND_HANDLE, 'registered')) {
            self::registerFrontend();
        }
        wp_enqueue_style(self::FRONTEND_HANDLE);
        wp_enqueue_script(self::FRONTEND_HANDLE);
    }

    /**
     * Visual Builder bundle, loaded into the builder's app window only.
     * Divi exposes the data as `window.DiviActiveFormBuilderData`.
     */
    public static function registerBuilder(): void
    {
        if (! function_exists('et_core_is_fb_enabled') || ! et_core_is_fb_enabled()) {
            return;
        }
        if (! class_exists(\ET\Builder\VisualBuilder\Assets\PackageBuildManager::class)) {
            return;
        }

        \ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
            [
                'name'    => self::BUILDER_HANDLE,
                'version' => self::version('assets/build/builder.js'),
                'script'  => [
                    'src'                => DIVI_ACTIVE_FORM_URL . 'assets/build/builder.js',
                    'deps'               => [ 'divi-module-library', 'divi-module', 'divi-field-library', 'divi-module-utils', 'divi-data', 'divi-vendor-wp-hooks' ],
                    'enqueue_top_window' => false,
                    'enqueue_app_window' => true,
                    'data_app_window'    => [
                        'optionsUrl' => esc_url_raw(rest_url(FormController::NAMESPACE . '/options')),
                        'fieldsUrl'  => esc_url_raw(rest_url(FormController::NAMESPACE . '/fields')),
                        'nonce'      => wp_create_nonce('wp_rest'),
                        'postTypes'  => Form\PostTypes::options(),
                        'acfFields'  => Form\AcfFields::options(),
                    ],
                ],
                'style'   => [
                    'src'                => DIVI_ACTIVE_FORM_URL . 'assets/build/form.css',
                    'deps'               => [],
                    'enqueue_top_window' => false,
                    'enqueue_app_window' => true,
                ],
            ]
        );
    }

    /**
     * Cache-bust with the file's mtime in development, the plugin version otherwise.
     */
    private static function version(string $relative): string
    {
        $path = DIVI_ACTIVE_FORM_DIR . $relative;
        if (defined('WP_DEBUG') && WP_DEBUG && is_readable($path)) {
            return DIVI_ACTIVE_FORM_VERSION . '.' . (string) filemtime($path);
        }

        return DIVI_ACTIVE_FORM_VERSION;
    }
}
