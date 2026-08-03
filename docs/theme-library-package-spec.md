# LiveCanvas AI Bridge Theme Library Package Spec

Status: beta

This document defines the public package contract for Theme Library imports in LiveCanvas AI Bridge.

The public plugin imports validated Picowind or Picostrap child theme ZIPs and deterministic starter data. It does not clone arbitrary websites. Internal generators, Playwright analysis, source-site reconstruction, and visual matching pipelines should live in a separate private project and export ZIPs that match this contract.

## Goals

- Install validated Picowind and Picostrap child themes from a catalog.
- Import LiveCanvas starter data in a predictable order.
- Keep header and footer as separate LiveCanvas partials.
- Replace media placeholders with WordPress Media Library URLs.
- Make re-imports idempotent for the same theme/version/checksum.
- Store rollback metadata for every import.
- Make the declared Picowind or Picostrap framework visible and filterable in the catalog.

## Catalog

Default catalog URL:

```text
https://raw.githubusercontent.com/livecanvas-team/livecanvas-theme-library/main/catalog.json
```

Beta fallback catalog:

```text
https://raw.githubusercontent.com/livecanvas-team/livecanvas-forge-ai/main/examples/theme-library/catalog.json
```

The dedicated public catalog repository is the canonical distribution location. The fallback catalog in the AI Bridge repository remains available for recovery and development testing.

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
      "framework": "picowind",
      "css": "tailwind",
      "ui": "daisyui",
      "builder": "picowind",
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

### Framework Metadata

New catalog entries should declare one of these framework values:

- `picowind` for Tailwind CSS and DaisyUI child themes.
- `picostrap` for Bootstrap 5 child themes.

The optional `css`, `ui`, and `builder` fields make the stack explicit on each card. Legacy catalog entries without `framework` continue to normalize to `picowind`. Common aliases such as `tailwind`, `daisyui`, `bootstrap-5`, and `picostrap5` normalize to the matching framework.

The WordPress Theme Library provides independent **Framework** and **Theme type** filters. The framework badge remains visible on every theme card so users can distinguish the target stack before preview or installation.

The `lcfa-theme.v1` contract supports both frameworks. The child theme `Template` header, catalog framework, and manifest framework must agree. Picowind uses WindPress/Tailwind build verification; Picostrap ships Sass source plus a precompiled Bootstrap bundle that is verified before the import can become ready.

## Repository Structure

Recommended catalog repository layout:

```text
catalog.json
themes/{theme-slug}/{theme-slug}.zip
themes/{theme-slug}/screenshots/*
themes/{theme-slug}/metadata.json
```

Only the ZIP and the catalog are required by the importer.

## Marketplace Cover Images

Theme Library catalog cards should use a curated marketplace cover image, not the raw full-page screenshot.

Recommended files:

```text
themes/{theme-slug}/screenshots/home.jpg
themes/{theme-slug}/screenshots/cover.jpg
```

- `home.jpg` is the raw desktop snapshot of the generated site.
- `cover.jpg` is the polished marketplace asset shown in the catalog.
- The catalog `screenshot` field should point to `cover.jpg`.
- The ZIP can still include the normal WordPress theme `screenshot.jpg`; that file is separate from the marketplace cover.

The internal export pipeline should generate `cover.jpg` after the real site snapshot:

1. Capture a clean desktop screenshot of the generated homepage.
2. Send that screenshot to an image provider as visual reference.
3. Ask the model to keep the website readable inside a browser/monitor mockup.
4. Ask the model to generate an abstract background tuned to the screenshot palette.
5. Save the result as `screenshots/cover.jpg`.
6. Save the prompt and provider metadata in the private generator logs, never API keys.

The public repository includes a helper for this private/internal workflow:

```bash
node scripts/theme-library-cover.js \
  --provider prompt \
  --screenshot examples/theme-library/themes/asteria-search/screenshots/home.jpg \
  --output /tmp/asteria-cover-prompt.json \
  --title "Asteria Search" \
  --subtitle "One-page Picowind starter"
```

Generation providers:

```bash
# GPT Image 2 / OpenAI image edit
OPENAI_API_KEY="..." \
node scripts/theme-library-cover.js \
  --provider openai \
  --screenshot screenshots/home.jpg \
  --output screenshots/cover.jpg \
  --title "Asteria Search"

# WaveSpeed GPT Image 2 Edit.
# The script defaults to:
# - https://api.wavespeed.ai/api/v3/media/upload/binary
# - https://api.wavespeed.ai/api/v3/openai/gpt-image-2/edit
WAVESPEED_API_KEY="..." \
node scripts/theme-library-cover.js \
  --provider wavespeed \
  --screenshot screenshots/home.jpg \
  --output screenshots/cover.jpg \
  --title "Asteria Search"
```

Do not commit API keys, raw provider responses containing secrets, or temporary generation files.

## ZIP Structure

Every ZIP must contain a valid Picowind or Picostrap child theme. A root directory inside the ZIP is allowed. These files are required for both frameworks:

```text
style.css
functions.php
screenshot.jpg
livecanvas/configuration.php
starter-data/lcfa-theme.json
starter-data/livecanvas-settings.json
starter-data/design-system.json
starter-data/media-manifest.json
starter-data/menus.json
starter-data/qa-report.json
starter-data/media/*
```

