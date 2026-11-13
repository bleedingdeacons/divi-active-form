<?php

/**
 * The Register Form Divi 5 module: the form around its Form Field children.
 *
 * At render time it reads its children's settings from Divi's block store, builds the
 * form's schema from them, and signs it into the page; the submission is validated
 * against that schema (see SubmissionHandler).
 *
 * Only loaded from the `divi_module_library_modules_dependency_tree` action, so the
 * ET\Builder classes below are guaranteed to exist whenever this file is.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Divi;

use DiviActiveForm\Assets;
use DiviActiveForm\Form\FieldDefinition;
use DiviActiveForm\Form\FormSchema;
use DiviActiveForm\Form\SchemaSigner;
use DiviActiveForm\Render\FormRenderer;
use DiviActiveForm\Rest\FormController;
use DiviActiveForm\Support\Attrs;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Packages\Module\Layout\Components\Classnames;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\Module\Options\Text\TextStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WP_Block_Type_Registry;

final class RegisterFormModule implements DependencyInterface
{
    public const NAME = 'divi-active-form/register-form';

    public function load(): void
    {
        ModuleRegistration::register_module(
            DIVI_ACTIVE_FORM_DIR . 'modules-json/register-form/',
            [
                'render_callback' => [ self::class, 'render_callback' ],
            ]
        );
    }

    /**
     * Front-end render.
     *
     * @param mixed          $attrs    Module attributes (merged with defaults by Divi).
     * @param mixed          $content  The rendered Form Field children.
     * @param \WP_Block      $block    Parsed block.
     * @param ModuleElements $elements Divi's element renderer for this module.
     */
    public static function render_callback(mixed $attrs, mixed $content, \WP_Block $block, ModuleElements $elements): string
    {
        $attrs = Attrs::map($attrs);

        Assets::enqueueFrontend();

        $id            = Attrs::string($block->parsed_block['id'] ?? '');
        $storeInstance = $block->parsed_block['storeInstance'] ?? null;
        $storeInstance = is_int($storeInstance) ? $storeInstance : null;

        $schema = FormSchema::fromAttrs($attrs, self::fieldDefinitions($block, $storeInstance));
        $notice = self::notice();

        $html = FormRenderer::render(
            [
                'token'         => SchemaSigner::fromSalts()->sign($schema),
                'fields'        => is_string($content) ? $content : '',
                'title'         => Attrs::string(Attrs::desktop($attrs, 'form.advanced.title')),
                'submitLabel'   => Attrs::string(Attrs::desktop($attrs, 'form.advanced.submitLabel')),
                'restUrl'       => rest_url(FormController::NAMESPACE . '/submit'),
                'actionUrl'     => admin_url('admin-post.php'),
                'returnUrl'     => self::currentUrl(),
                'notice'        => $notice['message'],
                'noticeIsError' => $notice['error'],
            ]
        );

        $parent = BlockParserStore::get_parent($id, $storeInstance);

        return Module::render(
            [
                // FE only.
                'orderIndex'         => $block->parsed_block['orderIndex'] ?? 0,
                'storeInstance'      => $storeInstance,

                // VB equivalent.
                'attrs'              => $attrs,
                'elements'           => $elements,
                'id'                 => $id,
                'name'               => $block->block_type->name,
                'moduleCategory'     => $block->block_type->category ?? 'module',
                'classnamesFunction' => [ self::class, 'module_classnames' ],
                'stylesComponent'    => [ self::class, 'module_styles' ],
                'parentAttrs'        => $parent->attrs ?? [],
                'parentId'           => $parent->id ?? '',
                'parentName'         => $parent->blockName ?? '',
                'children'           => $elements->style_components([ 'attrName' => 'module' ]) . $html,
            ]
        );
    }

    /**
     * The Form Field children's settings, in order, read from Divi's block store (which
     * holds them after presets are applied), falling back to the parsed block.
     *
     * @return list<FieldDefinition>
     */
    private static function fieldDefinitions(\WP_Block $block, ?int $storeInstance): array
    {
        $innerBlocks = $block->parsed_block['innerBlocks'] ?? [];
        $defaults    = Attrs::map(ModuleRegistration::get_default_attrs(FormFieldModule::NAME));
        $fields      = [];

        foreach (is_array($innerBlocks) ? $innerBlocks : [] as $inner) {
            if (! is_array($inner) || FormFieldModule::NAME !== ( $inner['blockName'] ?? '' )) {
                continue;
            }
            $childAttrs = $inner['attrs'] ?? [];
            $childId    = Attrs::string($inner['id'] ?? '');
            if ('' !== $childId) {
                $stored = BlockParserStore::get($childId, $storeInstance);
                if (null !== $stored && is_array($stored->attrs ?? null)) {
                    $childAttrs = $stored->attrs;
                }
            }
            $fields[] = FieldDefinition::fromAttrs(array_replace_recursive($defaults, Attrs::map($childAttrs)));
        }

        return $fields;
    }

    /**
     * The outcome of a submission sent without JavaScript, from the redirect back here.
     *
     * @return array{message: string, error: bool}
     */
    private static function notice(): array
    {
        // Display only: the values are escaped on output and change nothing.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $status  = sanitize_key(Attrs::string($_GET['daf_status'] ?? ''));
        $message = sanitize_text_field(rawurldecode(Attrs::string(wp_unslash($_GET['daf_message'] ?? ''))));
        // phpcs:enable

        if ('sent' !== $status && 'error' !== $status) {
            return [
                'message' => '',
                'error'   => false,
            ];
        }

        return [
            'message' => $message,
            'error'   => 'error' === $status,
        ];
    }

    private static function currentUrl(): string
    {
        $host = sanitize_text_field(wp_unslash(Attrs::string($_SERVER['HTTP_HOST'] ?? '')));
        $uri  = sanitize_text_field(wp_unslash(Attrs::string($_SERVER['REQUEST_URI'] ?? '/')));
        $url  = '' !== $host ? ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri : home_url('/');

        return remove_query_arg([ 'daf_status', 'daf_message' ], $url);
    }

    /**
     * @param array<string, mixed> $args Divi classnames args.
     */
    public static function module_classnames(array $args): void
    {
        $classnames = $args['classnamesInstance'] ?? null;
        if (! $classnames instanceof Classnames) {
            return;
        }
        $attrs = Attrs::map($args['attrs'] ?? []);

        $classnames->add('daf-form-module', true);
        $classnames->add(TextClassnames::text_options_classnames(Attrs::map(Attrs::at($attrs, 'module.advanced.text'))), true);
        $classnames->add(
            ElementClassnames::classnames(
                [
                    'attrs' => Attrs::map(Attrs::at($attrs, 'module.decoration')),
                ]
            )
        );
    }

    /**
     * @param array<string, mixed> $args Divi style args.
     */
    public static function module_styles(array $args): void
    {
        $elements = $args['elements'] ?? null;
        if (! $elements instanceof ModuleElements) {
            return;
        }
        $attrs       = Attrs::map($args['attrs'] ?? []);
        $order_class = Attrs::string($args['orderClass'] ?? '');

        Style::add(
            [
                'id'            => $args['id'] ?? '',
                'name'          => $args['name'] ?? self::NAME,
                'orderIndex'    => $args['orderIndex'] ?? 0,
                'storeInstance' => $args['storeInstance'] ?? null,
                'styles'        => [
                    $elements->style(
                        [
                            'attrName'   => 'module',
                            'styleProps' => [
                                'disabledOn' => [
                                    'disabledModuleVisibility' => Attrs::at(Attrs::map($args['settings'] ?? []), 'disabledModuleVisibility'),
                                ],
                            ],
                        ]
                    ),
                    TextStyle::style(
                        [
                            'selector'   => $order_class,
                            'attr'       => Attrs::map(Attrs::at($attrs, 'module.advanced.text')),
                            'orderClass' => $order_class,
                        ]
                    ),
                    $elements->style([ 'attrName' => 'title' ]),
                    $elements->style([ 'attrName' => 'label' ]),
                    $elements->style([ 'attrName' => 'field' ]),
                    $elements->style([ 'attrName' => 'button' ]),
                    CssStyle::style(
                        [
                            'selector'   => $order_class,
                            'attr'       => Attrs::map($attrs['css'] ?? []),
                            'cssFields'  => self::custom_css(),
                            'orderClass' => $order_class,
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function custom_css(): array
    {
        $type = WP_Block_Type_Registry::get_instance()->get_registered(self::NAME);

        return Attrs::map($type->customCssFields ?? []);
    }
}
