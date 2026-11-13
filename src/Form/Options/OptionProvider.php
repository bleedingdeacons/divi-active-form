<?php

/**
 * The choices a dropdown field offers.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form\Options;

use DiviActiveForm\Form\FieldDefinition;

if (! defined('ABSPATH')) {
    exit;
}

interface OptionProvider
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(FieldDefinition $field): array;
}
