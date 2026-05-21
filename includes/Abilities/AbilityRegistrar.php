<?php

declare(strict_types=1);

namespace WpEditorialAbilities\Abilities;

use WpEditorialAbilities\Services\EditorialPatternService;
use WpEditorialAbilities\Services\MediaService;
use WpEditorialAbilities\Services\PostService;
use WpEditorialAbilities\Services\SeoService;
use WpEditorialAbilities\Services\UserService;

final class AbilityRegistrar
{
    private PostService $posts;
    private MediaService $media;
    private SeoService $seo;
    private EditorialPatternService $patterns;
    private UserService $users;

    public function __construct()
    {
        $this->posts = new PostService();
        $this->media = new MediaService();
        $this->seo = new SeoService();
        $this->patterns = new EditorialPatternService();
        $this->users = new UserService();
    }

    public function registerHooks(): void
    {
        add_action('wp_abilities_api_categories_init', [$this, 'registerCategories']);
        add_action('wp_abilities_api_init', [$this, 'registerAbilities']);
    }

    public function registerCategories(): void
    {
        wp_register_ability_category('editorial-read', [
            'label' => __('Editorial Read', 'wp-editorial-abilities'),
            'description' => __('Read-only editorial context for planning posts and understanding site structure.', 'wp-editorial-abilities'),
        ]);

        wp_register_ability_category('editorial-write', [
            'label' => __('Editorial Write', 'wp-editorial-abilities'),
            'description' => __('Draft, media, SEO, scheduling, and publishing workflows for editorial teams.', 'wp-editorial-abilities'),
        ]);
    }

    public function registerAbilities(): void
    {
        $this->registerReadAbilities();
        $this->registerWriteAbilities();
    }

    private function registerReadAbilities(): void
    {
        $this->ability('list-authors', [
            'label' => __('List Authors', 'wp-editorial-abilities'),
            'description' => __('List WordPress users who can be assigned as post authors (users with the edit_posts capability). Use this to let the editor choose an author for a draft.', 'wp-editorial-abilities'),
            'category' => 'editorial-read',
            'input_schema' => $this->objectSchema(),
            'output_schema' => $this->arrayOfObjectsSchema(),
            'execute_callback' => [$this->users, 'listAuthors'],
            'permission_callback' => [$this, 'canRead'],
            'meta' => $this->publicMeta(['readonly' => true, 'destructive' => false, 'idempotent' => true]),
        ]);

        $this->ability('list-categories', [
            'label' => __('List Categories', 'wp-editorial-abilities'),
            'description' => __('List public post categories with IDs, names, slugs, parents, and counts.', 'wp-editorial-abilities'),
            'category' => 'editorial-read',
            'input_schema' => $this->objectSchema([
                'hide_empty' => ['type' => 'boolean', 'default' => false],
            ]),
            'output_schema' => $this->arrayOfObjectsSchema(),
            'execute_callback' => [$this->patterns, 'listCategories'],
            'permission_callback' => [$this, 'canRead'],
            'meta' => $this->publicMeta(['readonly' => true, 'destructive' => false, 'idempotent' => true]),
        ]);

        $this->ability('list-tags', [
            'label' => __('List Tags', 'wp-editorial-abilities'),
            'description' => __('List post tags with IDs, names, slugs, and counts.', 'wp-editorial-abilities'),
            'category' => 'editorial-read',
            'input_schema' => $this->objectSchema([
                'search' => ['type' => 'string'],
                'number' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
            ]),
            'output_schema' => $this->arrayOfObjectsSchema(),
            'execute_callback' => [$this->patterns, 'listTags'],
            'permission_callback' => [$this, 'canRead'],
            'meta' => $this->publicMeta(['readonly' => true, 'destructive' => false, 'idempotent' => true]),
        ]);

        $this->ability('get-recent-posts-by-category', [
            'label' => __('Get Recent Posts by Category', 'wp-editorial-abilities'),
            'description' => __('Return recent posts for a category to support editorial planning and style analysis.', 'wp-editorial-abilities'),
            'category' => 'editorial-read',
            'input_schema' => $this->objectSchema([
                'category' => ['type' => 'string', 'description' => 'Category slug, name, or ID.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 5],
                'status' => ['type' => 'string', 'enum' => ['publish', 'draft', 'pending', 'future'], 'default' => 'publish'],
            ]),
            'output_schema' => $this->arrayOfObjectsSchema(),
            'execute_callback' => [$this->patterns, 'getRecentPostsByCategory'],
            'permission_callback' => [$this, 'canRead'],
            'meta' => $this->publicMeta(['readonly' => true, 'destructive' => false, 'idempotent' => true]),
        ]);

        $this->ability('get-post-editorial-patterns', [
            'label' => __('Get Post Editorial Patterns', 'wp-editorial-abilities'),
            'description' => __('Analyze recent posts in a category and return reusable structural patterns for drafting new editorial notes.', 'wp-editorial-abilities'),
            'category' => 'editorial-read',
            'input_schema' => $this->objectSchema([
                'category' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 3, 'maximum' => 20, 'default' => 8],
            ]),
            'output_schema' => $this->objectSchema(),
            'execute_callback' => [$this->patterns, 'getEditorialPatterns'],
            'permission_callback' => [$this, 'canRead'],
            'meta' => $this->publicMeta(['readonly' => true, 'destructive' => false, 'idempotent' => true]),
        ]);

        $this->ability('get-media-library-items', [
            'label' => __('Get Media Library Items', 'wp-editorial-abilities'),
            'description' => __('Search the WordPress media library for images that can be attached to editorial drafts.', 'wp-editorial-abilities'),
            'category' => 'editorial-read',
            'input_schema' => $this->objectSchema([
                'search' => ['type' => 'string'],
                'mime_type' => ['type' => 'string', 'default' => 'image'],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
            ]),
            'output_schema' => $this->arrayOfObjectsSchema(),
            'execute_callback' => [$this->media, 'getMediaLibraryItems'],
            'permission_callback' => [$this, 'canRead'],
            'meta' => $this->publicMeta(['readonly' => true, 'destructive' => false, 'idempotent' => true]),
        ]);
    }

