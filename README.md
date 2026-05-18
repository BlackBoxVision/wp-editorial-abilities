# WP Editorial Abilities

WordPress plugin that exposes editorial workflows through the WordPress Abilities API and the official WordPress MCP Adapter.

## Requirements

- WordPress 6.9+; tested target is WordPress 7.0 RC4.
- PHP 8.0+.
- Official `wordpress/mcp-adapter` plugin for MCP access.
- A dedicated WordPress user for MCP clients. Give it only the editorial capabilities it needs.

## Abilities

Namespace: `wp-editorial-abilities/*`.

Read abilities:

- `list-categories`
- `list-tags`
- `get-recent-posts-by-category`
- `get-post-editorial-patterns`
- `get-media-library-items`

Write abilities:

- `create-editorial-draft`
- `update-editorial-draft`
- `attach-images-to-draft`
- `set-featured-image`
- `suggest-internal-links`
- `optimize-seo-metadata`
- `schedule-post`
- `publish-post`

Publishing and scheduling require `confirm_publish: true` and WordPress publish permissions.

## Local WordPress 7 RC4

Using `wp-env`:

```bash
npx @wordpress/env start
```

Admin URL: `http://localhost:8888/wp-admin`

Using Docker Compose:

```bash
docker compose up -d
docker compose run --rm cli wp core update --version=7.0-RC4 --allow-root
docker compose run --rm cli wp core install --url=http://localhost:8080 --title="Editorial Abilities" --admin_user=admin --admin_password=password --admin_email=admin@example.com --allow-root
docker compose run --rm cli wp plugin activate wp-editorial-abilities --allow-root
```

Install and activate the MCP Adapter in the same WordPress instance before connecting Claude, Codex, Cursor, or another MCP client.

## MCP Smoke Test

For local STDIO transport through WP-CLI:

```bash
wp mcp-adapter serve --user=admin --server=mcp-adapter-default-server
```

Expected flow:

1. Discover abilities through `mcp-adapter-discover-abilities`.
2. Inspect `wp-editorial-abilities/create-editorial-draft`.
3. Execute draft creation with title, content, categories, tags, and SEO metadata.
4. Confirm the draft exists in wp-admin and includes edit/preview URLs.
5. Try `publish-post` without `confirm_publish`; it must fail.
6. Try `publish-post` with `confirm_publish: true` as a user with publish permissions.

## SEO Support

`optimize-seo-metadata` supports Yoast and Rank Math.

- `target: "auto"` writes to detected active SEO plugins.
- `target: "yoast"` writes Yoast fields.
- `target: "rank_math"` writes Rank Math fields.
- `target: "both"` writes both sets of meta fields.

## Safety Notes

- Do not use administrator credentials for routine MCP editorial work.
- Prefer a dedicated editor user with application passwords for remote MCP access.
- Review drafts in wp-admin before publishing.
- Keep write abilities private to trusted MCP clients and audit WordPress logs.
