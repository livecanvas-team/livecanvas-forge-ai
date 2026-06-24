# LiveCanvas AI Bridge Theme Library Package Spec

Status: alpha

This document defines the public package contract for Theme Library imports in LiveCanvas AI Bridge.

The public plugin imports only validated Picowind child theme ZIPs and deterministic starter data. It does not clone arbitrary websites. Internal generators, Playwright analysis, source-site reconstruction, and visual matching pipelines should live in a separate private project and export ZIPs that match this contract.

## Goals

- Install validated Picowind child themes from a catalog.
- Import LiveCanvas starter data in a predictable order.
- Keep header and footer as separate LiveCanvas partials.
- Replace media placeholders with WordPress Media Library URLs.
- Make re-imports idempotent for the same theme/version/checksum.
- Store rollback metadata for every import.

## Catalog

Default catalog URL:

```text
https://raw.githubusercontent.com/livecanvas-team/livecanvas-picowind-onepage-themes/main/catalog.json
```

Beta fallback catalog:

```text
https://raw.githubusercontent.com/livecanvas-team/livecanvas-forge-ai/main/examples/theme-library/catalog.json
```

The dedicated catalog repository is the long-term location. The fallback catalog in the AI Bridge repository exists so the importer can be tested before the dedicated catalog is populated.

Developers can override it with:

```php
add_filter('lcfa_theme_library_catalog_url', function () {
    return 'https://example.com/catalog.json';
});
```

### Catalog Shape

The catalog can be either:

```json
{
  "themes": [
    {
      "slug": "studio-one",
      "name": "Studio One",
      "version": "1.0.0",
      "description": "One-page starter theme for creative studios.",
      "category": "portfolio",
      "screenshot": "https://example.com/themes/studio-one/screenshots/home.jpg",
      "package_url": "https://example.com/themes/studio-one/studio-one.zip",
      "checksum": "sha256:0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
      "metadata_url": "https://example.com/themes/studio-one/metadata.json"
    }
  ]
}
```

or a top-level array of theme objects.

Required per item:

- `slug`
- `name` or `title`
- `version`
- `package_url`, `zip_url`, or `download_url`
- `checksum` or `sha256`, SHA-256 only
- `screenshot`, `screenshot_url`, or first entry of `screenshots`

Invalid catalog items are skipped and reported in catalog errors.

## Repository Structure

Recommended catalog repository layout:

```text
catalog.json
themes/{theme-slug}/{theme-slug}.zip
themes/{theme-slug}/screenshots/*
themes/{theme-slug}/metadata.json
```

Only the ZIP and the catalog are required by the importer.

## ZIP Structure

Every ZIP must contain a valid Picowind child theme. A root directory inside the ZIP is allowed. The following files are required:

```text
style.css
functions.php
screenshot.jpg
livecanvas/configuration.php
public/styles/presets/daisyui.css
public/styles/tailwind.css
starter-data/lcfa-theme.json
starter-data/livecanvas-settings.json
starter-data/design-system.json
starter-data/media-manifest.json
starter-data/menus.json
starter-data/qa-report.json
starter-data/media/*
```

The importer accepts a ZIP with either:

```text
starter-data/lcfa-theme.json
```

or:

```text
{zip-root}/starter-data/lcfa-theme.json
```

All manifest paths are normalized as relative paths. Absolute paths, empty paths, `..`, and traversal are rejected.

## Manifest: starter-data/lcfa-theme.json

Canonical schema: `lcfa-theme.v1`.

Minimal valid example:

```json
{
  "schema": "lcfa-theme.v1",
  "theme": {
    "slug": "studio-one",
    "name": "Studio One",
    "version": "1.0.0",
    "stylesheet": "studio-one"
  },
  "compatibility": {
    "ai_bridge": ">=0.1.25",
    "livecanvas": ">=4.0.0",
    "picowind": ">=1.0.0",
    "windpress": "optional"
  },
  "homepage": {
    "title": "Home",
    "slug": "home",
    "template": "page-templates/empty.php",
    "content_file": "starter-data/home.html"
  },
  "header": {
    "title": "Header",
    "variant": "1",
    "content_file": "starter-data/header.html"
  },
  "footer": {
    "title": "Footer",
    "variant": "1",
    "content_file": "starter-data/footer.html"
  },
  "media_manifest": "starter-data/media-manifest.json",
  "menus_file": "starter-data/menus.json",
  "design_system_file": "starter-data/design-system.json",
  "livecanvas_settings": "starter-data/livecanvas-settings.json",
  "qa_report": "starter-data/qa-report.json",
  "rollback": {
    "strategy": "lcfa-import-audit"
  }
}
```

Required fields:

- `schema` must be `lcfa-theme.v1`
- `theme.slug`
- `theme.version`
- `homepage.content_file`
- `header.content_file`
- `footer.content_file`

Optional path fields default to:

