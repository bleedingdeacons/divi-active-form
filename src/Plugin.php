<?php

/**
 * Wires the plugin into WordPress and Divi 5.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm;

use DiviActiveForm\Render\FormRenderer;
use DiviActiveForm\Rest\FormController;

final class Plugin
{
    public function boot(): void
    {
        add_action('init', [ $this, 'init' ]);
        add_action('rest_api_init', [ $this, 'registerRestRoutes' ]);

        // The form's no-JavaScript fallback, for visitors and signed-in users alike.
        add_action('admin_post_nopriv_' . FormRenderer::ADMIN_POST_ACTION, [ $this, 'handleAdminPost' ]);
        add_action('admin_post_' . FormRenderer::ADMIN_POST_ACTION, [ $this, 'handleAdminPost' ]);

        // Divi 5: module registration (front end + builder) and the builder bundle.
        add_action('divi_module_library_modules_dependency_tree', [ $this, 'registerModules' ]);
        add_action('divi_visual_builder_assets_before_enqueue_scripts', [ Assets::class, 'registerBuilder' ]);

        add_action('admin_notices', [ $this, 'dependencyNotice' ]);

        /**
         * Fires once Divi Active Form has hooked itself in.
         */
        do_action('divi_active_form/loaded');
    }

    public function init(): void
    {
        load_plugin_textdomain('divi-active-form', false, dirname(plugin_basename(DIVI_ACTIVE_FORM_FILE)) . '/languages');
        Assets::registerFrontend();
    }

    public function registerRestRoutes(): void
    {
        ( new FormController() )->register();
    }

    public function handleAdminPost(): void
    {
        ( new FormController() )->handleAdminPost();
    }

    /**
     * @param mixed $dependencyTree ET\Builder\Framework\DependencyManagement\DependencyTree.
     */
    public function registerModules($dependencyTree): void
    {
        if (! $dependencyTree instanceof \ET\Builder\Framework\DependencyManagement\DependencyTree) {
            return;
        }
        $dependencyTree->add_dependency(new Divi\RegisterFormModule());
        $dependencyTree->add_dependency(new Divi\FormFieldModule());
    }

    public function dependencyNotice(): void
    {
        if (! current_user_can('activate_plugins') || function_exists('et_builder_d5_enabled')) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            esc_html__('Divi Active Form needs the Divi 5 theme to be active.', 'divi-active-form')
        );
    }
}
