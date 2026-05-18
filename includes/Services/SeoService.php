<?php

declare(strict_types=1);

namespace WpEditorialAbilities\Services;

final class SeoService
{
    public function optimizeSeoMetadata(array $input): array
    {
        $post_id = (int) $input['post_id'];
        $target = sanitize_key((string) ($input['target'] ?? 'auto'));
        $active = $this->detectActiveTargets($target);
        $written = [];
        $warnings = [];

        if (! $active) {
            $warnings[] = __('Neither Yoast SEO nor Rank Math appears to be active. No SEO metadata was written.', 'wp-editorial-abilities');
        }

        if (in_array('yoast', $active, true)) {
            $this->writeYoast($post_id, $input);
            $written[] = 'yoast';
        }

        if (in_array('rank_math', $active, true)) {
            $this->writeRankMath($post_id, $input);
            $written[] = 'rank_math';
        }

        return [
            'post_id' => $post_id,
            'requested_target' => $target,
            'written_targets' => $written,
            'warnings' => $warnings,
            'timestamp' => gmdate('c'),
        ];
    }

    private function detectActiveTargets(string $target): array
    {
        if ($target === 'both') {
            return ['yoast', 'rank_math'];
        }

        if ($target === 'yoast' || $target === 'rank_math') {
            return [$target];
        }

        $active = [];

        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) {
            $active[] = 'yoast';
        }

        if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
            $active[] = 'rank_math';
        }

        return $active;
    }

    private function writeYoast(int $post_id, array $input): void
    {
        $this->updateMeta($post_id, '_yoast_wpseo_title', $input['seo_title'] ?? null);
        $this->updateMeta($post_id, '_yoast_wpseo_metadesc', $input['meta_description'] ?? null);
        $this->updateMeta($post_id, '_yoast_wpseo_focuskw', $this->keywords($input));
        $this->updateMeta($post_id, '_yoast_wpseo_canonical', $input['canonical_url'] ?? null);
        $this->updateMeta($post_id, '_yoast_wpseo_opengraph-title', $input['open_graph_title'] ?? null);
        $this->updateMeta($post_id, '_yoast_wpseo_opengraph-description', $input['open_graph_description'] ?? null);
        $this->updateMeta($post_id, '_yoast_wpseo_opengraph-image', $input['open_graph_image'] ?? null);
        $this->updateMeta($post_id, '_yoast_wpseo_twitter-title', $input['twitter_title'] ?? null);
        $this->updateMeta($post_id, '_yoast_wpseo_twitter-description', $input['twitter_description'] ?? null);
        $this->updateMeta($post_id, '_yoast_wpseo_twitter-image', $input['twitter_image'] ?? null);
    }

    private function writeRankMath(int $post_id, array $input): void
    {
        $this->updateMeta($post_id, 'rank_math_title', $input['seo_title'] ?? null);
        $this->updateMeta($post_id, 'rank_math_description', $input['meta_description'] ?? null);
        $this->updateMeta($post_id, 'rank_math_focus_keyword', $this->keywords($input));
        $this->updateMeta($post_id, 'rank_math_canonical_url', $input['canonical_url'] ?? null);
        $this->updateMeta($post_id, 'rank_math_facebook_title', $input['open_graph_title'] ?? null);
        $this->updateMeta($post_id, 'rank_math_facebook_description', $input['open_graph_description'] ?? null);
        $this->updateMeta($post_id, 'rank_math_facebook_image', $input['open_graph_image'] ?? null);
        $this->updateMeta($post_id, 'rank_math_twitter_title', $input['twitter_title'] ?? null);
        $this->updateMeta($post_id, 'rank_math_twitter_description', $input['twitter_description'] ?? null);
        $this->updateMeta($post_id, 'rank_math_twitter_image', $input['twitter_image'] ?? null);
    }

    private function updateMeta(int $post_id, string $key, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        update_post_meta($post_id, $key, sanitize_text_field((string) $value));
    }

    private function keywords(array $input): string
    {
        if (empty($input['focus_keywords']) || ! is_array($input['focus_keywords'])) {
            return '';
        }

        return implode(', ', array_map('sanitize_text_field', array_map('strval', $input['focus_keywords'])));
    }
}