Picowind additionally requires:

```text
page-templates/empty.php
views/page-templates/empty.twig
public/styles/presets/daisyui.css
public/styles/tailwind.css
```

Picostrap additionally requires:

```text
page-templates/empty.php
css-output/bundle.css
sass/_theme_variables.scss
sass/_custom.scss
js/bootstrap.bundle.min.js
js/custom.js
```

If `starter-data/lcfa-theme.json` declares another `homepage.template`, that template file must exist in the ZIP. For Picowind, the default `page-templates/empty.php` must render a standalone `page-templates/empty.twig` with:

- `wp_head()` and `wp_footer()`;
- `wp_body_open()`;
- one `<main>` containing `{{ post.content }}`;
- `lc_custom_header()` or `lc_get_header()` before the main content;
- `lc_custom_footer()` or `lc_get_footer()` after the main content.

Do not rely on a parent `base.twig` unless the ZIP also ships the full child-theme view scaffold. Picowind header and footer content files contain only inner partial markup because its shell supplies the semantic elements.

For Picostrap, `page-templates/empty.php` must call `get_header()`, `the_content()`, and `get_footer()`. Picostrap partial files may include their outer `<header>` and `<footer>` elements. The compiled `css-output/bundle.css` must contain the Bootstrap runtime and every fragment declared under `css_verification.required_fragments`.

For both frameworks, homepage content must not contain inline header/footer markup. Put global CSS in framework-owned theme files rather than a `<style>` block inside starter content.

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
    "stylesheet": "studio-one",
    "framework": "picowind",
    "parent": "picowind"
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

For Picowind, the importer stores the design system payload through the WindPress `theme.json` cache API when WindPress is active.

```text
starter-data/design-system.json -> WindPress theme.json cache
```

The required `public/styles/tailwind.css` file can contain either Tailwind source imports/directives or already compiled CSS:

```css
@import "./presets/daisyui.css";
@import "./presets/example.css";
```

Tailwind source is compiled after the homepage and partials are written, so provider scans see the final imported content. Already compiled CSS is stored directly. In both cases AI Bridge verifies the persistent WindPress cache before declaring the import ready.

For Picostrap, `design-system.json` documents the source tokens while `sass/_theme_variables.scss` and `sass/_custom.scss` remain authoritative. The distributed `css-output/bundle.css` must already be compiled. AI Bridge verifies that bundle and does not invoke WindPress.

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
2. Framework design system and compiled CSS data.
3. Media into Media Library.
4. Header `lc_partial`.
5. Footer `lc_partial`.
6. Homepage page with `_lc_livecanvas_enabled=1`.
7. Menus.
8. `show_on_front=page` and `page_on_front={homepage_id}`.
9. WindPress cache verification for Picowind, or packaged bundle verification for Picostrap.
10. Framework-specific and AI Bridge cache flush.
11. Rollback metadata storage.

## Import Completion States

Theme Library uses explicit completion states:

- `ready`: starter data is imported and the persistent CSS cache is verified;
- `build_required`: starter data is imported, but a compatible local build runtime is unavailable;
- `build_failed`: the compiler failed or returned without producing a verifiable cache;
- `failed_rolled_back`: the import failed, then automatic rollback restored the previous state;
- `rollback_failed`: both import and automatic rollback failed; manual recovery remains available;
- `failed`: the import failed and automatic rollback was disabled or unavailable.

`POST /wp-json/lcfa/v1/theme-library/build` retries only the CSS build for an existing import. It does not duplicate pages, partials, media, or menus. The route is admin-only and is not MCP-public in v1.

## Idempotency

An import key is built from:

```text
theme.slug:theme.version:zip_sha256
```

Re-importing the same ready key returns `already_imported` unless `force=true`. Imports in `build_required` or `build_failed` keep their existing content and direct the admin to the build retry action instead of duplicating starter data.

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
- WindPress options changed by Picowind runtime initialization;
- the previous WindPress `main.css`, compiled CSS, sourcemap, and `theme.json` state.

The install step stores a short pending activation record before switching themes. The import consumes that record so rollback refers to the theme and menu locations that were active before installation, not the newly activated child theme. Large WindPress files are backed up under the AI Bridge uploads directory and verified with SHA-256; only metadata is stored in the WordPress rollback option.

Rollback records are dedicated Theme Library import records and are separate from normal AI Bridge command rollback records.

Automatic rollback is enabled by default for failed imports. The response keeps the original import error separate from `automatic_rollback`, including `attempted`, `ok`, `message`, `errors`, and the recovery plan. Administrators can disable it for one REST request with `auto_rollback: false`, or globally with:

```php
add_filter('lcfa_theme_library_auto_rollback', '__return_false');
```

Disabling automatic rollback never removes the stored `import_audit_id`; the normal manual rollback endpoint remains available.

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

- one Picowind or Picostrap child theme ZIP;
- one catalog item;
- one `lcfa-theme.v1` manifest;
- deterministic starter-data files;
- no unauthorized scraped brand, text, media, or video assets.

The public AI Bridge repo should remain the importer/runtime. The private Forge repo should remain the generator.
