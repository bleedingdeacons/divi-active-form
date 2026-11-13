<?php

/**
 * Published 12 Step Meeting List groups (`tsml_group` posts), for JSON Dropdowns that
 * ask which group someone belongs to.
 *
 * @package DiviActiveForm
 */

declare(strict_types=1);

namespace DiviActiveForm\Groups;

if (! defined('ABSPATH')) {
    exit;
}

final class GroupRepository
{
    public const POST_TYPE = 'tsml_group';

    /**
     * @return list<array{value: string, label: string}> Post ID and title, by title.
     */
    public function all(): array
    {
        if (! post_type_exists(self::POST_TYPE)) {
            return [];
        }

        $posts = get_posts(
            [
                'post_type'        => self::POST_TYPE,
                'post_status'      => 'publish',
                'posts_per_page'   => -1,
                'orderby'          => 'title',
                'order'            => 'ASC',
                'suppress_filters' => false,
            ]
        );

        $groups = [];
        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }
            $label = trim(html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ('' === $label) {
                continue;
            }
            $groups[] = [
                'value' => (string) $post->ID,
                'label' => $label,
            ];
        }

        /**
         * Filters the groups the groups endpoint lists, e.g. to leave some out.
         *
         * @param list<array{value: string, label: string}> $groups
         */
        $filtered = apply_filters('divi_active_form/groups', $groups);

        return is_array($filtered) ? array_values(array_filter($filtered, [ self::class, 'isOption' ])) : $groups;
    }

    /**
     * @phpstan-assert-if-true array{value: string, label: string} $item
     */
    private static function isOption(mixed $item): bool
    {
        return is_array($item) && is_string($item['value'] ?? null) && is_string($item['label'] ?? null);
    }
}
