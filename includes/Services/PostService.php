<?php

declare(strict_types=1);

namespace WpEditorialAbilities\Services;

use WP_Error;
use WP_Post;

final class PostService
{
    public function createEditorialDraft(array $input): array|WP_Error
    {
        $post_id = wp_insert_post([
            'post_author' => $this->resolveAuthor($input),
            'post_title' => sanitize_text_field((string) $input['title']),
            'post_content' => wp_kses_post((string) $input['content']),
            'post_excerpt' => isset($input['excerpt']) ? sanitize_textarea_field((string) $input['excerpt']) : '',
            'post_status' => 'draft',
            'post_type' => 'post',
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $this->setTerms($post_id, $input);

        if (! empty($input['featured_media_id'])) {
            set_post_thumbnail($post_id, (int) $input['featured_media_id']);
        }

        if (! empty($input['media_ids']) && is_array($input['media_ids'])) {
            foreach ($input['media_ids'] as $media_id) {
                wp_update_post([
                    'ID' => (int) $media_id,
                    'post_parent' => $post_id,
                ]);
            }
        }

        if (! empty($input['seo']) && is_array($input['seo'])) {
            (new SeoService())->optimizeSeoMetadata(array_merge($input['seo'], ['post_id' => $post_id]));
        }

        return $this->postResponse($post_id);
    }

    public function updateEditorialDraft(array $input): array|WP_Error
    {
        $post_id = (int) $input['post_id'];
        $post = get_post($post_id);

        if (! $post instanceof WP_Post) {
            return new WP_Error('wpea_post_not_found', __('Post not found.', 'wp-editorial-abilities'));
        }

        if (! in_array($post->post_status, ['draft', 'pending', 'future'], true)) {
            return new WP_Error('wpea_not_draft', __('Only draft, pending, or scheduled posts can be updated with this ability.', 'wp-editorial-abilities'));
        }

        $update = ['ID' => $post_id];

        if (isset($input['title'])) {
            $update['post_title'] = sanitize_text_field((string) $input['title']);
        }

        if (isset($input['content'])) {
            $update['post_content'] = wp_kses_post((string) $input['content']);
        }

        if (isset($input['excerpt'])) {
            $update['post_excerpt'] = sanitize_textarea_field((string) $input['excerpt']);
        }

        if (! empty($input['author_id'])) {
            $author = get_userdata((int) $input['author_id']);
            if ($author && user_can($author, 'edit_posts')) {
                $update['post_author'] = (int) $author->ID;
            }
        }

        $result = wp_update_post($update, true);

        if (is_wp_error($result)) {
            return $result;
        }

        $this->setTerms($post_id, $input);

        return $this->postResponse($post_id);
    }

    public function suggestInternalLinks(array $input): array
    {
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => min(10, max(1, (int) ($input['limit'] ?? 5))),
            's' => sanitize_text_field((string) $input['query']),
        ];

        if (! empty($input['category'])) {
            $category = $this->resolveCategory((string) $input['category']);
            if ($category) {
                $args['cat'] = $category;
            }
        }

        if (! empty($input['exclude_post_id'])) {
            $args['post__not_in'] = [(int) $input['exclude_post_id']];
        }

        $posts = get_posts($args);

        return array_map(static function (WP_Post $post): array {
            return [
                'post_id' => $post->ID,
                'title' => html_entity_decode(get_the_title($post), ENT_QUOTES),
                'excerpt' => wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 28),
                'permalink' => get_permalink($post),
                'date_gmt' => get_gmt_from_date($post->post_date),
            ];
        }, $posts);
    }

    public function schedulePost(array $input): array|WP_Error
    {
        if (empty($input['confirm_publish'])) {
            return new WP_Error('wpea_publish_not_confirmed', __('Scheduling requires confirm_publish=true.', 'wp-editorial-abilities'));
        }

        $post_id = (int) $input['post_id'];
        $date_gmt = sanitize_text_field((string) $input['date_gmt']);
        $date_local = get_date_from_gmt($date_gmt);

        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => 'future',
            'post_date' => $date_local,
            'post_date_gmt' => $date_gmt,
        ], true);

        if (is_wp_error($result)) {
            return $result;
        }

        return $this->postResponse($post_id);
    }

    public function publishPost(array $input): array|WP_Error
    {
        if (empty($input['confirm_publish'])) {
            return new WP_Error('wpea_publish_not_confirmed', __('Publishing requires confirm_publish=true.', 'wp-editorial-abilities'));
        }

        $post_id = (int) $input['post_id'];
        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($result)) {
            return $result;
        }

        return $this->postResponse($post_id);
    }

    public function postResponse(int $post_id): array
    {
        $post = get_post($post_id);

        return [
            'post_id' => $post_id,
            'status' => $post ? $post->post_status : '',
            'title' => $post ? html_entity_decode(get_the_title($post), ENT_QUOTES) : '',
            'edit_url' => get_edit_post_link($post_id, 'raw') ?: '',
            'preview_url' => get_preview_post_link($post_id) ?: '',
            'permalink' => get_permalink($post_id) ?: '',
            'actor' => get_current_user_id(),
            'timestamp' => gmdate('c'),
        ];
    }

    private function setTerms(int $post_id, array $input): void
    {
        if (! empty($input['categories']) && is_array($input['categories'])) {
            $category_ids = array_values(array_filter(array_map(
                fn ($category): int => $this->resolveCategory((string) $category),
                $input['categories']
            )));

            if ($category_ids) {
                wp_set_post_categories($post_id, $category_ids, false);
            }
        }

        if (! empty($input['tags']) && is_array($input['tags'])) {
            $tags = array_map('sanitize_text_field', array_map('strval', $input['tags']));
            wp_set_post_tags($post_id, $tags, false);
        }
    }

    private function resolveAuthor(array $input): int
    {
        $current = get_current_user_id();

        if (empty($input['author_id'])) {
            return $current;
        }

        $author = get_userdata((int) $input['author_id']);

        if ($author && user_can($author, 'edit_posts')) {
            return (int) $author->ID;
        }

        return $current;
    }

    private function resolveCategory(string $category): int
    {
        if (is_numeric($category)) {
            return (int) $category;
        }

        $category = sanitize_text_field($category);
        $term = get_category_by_slug($category);

        if (! $term) {
            $term = get_term_by('name', $category, 'category');
        }

        return $term ? (int) $term->term_id : 0;
    }
}
