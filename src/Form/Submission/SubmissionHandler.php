<?php

/**
 * One submission, start to finish: open the signed schema, turn away bots and floods,
 * validate, and save.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form\Submission;

use DiviActiveForm\Form\FormSchema;
use DiviActiveForm\Form\Options\FieldOptions;
use DiviActiveForm\Form\Options\OptionProvider;
use DiviActiveForm\Form\SchemaSigner;

if (! defined('ABSPATH')) {
    exit;
}

final class SubmissionHandler
{
    /** Request parameter holding the signed schema. */
    public const SCHEMA_FIELD = '_daf_schema';

    /** Honeypot input: hidden from people, so anything in it came from a bot. */
    public const HONEYPOT_FIELD = '_daf_website';

    private const RATE_LIMIT  = 10;
    private const RATE_WINDOW = 10 * 60;

    public function __construct(
        private readonly SchemaSigner $signer,
        private readonly Validator $validator,
        private readonly PostWriter $writer,
    ) {
    }

    public static function create(?OptionProvider $options = null): self
    {
        $options ??= new FieldOptions();

        return new self(SchemaSigner::fromSalts(), new Validator($options), new PostWriter($options));
    }

    /**
     * @param array<string, mixed> $input     Unslashed request parameters.
     * @param string               $clientKey Identifies the sender for rate limiting (their IP address).
     */
    public function handle(array $input, string $clientKey): SubmissionResult
    {
        $token  = $input[ self::SCHEMA_FIELD ] ?? '';
        $schema = is_string($token) ? $this->signer->verify($token) : null;
        if (null === $schema) {
            return SubmissionResult::failed(400, __('This form has expired or was changed. Reload the page and try again.', 'divi-active-form'));
        }

        if ('' === $schema->postType || ! post_type_exists($schema->postType) || [] === $schema->fields) {
            return SubmissionResult::failed(500, __('This form is not set up correctly. Please let the site administrator know.', 'divi-active-form'));
        }

        $honeypot = $input[ self::HONEYPOT_FIELD ] ?? '';
        if (is_scalar($honeypot) && '' !== trim((string) $honeypot)) {
            return SubmissionResult::discarded(self::successMessage($schema), $schema->redirectUrl);
        }

        if ($this->overLimit($clientKey)) {
            return SubmissionResult::failed(429, __('Too many submissions. Please wait a few minutes and try again.', 'divi-active-form'));
        }

        $result = $this->validator->validate($schema, $input);
        if ([] !== $result['errors']) {
            return SubmissionResult::invalid($result['errors']);
        }

        $postId = $this->writer->write($schema, $result['values']);
        if (0 === $postId) {
            return SubmissionResult::failed(500, __('Your submission could not be saved. Please try again later.', 'divi-active-form'));
        }

        return SubmissionResult::created($postId, self::successMessage($schema), $schema->redirectUrl);
    }

    private static function successMessage(FormSchema $schema): string
    {
        return '' !== $schema->successMessage
            ? $schema->successMessage
            : __('Thank you. Your submission has been received.', 'divi-active-form');
    }

    /**
     * Counts this attempt and says whether the sender is over the limit
     * (filter: `divi_active_form/rate_limit`, submissions per ten minutes; 0 turns it off).
     */
    private function overLimit(string $clientKey): bool
    {
        $limit = (int) apply_filters('divi_active_form/rate_limit', self::RATE_LIMIT);
        if ($limit <= 0 || '' === $clientKey) {
            return false;
        }

        $key   = 'daf_rate_' . md5($clientKey);
        $stored = get_transient($key);
        $count  = is_numeric($stored) ? (int) $stored : 0;
        if ($count >= $limit) {
            return true;
        }
        set_transient($key, $count + 1, self::RATE_WINDOW);

        return false;
    }
}
