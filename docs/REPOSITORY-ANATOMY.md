# Repository Anatomy

This plugin is a thin orchestration layer on top of the [WordPress Abilities API](https://make.wordpress.org/core/2025/11/10/abilities-api/). It registers editorial tools that the official [MCP Adapter](https://github.com/WordPress/mcp-adapter) exposes to MCP clients. WordPress core APIs (`wp_insert_post`, media library, taxonomies, post meta) live in service classes; ability definitions stay in one registrar.

```
MCP client (Claude, Cursor, Codex, …)
        │
        ▼
  MCP Adapter plugin          ← transport, auth, tool discovery
        │
        ▼
  WordPress Abilities API     ← wp_register_ability(), schemas, permissions
        │
        ▼
  WP Editorial Abilities      ← this repo
        │
        ▼
  WordPress core              ← posts, media, taxonomies, SEO meta
```

## Directory layout

```
wp-editorial-abilities/
├── wp-editorial-abilities.php   # Plugin header, constants, PSR-4 autoloader, boot hook
├── includes/
│   ├── Plugin.php               # Singleton bootstrap; checks Abilities API + MCP Adapter
│   ├── Abilities/
│   │   └── AbilityRegistrar.php # All ability definitions, JSON schemas, permission callbacks
│   └── Services/                # Execute callbacks — WordPress business logic
│       ├── PostService.php      # Draft CRUD, publish, schedule, internal links
│       ├── MediaService.php     # Library search, attachments, featured image, base64 upload
│       ├── SeoService.php       # Yoast / Rank Math meta writes
│       ├── EditorialPatternService.php  # Categories, tags, recent posts, pattern analysis
│       └── UserService.php      # Author listing for draft assignment
├── skills/
│   └── generic-editorial-note/  # Optional MCP agent skill (workflow for drafting notes)
├── docs/
│   ├── ABILITIES.md             # Abilities reference and SEO options
│   └── REPOSITORY-ANATOMY.md    # This file — architecture and codebase layout
├── tests/
│   └── bootstrap.php            # Test bootstrap (loads plugin entry point)
├── CONTRIBUTING.md              # Local dev, testing, releases
├── composer.json                # PHPCS / PHPCompatibility dev tooling
├── phpcs.xml.dist               # WordPress coding standards config
├── .wp-env.json                 # Local WP 7.0 + MCP Adapter via @wordpress/env
└── docker-compose.yml           # Alternative local stack (MariaDB + WP + WP-CLI)
```

## Boot sequence

1. **`wp-editorial-abilities.php`** — Defines `WPEA_*` constants and registers a PSR-4 autoloader for the `WpEditorialAbilities\` namespace under `includes/`.
2. **`Plugin::boot()`** — Runs on `plugins_loaded`. If `wp_register_ability()` is missing (WordPress < 6.9), shows an admin error and stops. Otherwise wires `AbilityRegistrar`.
3. **`AbilityRegistrar`** — Hooks `wp_abilities_api_categories_init` and `wp_abilities_api_init` to register two categories (`editorial-read`, `editorial-write`) and all abilities under the `wp-editorial-abilities/*` namespace. Each ability declares `input_schema`, `output_schema`, `execute_callback`, `permission_callback`, and MCP metadata (`meta.mcp.public => true`).
4. **Services** — Stateless classes invoked as execute callbacks. They return arrays or `WP_Error`; the Abilities API handles serialization and REST/MCP exposure.

## Where to change things

| Goal | Start here |
|------|------------|
| Add or modify an ability | `includes/Abilities/AbilityRegistrar.php` |
| Change post/draft/publish logic | `includes/Services/PostService.php` |
| Change media handling | `includes/Services/MediaService.php` |
| Add SEO plugin support | `includes/Services/SeoService.php` |
| Change taxonomy / pattern analysis | `includes/Services/EditorialPatternService.php` |
| Adjust MCP agent workflow hints | `skills/generic-editorial-note/SKILL.md` |

For local development and release workflows, see [`CONTRIBUTING.md`](../CONTRIBUTING.md).