    private function registerWriteAbilities(): void
    {
        $this->ability('create-editorial-draft', [
            'label' => __('Create Editorial Draft', 'wp-editorial-abilities'),
            'description' => __('Create a draft post from editorial copy, links, categories, tags, images, and optional SEO metadata.', 'wp-editorial-abilities'),
            'category' => 'editorial-write',
            'input_schema' => $this->draftInputSchema(),
            'output_schema' => $this->postOutputSchema(),
            'execute_callback' => [$this->posts, 'createEditorialDraft'],
            'permission_callback' => [$this, 'canCreatePosts'],
            'meta' => $this->publicMeta(['readonly' => false, 'destructive' => false, 'idempotent' => false]),
        ]);

        $this->ability('update-editorial-draft', [
            'label' => __('Update Editorial Draft', 'wp-editorial-abilities'),
            'description' => __('Update an existing editable draft or pending post without publishing it.', 'wp-editorial-abilities'),
            'category' => 'editorial-write',
            'input_schema' => $this->objectSchema([
                'post_id' => ['type' => 'integer', 'required' => true],
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'excerpt' => ['type' => 'string'],
                'author_id' => ['type' => 'integer', 'description' => 'User ID to reassign as post author. Use list-authors first.'],
                'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            ], ['post_id']),
            'output_schema' => $this->postOutputSchema(),
            'execute_callback' => [$this->posts, 'updateEditorialDraft'],
            'permission_callback' => [$this, 'canEditInputPost'],
            'meta' => $this->publicMeta(['readonly' => false, 'destructive' => false, 'idempotent' => false]),
        ]);

        $this->ability('attach-images-to-draft', [
            'label' => __('Attach Images to Draft', 'wp-editorial-abilities'),
            'description' => __('Attach existing media IDs or sideload image URLs to an editable post.', 'wp-editorial-abilities'),
            'category' => 'editorial-write',
            'input_schema' => $this->objectSchema([
                'post_id' => ['type' => 'integer', 'required' => true],
                'media_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'image_urls' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uri']],
            ], ['post_id']),
            'output_schema' => $this->arrayOfObjectsSchema(),
            'execute_callback' => [$this->media, 'attachImagesToDraft'],
            'permission_callback' => [$this, 'canEditInputPost'],
            'meta' => $this->publicMeta(['readonly' => false, 'destructive' => false, 'idempotent' => false]),
        ]);

        $this->ability('set-featured-image', [
            'label' => __('Set Featured Image', 'wp-editorial-abilities'),
            'description' => __('Set an existing attachment as the featured image for an editable post.', 'wp-editorial-abilities'),
            'category' => 'editorial-write',
            'input_schema' => $this->objectSchema([
                'post_id' => ['type' => 'integer', 'required' => true],
                'media_id' => ['type' => 'integer', 'required' => true],
            ], ['post_id', 'media_id']),
            'output_schema' => $this->postOutputSchema(),
            'execute_callback' => [$this->media, 'setFeaturedImage'],
            'permission_callback' => [$this, 'canEditInputPost'],
            'meta' => $this->publicMeta(['readonly' => false, 'destructive' => false, 'idempotent' => true]),
        ]);

        $this->ability('upload-media-base64', [
            'label' => __('Upload Media From Base64', 'wp-editorial-abilities'),
            'description' => __('Upload a file (typically an image the editor shared directly in chat) to the media library from a base64-encoded payload. Returns the attachment ID and URL so it can be used as a featured image or attached to a draft.', 'wp-editorial-abilities'),
            'category' => 'editorial-write',
            'input_schema' => $this->objectSchema([
                'filename' => ['type' => 'string', 'required' => true, 'description' => 'Filename including extension, e.g. cover.png.'],
                'base64_data' => ['type' => 'string', 'required' => true, 'description' => 'Base64-encoded file contents. May include the data:mime;base64, prefix.'],
                'description' => ['type' => 'string', 'description' => 'Optional caption / alt text.'],
            ], ['filename', 'base64_data']),
            'output_schema' => $this->objectSchema(),
            'execute_callback' => [$this->media, 'uploadMediaBase64'],
            'permission_callback' => [$this, 'canUploadFiles'],
            'meta' => $this->publicMeta(['readonly' => false, 'destructive' => false, 'idempotent' => false]),
        ]);

        $this->ability('suggest-internal-links', [
            'label' => __('Suggest Internal Links', 'wp-editorial-abilities'),
            'description' => __('Suggest existing posts to link from a draft based on query text and category.', 'wp-editorial-abilities'),
            'category' => 'editorial-write',
            'input_schema' => $this->objectSchema([
                'query' => ['type' => 'string', 'required' => true],
                'category' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 5],
                'exclude_post_id' => ['type' => 'integer'],
            ], ['query']),
            'output_schema' => $this->arrayOfObjectsSchema(),
            'execute_callback' => [$this->posts, 'suggestInternalLinks'],
            'permission_callback' => [$this, 'canRead'],
            'meta' => $this->publicMeta(['readonly' => true, 'destructive' => false, 'idempotent' => true]),
        ]);

