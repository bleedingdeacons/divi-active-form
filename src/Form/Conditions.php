<?php

/**
 * When a field is visible, or enabled: a set of rules over the other fields' values.
 *
 * Rules are typed one per line in the Form Field's Conditional Logic settings:
 *
 *   size = large
 *   category != 12
 *   email is not empty
 *   agree is checked
 *
 * `is checked` / `is not checked` are `is not empty` / `is empty` under the names that
 * read naturally for a checkbox. Values compare as trimmed text, ignoring case. With no
 * rules the set always passes. Lines that cannot be read are dropped, so a typo makes a
 * rule disappear rather than hide a field for ever.
 *
 * The same rules run in the browser (assets/src/frontend/form-core.js), which receives
 * them already parsed, so the two cannot read a rule differently.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form;

use DiviActiveForm\Support\Attrs;

if (! defined('ABSPATH')) {
    exit;
}

final class Conditions
{
    public const OPERATORS = [ '=', '!=', 'empty', 'filled' ];

    /**
     * @param list<array{field: string, op: string, value: string}> $rules
     * @param bool                                                  $any   Pass when any rule holds, rather than all.
     */
    public function __construct(
        public readonly array $rules = [],
        public readonly bool $any = false,
    ) {
    }

    public static function parse(string $text, string $match = 'all'): self
    {
        $rules = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $rule = self::parseLine($line);
            if (null !== $rule) {
                $rules[] = $rule;
            }
        }

        return new self($rules, 'any' === strtolower(trim($match)));
    }

    /**
     * @return array{field: string, op: string, value: string}|null
     */
    private static function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ('' === $line) {
            return null;
        }

        if (preg_match('/^([a-z0-9_\-]+)\s+is\s+(not\s+)?(empty|checked)$/i', $line, $m)) {
            $empty = ( 'empty' === strtolower($m[3]) ) !== ( '' !== $m[2] );

            return [
                'field' => FieldDefinition::sanitizeName($m[1]),
                'op'    => $empty ? 'empty' : 'filled',
                'value' => '',
            ];
        }

        if (preg_match('/^([a-z0-9_\-]+)\s*(!=|=)\s*(.*)$/i', $line, $m)) {
            return [
                'field' => FieldDefinition::sanitizeName($m[1]),
                'op'    => $m[2],
                'value' => self::unquote(trim($m[3])),
            ];
        }

        return null;
    }

    private static function unquote(string $value): string
    {
        if (strlen($value) >= 2 && ( $value[0] === '"' || $value[0] === "'" ) && $value[-1] === $value[0]) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    public function isEmpty(): bool
    {
        return [] === $this->rules;
    }

    /**
     * @param array<string, string> $values Field name => value; a missing field counts as empty.
     */
    public function passes(array $values): bool
    {
        if ([] === $this->rules) {
            return true;
        }

        foreach ($this->rules as $rule) {
            $holds = self::holds($rule, trim($values[ $rule['field'] ] ?? ''));
            if ($this->any && $holds) {
                return true;
            }
            if (! $this->any && ! $holds) {
                return false;
            }
        }

        return ! $this->any;
    }

    /**
     * @param array{field: string, op: string, value: string} $rule
     */
    private static function holds(array $rule, string $actual): bool
    {
        return match ($rule['op']) {
            'empty'  => '' === $actual,
            'filled' => '' !== $actual,
            '!='     => self::lower($actual) !== self::lower(trim($rule['value'])),
            default  => self::lower($actual) === self::lower(trim($rule['value'])),
        };
    }

    /**
     * Lower case, by character where mbstring is available. WordPress polyfills only
     * some mb_* functions, and not this one.
     */
    private static function lower(string $text): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    /**
     * @return array{any: bool, rules: list<array{field: string, op: string, value: string}>}
     */
    public function toArray(): array
    {
        return [
            'any'   => $this->any,
            'rules' => $this->rules,
        ];
    }

    public static function fromArray(mixed $data): self
    {
        $data  = Attrs::map($data);
        $rules = [];
        foreach (is_array($data['rules'] ?? null) ? $data['rules'] : [] as $rule) {
            $rule  = Attrs::map($rule);
            $field = FieldDefinition::sanitizeName(Attrs::string($rule['field'] ?? null));
            $op    = Attrs::string($rule['op'] ?? null);
            if ('' === $field || ! in_array($op, self::OPERATORS, true)) {
                continue;
            }
            $rules[] = [
                'field' => $field,
                'op'    => $op,
                'value' => Attrs::string($rule['value'] ?? null),
            ];
        }

        return new self($rules, true === ( $data['any'] ?? false ));
    }
}
