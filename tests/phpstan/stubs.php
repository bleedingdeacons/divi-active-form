<?php

/**
 * PHPStan stubs for the parts of Divi 5 and ACF that Divi Active Form calls.
 * Neither is installed in CI, and Divi publishes no stubs. Signatures are copied
 * from Divi 5.19.0 and ACF 6; only what this plugin uses.
 *
 * Read by PHPStan only (scanFiles in phpstan.neon.dist), never loaded at runtime.
 */

// phpcs:ignoreFile -- declarations in several namespaces, mirroring vendor code.

namespace ET\Builder\Framework\DependencyManagement\Interfaces {
    interface DependencyInterface
    {
        /** @return void */
        public function load();
    }
}

namespace ET\Builder\Framework\DependencyManagement {
    use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;

    class DependencyTree
    {
        public function add_dependency(DependencyInterface $dependency): void
        {
        }
    }
}

namespace ET\Builder\FrontEnd\BlockParser {
    class BlockParserBlock extends \WP_Block_Parser_Block
    {
        /** @var string */
        public $id;
    }

    class BlockParserStore
    {
        /**
         * @param string   $child_id
         * @param int|null $instance
         */
        public static function get_parent($child_id, $instance = null): ?BlockParserBlock
        {
            return null;
        }

        /**
         * @param string   $id
         * @param int|null $instance
         */
        public static function get($id, $instance = null): ?BlockParserBlock
        {
            return null;
        }
    }
}

namespace ET\Builder\FrontEnd\Module {
    class Style
    {
        /** @param array<string, mixed> $args */
        public static function add(array $args): void
        {
        }
    }
}

namespace ET\Builder\Packages\Module\Layout\Components {
    class Classnames
    {
        /** @param string|array<mixed> $classname */
        public function add($classname, bool $should_be_added = true): void
        {
        }
    }
}

namespace ET\Builder\Packages\Module\Layout\Components\ModuleElements {
    class ModuleElements
    {
        /**
         * @param array<string, mixed> $args
         * @return string|array<mixed>|null
         */
        public function style(array $args)
        {
            return null;
        }

        /** @param array<string, mixed> $args */
        public function style_components(array $args): ?string
        {
            return null;
        }
    }
}

namespace ET\Builder\Packages\Module {
    class Module
    {
        /** @param array<string, mixed> $args */
        public static function render(array $args): string
        {
            return '';
        }
    }
}

namespace ET\Builder\Packages\Module\Options\Css {
    class CssStyle
    {
        /**
         * @param array<string, mixed> $args
         * @return string|array<mixed>
         */
        public static function style(array $args)
        {
            return '';
        }
    }
}

namespace ET\Builder\Packages\Module\Options\Element {
    class ElementClassnames
    {
        /** @param array<string, mixed> $args */
        public static function classnames(array $args): string
        {
            return '';
        }
    }
}

namespace ET\Builder\Packages\Module\Options\Text {
    class TextClassnames
    {
        /**
         * @param array<string, mixed> $attr
         * @param array<string, mixed> $settings
         */
        public static function text_options_classnames(array $attr, array $settings = []): string
        {
            return '';
        }
    }

    class TextStyle
    {
        /**
         * @param array<string, mixed> $args
         * @return string|array<mixed>
         */
        public static function style(array $args)
        {
            return '';
        }
    }
}

namespace ET\Builder\Packages\ModuleLibrary {
    class ModuleRegistration
    {
        /** @param array<string, mixed> $args */
        public static function register_module(string $metadata_folder, array $args = []): ?\WP_Block_Type
        {
            return null;
        }

        /** @return array<string, mixed> */
        public static function get_default_attrs(string $module_name, string $attrs_type = 'default'): array
        {
            return [];
        }
    }
}

namespace ET\Builder\VisualBuilder\Assets {
    class PackageBuildManager
    {
        /** @param array<string, mixed> $params */
        public static function register_package_build($params): void
        {
        }
    }
}

namespace {
    /** Divi: whether the Visual Builder is loading. */
    function et_core_is_fb_enabled(): bool
    {
        return false;
    }

    /** Divi: whether Divi 5 is active. */
    function et_builder_d5_enabled(): bool
    {
        return false;
    }

    /**
     * ACF.
     *
     * @param string     $selector
     * @param mixed      $value
     * @param int|string $post_id
     * @return bool|int
     */
    function update_field($selector, $value, $post_id = false)
    {
        return true;
    }
}
