<?php

declare(strict_types=1);

namespace WpEditorialAbilities\Services;

use WP_Error;
use WP_Post;

final class MediaService
{
    public function getMediaLibraryItems(array $input): array
    {
        $query = new \WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => sanitize_text_field((string) ($input['mime_type'] ?? 'image')),
            's' => isset($input['search']) ? sanitize_text_field((string) $input['search']) : '',
            'paged' => max(1, (int) ($input['page'] ?? 1)),
            'posts_per_page' => min(50, max(1, (int) ($input['per_page'] ?? 20))),
        ]);

        return array_map([$this, 'mediaResponse'], $query->posts);
    }

    public function attachImagesToDraft(array $input): array|WP_Error
    {
        $post_id = (int) $input['post_id'];
        $attached = [];

        foreach (($input['media_ids'] ?? []) as $media_id) {
            $attachment_id = (int) $media_id;
            $result = wp_update_post([
                'ID' => $attachment_id,
                'post_parent' => $post_id,
            ], true);

            if (is_wp_error($result)) {
                return $result;
            }

            $attachment = get_post($attachment_id);
            if ($attachment instanceof WP_Post) {
                $attached[] = $this->mediaResponse($attachment);
            }
        }

        foreach (($input['image_urls'] ?? []) as $url) {
            $attachment_id = $this->sideloadImage((string) $url, $post_id);
            if (is_wp_error($attachment_id)) {
                return $attachment_id;
            }

            $attachment = get_post($attachment_id);
            if ($attachment instanceof WP_Post) {
                $attached[] = $this->mediaResponse($attachment);
            }
        }

        return $attached;
    }

    public function uploadMediaBase64(array $input): array|WP_Error
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $filename = isset($input['filename']) ? sanitize_file_name((string) $input['filename']) : '';
        $data = isset($input['base64_data']) ? (string) $input['base64_data'] : '';
        $description = isset($input['description']) ? sanitize_text_field((string) $input['description']) : '';

        if ($filename === '' || $data === '') {
            return new WP_Error('wpea_missing_input', __('filename and base64_data are required.', 'wp-editorial-abilities'));
        }

        if (str_contains($data, ',')) {
            $data = substr($data, strpos($data, ',') + 1);
        }

        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            return new WP_Error('wpea_invalid_base64', __('Could not decode the base64 payload.', 'wp-editorial-abilities'));
        }

        $upload = wp_upload_bits($filename, null, $decoded);

        if (! empty($upload['error'])) {
            return new WP_Error('wpea_upload_failed', (string) $upload['error']);
        }

        $file = $upload['file'];
        $filetype = wp_check_filetype(basename($file), null);

        $attachment_id = wp_insert_attachment([
            'guid' => $upload['url'],
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => $description,
            'post_excerpt' => $description,
            'post_status' => 'inherit',
        ], $file, 0, true);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $file);
        wp_update_attachment_metadata($attachment_id, $metadata);

        if ($description !== '') {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $description);
        }

        $attachment = get_post($attachment_id);

        if (! $attachment instanceof WP_Post) {
            return new WP_Error('wpea_attachment_missing', __('Attachment could not be loaded after upload.', 'wp-editorial-abilities'));
        }

        return $this->mediaResponse($attachment);
    }

    public function setFeaturedImage(array $input): array|WP_Error
    {
        $post_id = (int) $input['post_id'];
        $media_id = (int) $input['media_id'];

        if (get_post_type($media_id) !== 'attachment') {
            return new WP_Error('wpea_invalid_attachment', __('Featured image must be an attachment.', 'wp-editorial-abilities'));
        }

        $result = set_post_thumbnail($post_id, $media_id);

        if (! $result) {
            return new WP_Error('wpea_featured_image_failed', __('Unable to set featured image.', 'wp-editorial-abilities'));
        }

        return (new PostService())->postResponse($post_id);
    }

    private function sideloadImage(string $url, int $post_id): int|WP_Error
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image(esc_url_raw($url), $post_id, null, 'id');

        return is_wp_error($attachment_id) ? $attachment_id : (int) $attachment_id;
    }

    private function mediaResponse(WP_Post $post): array
    {
        return [
            'media_id' => $post->ID,
            'title' => html_entity_decode(get_the_title($post), ENT_QUOTES),
            'mime_type' => $post->post_mime_type,
            'url' => wp_get_attachment_url($post->ID) ?: '',
            'alt' => get_post_meta($post->ID, '_wp_attachment_image_alt', true) ?: '',
            'parent' => (int) $post->post_parent,
        ];
    }
}
