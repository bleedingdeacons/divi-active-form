<?php

/**
 * REST routes, under divi-active-form/v1:
 *
 *   POST /submit    a Register Form submission (public; what form.js sends)
 *   GET  /example-options
 *                   a sample JSON source for dropdowns (public; ?shape=nested for the
 *                   nested form). See ExampleOptions.
 *   GET  /options   a JSON source's options as the form would show them (editors only;
 *                   the Visual Builder's preview)
 *
 * Plus the admin-post.php handler the form falls back to without JavaScript.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Rest;

use DiviActiveForm\Form\FieldDefinition;
use DiviActiveForm\Form\FieldType;
use DiviActiveForm\Form\Options\ExampleOptions;
use DiviActiveForm\Form\Options\FieldOptions;
use DiviActiveForm\Form\Submission\SubmissionHandler;
use DiviActiveForm\Form\Submission\SubmissionResult;
use DiviActiveForm\Render\FormRenderer;
use DiviActiveForm\Support\Attrs;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

final class FormController
{
    public const NAMESPACE = 'divi-active-form/v1';

    public function register(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/submit',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'submit' ],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/example-options',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'exampleOptions' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'shape' => [
                        'type' => 'string',
                        'enum' => [ 'flat', 'nested' ],
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/options',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'options' ],
                'permission_callback' => static fn (): bool => current_user_can('edit_posts'),
                'args'                => [
                    'source'    => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                    'itemsPath' => [ 'type' => 'string' ],
                    'valueKey'  => [ 'type' => 'string' ],
                    'labelKey'  => [ 'type' => 'string' ],
                ],
            ]
        );
    }

    public function submit(WP_REST_Request $request): WP_REST_Response
    {
        $result = SubmissionHandler::create()->handle(Attrs::map($request->get_params()), self::clientKey());

        return new WP_REST_Response($result->toArray(), $result->status);
    }

    public function exampleOptions(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(ExampleOptions::shaped(Attrs::string($request->get_param('shape'))), 200);
    }

    public function options(WP_REST_Request $request): WP_REST_Response
    {
        $string = static fn (string $key, string $default = ''): string => trim(Attrs::string($request->get_param($key), $default));

        $field = new FieldDefinition(
            name: 'preview',
            label: '',
            type: FieldType::Json,
            source: $string('source'),
            itemsPath: $string('itemsPath'),
            valueKey: '' !== $string('valueKey') ? $string('valueKey') : 'value',
            labelKey: '' !== $string('labelKey') ? $string('labelKey') : 'label',
        );

        $sources = new FieldOptions();

        return new WP_REST_Response(
            [
                'options'  => $sources->options($field),
                'readable' => null !== $sources->document($field->source),
            ],
            200
        );
    }

    /**
     * admin-post.php?action=divi_active_form_submit, for browsers without JavaScript.
     * Redirects back to the form's page with the outcome in the query string.
     */
    public function handleAdminPost(): void
    {
        // A public form: there is no nonce to check, by design (pages are cached, and
        // visitors are not signed in). The signed schema and the honeypot stand in.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $input  = Attrs::map(wp_unslash($_POST));
        $result = SubmissionHandler::create()->handle($input, self::clientKey());

        $return = wp_validate_redirect(Attrs::string($input['_daf_return'] ?? ''), home_url('/'));
        $target = $result->ok && '' !== $result->redirect
            ? wp_validate_redirect($result->redirect, $return)
            : add_query_arg(self::noticeArgs($result), $return);

        wp_safe_redirect($target, 303);
        exit;
    }

    /**
     * @return array<string, string>
     */
    private static function noticeArgs(SubmissionResult $result): array
    {
        $message = $result->message;
        if (! $result->ok && [] !== $result->errors) {
            $message = implode(' ', $result->errors);
        }

        return [
            'daf_status'  => $result->ok ? 'sent' : 'error',
            'daf_message' => rawurlencode($message),
        ];
    }

    /**
     * The sender's address, for rate limiting only. REMOTE_ADDR rather than any
     * forwarded-for header, which the sender controls.
     */
    private static function clientKey(): string
    {
        return isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';
    }
}
