<?php

/**
 * Turns a valid submission into a post: inserts it, saves each field, and announces it.
 *
 * Fields are saved with ACF's update_field() when ACF is active, so a field whose name
 * matches an ACF field (a post object, say) gets ACF's field reference as well as its
 * value; without ACF they are plain post meta under the same names.
 *
 * Two actions fire once everything is saved:
 *
 *   divi_active_form/after_insert_post ( $formId, $postId, $values )
 *   daf_after_insert_post ( $formId, $postId )  only when the form turns it on
 *
 * The second is a shorter form of the first, with just the form ID and post ID, for
 * listeners that need no more.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Form\Submission;

use DiviActiveForm\Form\FormSchema;
use DiviActiveForm\Form\Options\OptionProvider;
use DiviActiveForm\Form\TitleTemplate;

if (! defined('ABSPATH')) {
    exit;
}

final class PostWriter
{
    public function __construct(private readonly OptionProvider $options)
    {
    }

    /**
     * @param array<string, string> $values Validated values, by field name.
     * @return int The new post's ID, or 0 when it could not be created.
     */
    public function write(FormSchema $schema, array $values): int
    {
        $postId = wp_insert_post(
            [
                'post_type'   => $schema->postType,
                'post_status' => $schema->postStatus,
                'post_title'  => TitleTemplate::render($schema->titleTemplate, $this->displayValues($schema, $values), $this->fallbackTitle($schema)),
            ],
            true
        );

        if (is_wp_error($postId) || ! is_int($postId) || $postId <= 0) {
            return 0;
        }

        foreach ($values as $name => $value) {
            if ('' === $value) {
                continue;
            }
            if (function_exists('update_field')) {
                update_field($name, $value, $postId);
            } else {
                update_post_meta($postId, $name, $value);
            }
        }

        /**
         * Fires after a Divi Active Form submission has been saved as a post.
         *
         * @param string                $formId The form's Form ID setting.
         * @param int                   $postId The created post.
         * @param array<string, string> $values Submitted values, by field name.
         */
        do_action('divi_active_form/after_insert_post', $schema->formId, $postId, $values);

        if ($schema->fireLegacyHook) {
            do_action('daf_after_insert_post', $schema->formId, $postId);
        }

        return $postId;
    }

    /**
     * Values as a person would read them: a dropdown's option label rather than its value.
     *
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private function displayValues(FormSchema $schema, array $values): array
    {
        $display = $values;
        foreach ($schema->fields as $field) {
            if (! $field->type->hasChoices() || '' === ( $values[ $field->name ] ?? '' )) {
                continue;
            }
            foreach ($this->options->options($field) as $option) {
                if ($option['value'] === $values[ $field->name ]) {
                    $display[ $field->name ] = $option['label'];
                    break;
                }
            }
        }

        return $display;
    }

    private function fallbackTitle(FormSchema $schema): string
    {
        $name = '' !== $schema->formId ? $schema->formId : __('Form', 'divi-active-form');

        /* translators: 1: form ID, 2: date and time of the submission */
        return sprintf(__('%1$s submission, %2$s', 'divi-active-form'), $name, current_time('Y-m-d H:i'));
    }
}
