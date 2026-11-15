<?php

/**
 * The kinds of field a Form Field module can be.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form;

if (! defined('ABSPATH')) {
    exit;
}

enum FieldType: string
{
    case Text     = 'text';
    case Email    = 'email';
    case Tel      = 'tel';
    case Textarea = 'textarea';
    case Select   = 'select';
    /** A Dropdown whose options come from a JSON source (Options From: JSON Source). */
    case Json     = 'json';
    case Acf      = 'acf';
    case Checkbox = 'checkbox';

    /**
     * Unknown or empty values fall back to a plain text input.
     */
    public static function parse(mixed $value): self
    {
        return is_string($value) ? ( self::tryFrom($value) ?? self::Text ) : self::Text;
    }

    /**
     * Whether the field offers a fixed set of choices that a submission must be one of.
     */
    public function hasChoices(): bool
    {
        return $this === self::Select || $this === self::Json || $this === self::Acf;
    }
}
