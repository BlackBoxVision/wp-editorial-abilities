<?php

declare(strict_types=1);

namespace WpEditorialAbilities\Services;

use WP_Post;
use WP_Term;

final class EditorialPatternService
{
    public function listCategories(array $input): array
    {
        $terms = get_categories([
            'hide_empty' => (bool) ($input['hide_empty'] ?? false),
        ]);

        return array_map([$this, 'termResponse'], $terms);
    }

    public function listTags(array $input): array
    {
        $terms = get_tags([
            'hide_empty' => false,
            'search' => isset($input['search']) ? sanitize_text_field((string) $input['search']) : '',
            'number' => min(100, max(1, (int) ($input['number'] ?? 50))),
        ]);

        return array_map([$this, 'termResponse'], $terms);
    }

    public function getRecentPostsByCategory(array $input): array
    {
        $posts = $this->categoryPosts($input);

        return array_map([$this, 'postSample'], $posts);
    }

    public function getEditorialPatterns(array $input): array
    {
        $posts = $this->categoryPosts($input);
        $samples = array_map([$this, 'postSample'], $posts);
        $word_counts = array_map(static fn (array $sample): int => $sample['word_count'], $samples);
        $heading_counts = array_map(static fn (array $sample): int => count($sample['headings']), $samples);

        return [
            'category' => sanitize_text_field((string) ($input['category'] ?? '')),
            'sample_count' => count($samples),
            'average_word_count' => $word_counts ? (int) round(array_sum($word_counts) / count($word_counts)) : 0,
            'average_heading_count' => $heading_counts ? round(array_sum($heading_counts) / count($heading_counts), 1) : 0,
            'common_structure' => [
                'headline_examples' => array_column($samples, 'title'),
                'opening_examples' => array_slice(array_column($samples, 'opening'), 0, 5),
                'heading_examples' => $this->flattenHeadings($samples),
                'link_density_hint' => $this->linkDensityHint($samples),
            ],
            'samples' => $samples,
        ];
    }

    private function categoryPosts(array $input): array
    {
        $args = [
            'post_type' => 'post',
            'post_status' => sanitize_key((string) ($input['status'] ?? 'publish')),
            'posts_per_page' => min(20, max(1, (int) ($input['limit'] ?? 5))),
        ];

        if (! empty($input['category'])) {
            $category_id = $this->resolveCategory((string) $input['category']);
            if ($category_id > 0) {
                $args['cat'] = $category_id;
            }
        }

        return get_posts($args);
    }

    private function postSample(WP_Post $post): array
    {
        $plain = trim(wp_strip_all_tags($post->post_content));

        return [
            'post_id' => $post->ID,
            'title' => html_entity_decode(get_the_title($post), ENT_QUOTES),
            'status' => $post->post_status,
            'permalink' => get_permalink($post) ?: '',
            'date_gmt' => get_gmt_from_date($post->post_date),
            'opening' => wp_trim_words($plain, 45),
            'word_count' => str_word_count($plain),
            'headings' => $this->extractHeadings($post->post_content),
            'link_count' => substr_count(strtolower($post->post_content), '<a '),
        ];
    }

    private function extractHeadings(string $content): array
    {
        preg_match_all('/<h[2-4][^>]*>(.*?)<\/h[2-4]>/i', $content, $matches);

        return array_values(array_filter(array_map(
            static fn (string $heading): string => trim(wp_strip_all_tags($heading)),
            $matches[1] ?? []
        )));
    }

    private function flattenHeadings(array $samples): array
    {
        $headings = [];

        foreach ($samples as $sample) {
            foreach (($sample['headings'] ?? []) as $heading) {
                $headings[] = $heading;
            }
        }

        return array_values(array_filter($headings));
    }

    private function linkDensityHint(array $samples): string
    {
        if (! $samples) {
            return 'No samples available.';
        }

        $links = array_sum(array_column($samples, 'link_count'));
        $words = max(1, array_sum(array_column($samples, 'word_count')));

        return sprintf(
            '%s links per 1,000 words across the sampled posts.',
            round(($links / $words) * 1000, 1)
        );
    }

    private function termResponse(WP_Term $term): array
    {
        return [
            'term_id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'taxonomy' => $term->taxonomy,
            'parent' => (int) $term->parent,
            'count' => (int) $term->count,
        ];
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
