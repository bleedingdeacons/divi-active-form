<?php

/**
 * The Form Field Divi 5 module: one input of a Register Form.
 *
 * Its settings are read twice: here, to render the input (with a dropdown's options
 * fetched from its JSON source), and by RegisterFormModule, to build the schema that
 * the submission is checked against.
 *
 * Only loaded from the `divi_module_library_modules_dependency_tree` action.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Divi;

use DiviActiveForm\Form\FieldDefinition;
use DiviActiveForm\Form\Options\FieldOptions;
use DiviActiveForm\Render\FieldRenderer;
use DiviActiveForm\Support\Attrs;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Packages\Module\Layout\Components\Classnames;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

final class FormFieldModule implements DependencyInterface
{
    public const NAME = 'divi-active-form/form-field';

    private static ?FieldOptions $options = null;

    public function load(): void
    {
        ModuleRegistration::register_module(
            DIVI_ACTIVE_FORM_DIR . 'modules-json/form-field/',
            [
                'render_callback' => [ self::class, 'render_callback' ],
            ]
        );
    }

    /**
     * One FieldOptions per request, so a JSON source shared by several fields is read
     * once.
     */
    public static function options(): FieldOptions
    {
        return self::$options ??= new FieldOptions();
    }

    /**
     * @param mixed          $attrs    Module attributes (merged with defaults by Divi).
     * @param mixed          $content  Unused: a field has no children.
     * @param \WP_Block      $block    Parsed block.
     * @param ModuleElements $elements Divi's element renderer for this module.
     */
    public static function render_callback(mixed $attrs, mixed $content, \WP_Block $block, ModuleElements $elements): string
    {
        $attrs = Attrs::map($attrs);
        $field = FieldDefinition::fromAttrs($attrs);

        $id            = Attrs::string($block->parsed_block['id'] ?? '');
        $orderIndex    = $block->parsed_block['orderIndex'] ?? 0;
        $storeInstance = $block->parsed_block['storeInstance'] ?? null;
        $storeInstance = is_int($storeInstance) ? $storeInstance : null;
        $parent        = BlockParserStore::get_parent($id, $storeInstance);

        $html = FieldRenderer::render(
            $field,
            $field->type->hasChoices() ? self::options()->options($field) : [],
            'daf-' . Attrs::string($orderIndex) . '-' . $field->name
        );

        return Module::render(
            [
                'orderIndex'         => $orderIndex,
                'storeInstance'      => $storeInstance,
                'attrs'              => $attrs,
                'elements'           => $elements,
                'id'                 => $id,
                'name'               => $block->block_type->name,
                'moduleCategory'     => $block->block_type->category ?? 'child-module',
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
     * @param array<string, mixed> $args Divi classnames args.
     */
    public static function module_classnames(array $args): void
    {
        $classnames = $args['classnamesInstance'] ?? null;
        if (! $classnames instanceof Classnames) {
            return;
        }
        $attrs = Attrs::map($args['attrs'] ?? []);

        $classnames->add('daf-field-module', true);
        $classnames->add('daf-field-module--half', 'off' === Attrs::string(Attrs::desktop($attrs, 'fieldItem.advanced.fullwidth')));
        $classnames->add(ElementClassnames::classnames([ 'attrs' => Attrs::map(Attrs::at($attrs, 'module.decoration')) ]));
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
                ],
            ]
        );
    }
}