- `media_manifest`: `starter-data/media-manifest.json`
- `menus_file`: `starter-data/menus.json`
- `design_system_file`: `starter-data/design-system.json`
- `livecanvas_settings`: `starter-data/livecanvas-settings.json`
- `qa_report`: `starter-data/qa-report.json`

## Content Files

Content files are raw LiveCanvas-friendly HTML fragments.

Rules:

- Do not include `<html>`, `<head>`, or `<body>`.
- Homepage content must not include inline `<header>` or `<footer>` elements.
- Header and footer must be separate files and are imported as `lc_partial` posts.
- Use framework-compatible markup for the target child theme.
- Use media placeholders for packaged images.

Supported media placeholders:

```text
{{media:hero}}
{{media:hero:url}}
```

Both are replaced with the imported Media Library URL for asset ID `hero`.

## Media Manifest

Path: `starter-data/media-manifest.json`.

The importer accepts either `items` or `media`.

```json
{
  "items": [
    {
      "id": "hero",
      "file": "starter-data/media/hero.jpg",
      "title": "Hero image",
      "alt": "Studio interior with warm light",
      "caption": ""
    }
  ]
}
```

Required per media item:

- `id` or `asset_id`
- `file`

Optional:

- `title`
- `alt`
- `caption`

Media is deduped by:

- `_lcfa_theme_library_slug`
- `_lcfa_theme_library_asset_id`
- `_lcfa_theme_library_checksum`

## LiveCanvas Settings

Path: `starter-data/livecanvas-settings.json`.

Supported shape:

```json
{
  "options": {
    "option_name": {
      "key": "value"
    }
  }
}
```

Every listed option is backed up before update and restored on rollback.

## Design System

Path: `starter-data/design-system.json`.

The current importer stores the design system payload in:

```text
lcfa_theme_library_design_system
```

If `windpress_css` is present, AI Bridge attempts to import it through the WindPress bridge:

```json
{
  "tokens": {
    "colors": {
      "primary": "#30c7d9"
    }
  },
  "windpress_css": "/* generated CSS */"
}
```

If WindPress import is unavailable, the importer records a warning and continues.

## Menus

Path: `starter-data/menus.json`.

```json
{
  "menus": [
    {
      "name": "Primary Menu",
      "location": "primary",
      "items": [
        {
          "title": "Home",
          "url": "/"
        },
        {
          "title": "Contact",
          "url": "/contact/"
        }
      ]
    }
  ]
}
```

The importer creates missing menus, adds menu items, and assigns `nav_menu_locations`. Previous menu-location assignments are stored for rollback.

## Import Order

`preview` validates and returns a plan without writing.

`install` validates the ZIP, installs the child theme using WordPress `Theme_Upgrader`, and activates it.

`import` runs in this order:

1. LiveCanvas settings.
2. Design system and WindPress CSS data.
3. Media into Media Library.
4. Header `lc_partial`.
5. Footer `lc_partial`.
6. Homepage page with `_lc_livecanvas_enabled=1`.
7. Menus.
8. `show_on_front=page` and `page_on_front={homepage_id}`.
9. WindPress cache rebuild/flush when available.
10. AI Bridge cache flush.
11. Rollback metadata storage.

## Idempotency

An import key is built from:

```text
theme.slug:theme.version:zip_sha256
```

Re-importing the same key returns `already_imported` unless `force=true`.

Existing imported records are found by:

- `_lcfa_theme_library_slug`
- `_lcfa_theme_library_part`

Parts:

- `homepage`
- `header`
- `footer`

## Rollback

Each import creates one `import_audit_id`.

Rollback restores or removes:

- previous active theme;
- previous `show_on_front` and `page_on_front`;
- created homepage/header/footer posts;
- updated homepage/header/footer content and metadata;
- imported media attachments;
- created menus;
- previous `nav_menu_locations`;
- options touched by LiveCanvas settings import;
- design system option.

Rollback records are dedicated Theme Library import records and are separate from normal AI Bridge command rollback records.

## REST Endpoints

All endpoints are admin-only in v1 and require `manage_options`.

```text
GET  /wp-json/lcfa/v1/theme-library/catalog
POST /wp-json/lcfa/v1/theme-library/preview
POST /wp-json/lcfa/v1/theme-library/install
POST /wp-json/lcfa/v1/theme-library/import
POST /wp-json/lcfa/v1/theme-library/rollback
```

These endpoints are not MCP-public in v1.

## Internal Theme Forge Output

The private `LiveCanvas Theme Forge Internal` project should treat this document as its export target.

It may use Playwright, agent orchestration, visual QA, screenshot-to-section analysis, asset generation, and staging WordPress sites internally, but the public output should always be:

- one Picowind child theme ZIP;
- one catalog item;
- one `lcfa-theme.v1` manifest;
- deterministic starter-data files;
- no unauthorized scraped brand, text, media, or video assets.

The public AI Bridge repo should remain the importer/runtime. The private Forge repo should remain the generator.
