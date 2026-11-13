<?php

/**
 * What became of a submission, in a shape both the REST endpoint and the no-JavaScript
 * fallback can answer with.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form\Submission;

if (! defined('ABSPATH')) {
    exit;
}

final class SubmissionResult
{
    /**
     * @param array<string, string> $errors Field name => message.
     */
    private function __construct(
        public readonly bool $ok,
        public readonly int $status,
        public readonly string $message,
        public readonly array $errors = [],
        public readonly int $postId = 0,
        public readonly string $redirect = '',
    ) {
    }

    public static function created(int $postId, string $message, string $redirect = ''): self
    {
        return new self(true, 201, $message, [], $postId, $redirect);
    }

    /**
     * Looks like success to the sender, but nothing was saved (a filled-in honeypot).
     */
    public static function discarded(string $message, string $redirect = ''): self
    {
        return new self(true, 200, $message, [], 0, $redirect);
    }

    /**
     * @param array<string, string> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(false, 422, __('Please correct the highlighted fields.', 'divi-active-form'), $errors);
    }

    public static function failed(int $status, string $message): self
    {
        return new self(false, $status, $message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok'       => $this->ok,
            'message'  => $this->message,
            'errors'   => (object) $this->errors,
            'redirect' => $this->redirect,
        ];
    }
}
