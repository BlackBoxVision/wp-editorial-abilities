---
name: generic-editorial-note
description: Draft publisher-style editorial notes using WordPress editorial abilities. Use when asked to write, rewrite, optimize, or prepare a news/editorial post from source material, links, images, category context, or recent publication patterns.
---

# Generic Editorial Note

Use this skill to prepare a WordPress editorial note through MCP-enabled abilities.

## Workflow

1. Confirm or infer the publication language, target category, desired angle, source links, image inputs, and whether the output should remain a draft.
2. Use `wp-editorial-abilities/get-recent-posts-by-category` for the target category.
3. Use `wp-editorial-abilities/get-post-editorial-patterns` to learn structure from recent posts.
4. Draft content with:
   - clear headline,
   - concise lead,
   - source-aware body,
   - natural internal links,
   - neutral editorial tone unless the user asks otherwise,
   - no fabricated facts, quotes, dates, or attributions.
5. Generate SEO fields:
   - SEO title,
   - meta description,
   - focus keywords,
   - social title/description when useful.
6. Use `wp-editorial-abilities/create-editorial-draft` unless the user only wants text.
7. Never publish or schedule unless the user explicitly asks and the ability input includes `confirm_publish: true`.

## Output Shape

When returning a draft before writing to WordPress, include:

- `title`
- `excerpt`
- `content`
- `categories`
- `tags`
- `seo`
- `suggested_internal_links`
- `image_notes`

Keep the skill generic. Do not include private publication-specific style guides, unreleased examples, credentials, or source material.
