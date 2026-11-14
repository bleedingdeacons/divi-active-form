<?php

/**
 * Front-end markup for the Divi Active Form around its fields.
 *
 * The form posts to admin-post.php, so it works without JavaScript; assets/build/form.js
 * takes over when it loads, sending the same fields to the REST endpoint and showing
 * errors beside each field without a page load.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Render;

use DiviActiveForm\Form\Submission\SubmissionHandler;

if (! defined('ABSPATH')) {
    exit;
}

final class FormRenderer
{
    public const ADMIN_POST_ACTION = 'divi_active_form_submit';

    /**
     * @param array{
     *     token: string,
     *     fields: string,
     *     title: string,
     *     submitLabel: string,
     *     restUrl: string,
     *     actionUrl: string,
     *     returnUrl: string,
     *     notice: string,
     *     noticeIsError: bool,
     * } $args
     */
    public static function render(array $args): string
    {
        $notice = '' !== $args['notice']
            ? sprintf(
                '<div class="daf-form__message%1$s" role="status" aria-live="polite">%2$s</div>',
                $args['noticeIsError'] ? ' daf-form__message--error' : ' daf-form__message--success',
                esc_html($args['notice'])
            )
            : '<div class="daf-form__message" role="status" aria-live="polite" hidden></div>';

        return sprintf(
            '%1$s<form class="daf-form" method="post" action="%2$s" data-daf-form="" data-rest="%3$s">'
                . '%4$s'
                . '<div class="daf-form__fields">%5$s</div>'
                . '<input type="hidden" name="%6$s" value="%7$s">'
                . '<input type="hidden" name="_daf_return" value="%8$s">'
                . '<div class="daf-form__trap" aria-hidden="true"><label>%9$s <input type="text" name="%10$s" value="" tabindex="-1" autocomplete="off"></label></div>'
                . '<div class="daf-form__footer"><button type="submit" class="et_pb_button daf-form__submit">%11$s</button></div>'
                . '</form>',
            '' !== $args['title'] ? sprintf('<h3 class="daf-form__title">%s</h3>', esc_html($args['title'])) : '',
            // admin-post.php's `action` goes in the URL, not a hidden input: an input named
            // "action" would shadow the form's own .action property for every script.
            esc_url(add_query_arg('action', self::ADMIN_POST_ACTION, $args['actionUrl'])),
            esc_url($args['restUrl']),
            $notice,
            $args['fields'],
            esc_attr(SubmissionHandler::SCHEMA_FIELD),
            esc_attr($args['token']),
            esc_url($args['returnUrl']),
            esc_html__('Leave this field empty', 'divi-active-form'),
            esc_attr(SubmissionHandler::HONEYPOT_FIELD),
            esc_html('' !== $args['submitLabel'] ? $args['submitLabel'] : __('Submit', 'divi-active-form'))
        );
    }
}