        $this->ability('optimize-seo-metadata', [
            'label' => __('Optimize SEO Metadata', 'wp-editorial-abilities'),
            'description' => __('Write Yoast and/or Rank Math SEO metadata for an editable post.', 'wp-editorial-abilities'),
            'category' => 'editorial-write',
            'input_schema' => $this->seoInputSchema(),
            'output_schema' => $this->objectSchema(),
            'execute_callback' => [$this->seo, 'optimizeSeoMetadata'],
            'permission_callback' => [$this, 'canEditInputPost'],
            'meta' => $this->publicMeta(['readonly' => false, 'destructive' => false, 'idempotent' => true]),
        ]);

        $this->ability('schedule-post', [
            'label' => __('Schedule Post', 'wp-editorial-abilities'),
            'description' => __('Schedule an editable post for publication. Requires explicit confirmation.', 'wp-editorial-abilities'),
            'category' => 'editorial-write',
            'input_schema' => $this->publishInputSchema(true),
            'output_schema' => $this->postOutputSchema(),
            'execute_callback' => [$this->posts, 'schedulePost'],
            'permission_callback' => [$this, 'canPublishInputPost'],
            'meta' => $this->publicMeta(['readonly' => false, 'destructive' => false, 'idempotent' => false]),
        ]);

        $this->ability('publish-post', [
            'label' => __('Publish Post', 'wp-editorial-abilities'),
            'description' => __('Publish an editable post. Requires explicit confirmation.', 'wp-editorial-abilities'),
            'category' => 'editorial-write',
            'input_schema' => $this->publishInputSchema(false),
            'output_schema' => $this->postOutputSchema(),
            'execute_callback' => [$this->posts, 'publishPost'],
            'permission_callback' => [$this, 'canPublishInputPost'],
            'meta' => $this->publicMeta(['readonly' => false, 'destructive' => false, 'idempotent' => false]),
        ]);
    }

    public function canRead(): bool
    {
        return current_user_can('edit_posts');
    }

    public function canCreatePosts(): bool
    {
        return current_user_can('edit_posts');
    }

    public function canUploadFiles(): bool
    {
        return current_user_can('upload_files');
    }

    public function canEditInputPost(array $input): bool
    {
        $post_id = isset($input['post_id']) ? (int) $input['post_id'] : 0;

        return $post_id > 0 && current_user_can('edit_post', $post_id);
    }

    public function canPublishInputPost(array $input): bool
    {
        $post_id = isset($input['post_id']) ? (int) $input['post_id'] : 0;

        return $post_id > 0 && current_user_can('edit_post', $post_id) && current_user_can('publish_posts');
    }

    private function ability(string $name, array $args): void
    {
        wp_register_ability('wp-editorial-abilities/' . $name, $args);
    }

    private function publicMeta(array $annotations): array
    {
        return [
            'show_in_rest' => true,
            'mcp' => ['public' => true],
            'annotations' => $annotations,
        ];
    }

    private function objectSchema(array $properties = [], array $required = []): array
    {
        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    private function arrayOfObjectsSchema(): array
    {
        return [
            'type' => 'array',
            'items' => ['type' => 'object'],
        ];
    }

    private function postOutputSchema(): array
    {
        return $this->objectSchema([
            'post_id' => ['type' => 'integer'],
            'status' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'edit_url' => ['type' => 'string'],
            'preview_url' => ['type' => 'string'],
            'permalink' => ['type' => 'string'],
            'actor' => ['type' => 'integer'],
            'timestamp' => ['type' => 'string'],
        ]);
    }

    private function draftInputSchema(): array
    {
        return $this->objectSchema([
            'title' => ['type' => 'string', 'required' => true],
            'content' => ['type' => 'string', 'required' => true],
            'excerpt' => ['type' => 'string'],
            'author_id' => ['type' => 'integer', 'description' => 'User ID to assign as post author. Use list-authors first. Falls back to the current MCP user if omitted or invalid.'],
            'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            'featured_media_id' => ['type' => 'integer'],
            'media_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'seo' => ['type' => 'object'],
        ], ['title', 'content']);
    }

    private function seoInputSchema(): array
    {
        return $this->objectSchema([
            'post_id' => ['type' => 'integer', 'required' => true],
            'target' => ['type' => 'string', 'enum' => ['auto', 'yoast', 'rank_math', 'both'], 'default' => 'auto'],
            'seo_title' => ['type' => 'string'],
            'meta_description' => ['type' => 'string'],
            'focus_keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
            'canonical_url' => ['type' => 'string', 'format' => 'uri'],
            'open_graph_title' => ['type' => 'string'],
            'open_graph_description' => ['type' => 'string'],
            'open_graph_image' => ['type' => 'string', 'format' => 'uri'],
            'twitter_title' => ['type' => 'string'],
            'twitter_description' => ['type' => 'string'],
            'twitter_image' => ['type' => 'string', 'format' => 'uri'],
        ], ['post_id']);
    }

    private function publishInputSchema(bool $scheduled): array
    {
        $properties = [
            'post_id' => ['type' => 'integer', 'required' => true],
            'confirm_publish' => ['type' => 'boolean', 'required' => true],
        ];

        if ($scheduled) {
            $properties['date_gmt'] = ['type' => 'string', 'required' => true];
        }

        return $this->objectSchema($properties, $scheduled ? ['post_id', 'confirm_publish', 'date_gmt'] : ['post_id', 'confirm_publish']);
    }
}
