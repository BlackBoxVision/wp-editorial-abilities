# Abilities Reference

All abilities are registered under the namespace `wp-editorial-abilities/*` and exposed to MCP clients through the [MCP Adapter](https://github.com/WordPress/mcp-adapter).

Source of truth: [`includes/Abilities/AbilityRegistrar.php`](../includes/Abilities/AbilityRegistrar.php).

## Read abilities

Category: `editorial-read`. Require `edit_posts`.

| Ability | Description |
| --- | --- |
| `list-authors` | Users who can be assigned as post authors |
| `list-categories` | Post categories with IDs, names, slugs, parents, counts |
| `list-tags` | Post tags with IDs, names, slugs, counts |
| `get-recent-posts-by-category` | Recent posts in a category for planning and style analysis |
| `get-post-editorial-patterns` | Structural patterns extracted from recent category posts |
| `get-media-library-items` | Search media library for attachable images |

## Write abilities

Category: `editorial-write`.

| Ability | Description |
| --- | --- |
| `create-editorial-draft` | Create a draft from title, content, categories, tags, media, SEO |
| `update-editorial-draft` | Update an existing draft or pending post |
| `attach-images-to-draft` | Attach media IDs or sideload image URLs to a post |
| `set-featured-image` | Set an attachment as the featured image |
| `upload-media-base64` | Upload a file from a base64 payload to the media library |
| `suggest-internal-links` | Suggest existing posts to link from a draft |
| `optimize-seo-metadata` | Write Yoast and/or Rank Math SEO metadata |
| `schedule-post` | Schedule a post for future publication |
| `publish-post` | Publish a post immediately |

## Permissions

- Read abilities: `edit_posts`.
- Draft creation: `edit_posts`.
- Media upload (`upload-media-base64`): `upload_files`.
- Edit abilities: `edit_post` on the target post ID.
- Publish / schedule: `edit_post` + `publish_posts` on the target post ID.

## Publishing safety

`publish-post` and `schedule-post` require `confirm_publish: true` in the input. Without it, the ability rejects the request. This prevents accidental publication by MCP clients.

## SEO support

`optimize-seo-metadata` supports Yoast SEO and Rank Math via the `target` input:

| Value | Behavior |
| --- | --- |
| `auto` | Write to whichever supported SEO plugin is active (default) |
| `yoast` | Write Yoast fields only |
| `rank_math` | Write Rank Math fields only |
| `both` | Write both sets of meta fields |

Supported fields include SEO title, meta description, focus keywords, canonical URL, and Open Graph / Twitter metadata.
