# Contributing

Guide for local development, testing, and publishing releases.

## Local development

### wp-env

```bash
npx @wordpress/env start
```

Admin URL: `http://localhost:8888/wp-admin`

Uses [`.wp-env.json`](.wp-env.json) to run WordPress 7.0 with this plugin and [`WordPress/mcp-adapter`](https://github.com/WordPress/mcp-adapter) pre-linked.

### Docker Compose

```bash
docker compose up -d
docker compose run --rm cli wp core update --version=7.0 --allow-root
docker compose run --rm cli wp core install --url=http://localhost:8080 --title="Editorial Abilities" --admin_user=admin --admin_password=password --admin_email=admin@example.com --allow-root
docker compose run --rm cli wp plugin activate wp-editorial-abilities --allow-root
```

Install and activate the MCP Adapter in the same instance before connecting an MCP client.

## MCP smoke test

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

## Code quality

```bash
composer install
composer run lint      # phpcs against includes/
composer run syntax    # php -l on all plugin PHP files
```

Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) as configured in [`phpcs.xml.dist`](phpcs.xml.dist).

Use [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `docs:`, etc.) — release changelogs are generated from commit history.

## Architecture

See [`docs/REPOSITORY-ANATOMY.md`](docs/REPOSITORY-ANATOMY.md) for the stack diagram, boot sequence, directory layout, and where to change things.

Key entry points:

| Goal | File |
| --- | --- |
| Add or modify an ability | `includes/Abilities/AbilityRegistrar.php` |
| Post / draft / publish logic | `includes/Services/PostService.php` |
| Media handling | `includes/Services/MediaService.php` |
| SEO plugin support | `includes/Services/SeoService.php` |
| Taxonomy / pattern analysis | `includes/Services/EditorialPatternService.php` |
| MCP agent workflow hints | `skills/generic-editorial-note/SKILL.md` |

## Publishing a release

Push a semver tag to trigger [`.github/workflows/release.yml`](.github/workflows/release.yml):

```bash
git tag v0.2.0
git push origin v0.2.0
```

The workflow:

1. Generates a changelog from Conventional Commits since the previous tag.
2. Builds `wp-editorial-abilities-vX.Y.Z.zip` with the correct WordPress plugin folder structure.
3. Creates a GitHub Release and attaches the zip.

Bump the version in [`wp-editorial-abilities.php`](wp-editorial-abilities.php) (`Version` header and `WPEA_PLUGIN_VERSION`) before tagging.
