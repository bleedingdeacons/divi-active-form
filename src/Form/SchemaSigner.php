<?php

/**
 * Seals a FormSchema into a token for a hidden input, and opens it again on submit.
 *
 * The token is the base64url JSON of the schema plus an HMAC over it. A token that has
 * been altered, or was signed with another site's key, does not open.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form;

use DiviActiveForm\Support\Attrs;

if (! defined('ABSPATH')) {
    exit;
}

final class SchemaSigner
{
    public function __construct(private readonly string $key)
    {
    }

    public static function fromSalts(): self
    {
        return new self(wp_salt('auth') . '|divi-active-form');
    }

    public function sign(FormSchema $schema): string
    {
        $payload = self::base64UrlEncode((string) wp_json_encode($schema->toArray()));

        return $payload . '.' . hash_hmac('sha256', $payload, $this->key);
    }

    public function verify(string $token): ?FormSchema
    {
        $parts = explode('.', $token);
        if (2 !== count($parts)) {
            return null;
        }
        [ $payload, $mac ] = $parts;

        if (! hash_equals(hash_hmac('sha256', $payload, $this->key), $mac)) {
            return null;
        }

        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        if (false === $json) {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? FormSchema::fromArray(Attrs::map($data)) : null;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
