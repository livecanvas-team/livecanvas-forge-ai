# LiveCanvas AI Bridge Development Status

Last reviewed: 2026-08-02

## Product Status

LiveCanvas AI Bridge is an **alpha product for staging and controlled testing**. It is already useful for connecting coding agents to WordPress and LiveCanvas, but complex write workflows still need broader real-site verification before production guarantees are appropriate.

The plugin has three responsibilities:

1. Connect a coding agent to exactly one WordPress site.
2. Give the agent structured read, preview, apply, audit, and rollback operations.
3. Install validated Picowind starter themes through the Theme Library.

The private LiveCanvas Theme Forge source analyzer and the planned LiveCanvas AI Vision product are separate projects. Arbitrary website cloning is not part of this public plugin.

## Ready For Alpha Testing

These areas have implemented runtime code and automated regression coverage:

- Site setup, framework detection, reset, and connection diagnostics.
- Project-scoped Codex and OpenCode configuration generation.
- Secure pairing sessions with site fingerprints, scopes, expiry, and revocation.
- Direct OAuth 2.1 + PKCE server contracts for compatible public HTTPS sites.
- WordPress, LiveCanvas, theme, WindPress, inventory, page HTML, and run inspection.
- Read-only validation plus preview/apply abilities for pages, partials, dynamic templates, design systems, and native pattern pages.
- Audit IDs and rollback for supported Command Deck writes.
- Targeted content patch preview/apply with failure on missing or ambiguous selectors.
- Guarded theme-file, media, Picostrap compile, debug/cache, Polylang, and SEO tools behind Full Access scopes.
- Picostrap design-system preview/compile/apply now uses native Customizer variables, deterministic fingerprints, an atomic bundle write, and unified rollback.
- GitHub/LiveCanvas-gated plugin update metadata and distribution checks.
- Theme Library catalog, package validation, child-theme installation, starter-data import, idempotency, and import rollback.
- Theme Library rollback now preserves the theme active before installation and backs up WindPress options, source CSS, generated CSS, sourcemap, and `theme.json` on disk with checksum verification.
- Asteria 1.0.1 now passes a real LocalWP preview/install/import/desktop/mobile/rollback run with separate LiveCanvas header/footer rendering and exact previous theme/homepage recovery.
- Progressive admin workspaces: three-step Setup, focused Connections, four-view Abilities & Runs, and five-view Command Deck.

## Beta: Needs More Real-Site Verification

These features are implemented, but should still be treated as beta:

- Direct OAuth discovery and authorization across different hosts, proxies, security plugins, and WordPress MCP Adapter versions.
- Remote Full Access writes across restrictive hosting filesystems.
- Picostrap Sass and WindPress/Picowind compilation across plugin versions.
- Visual checks, because the local MCP runtime must provide Playwright and a compatible Chromium installation.
- Theme Library import across migrated, multilingual, cache-heavy, and restrictive remote sites. The first LocalWP Picowind/Tailwind 4 transaction now passes.
- Polylang and SEOPress operations across different content models and language assignments.
- The React Abilities & Runs shell and its progressive fallback to the PHP interface.
- End-to-end onboarding for Claude Code, Claude Desktop, Cursor, and generic MCP clients.

## Not Complete

The following work remains intentionally open:

- Rich WooCommerce product and archive generation.
- Deeper ACF-aware field mapping and markup generation.
- Production-grade screenshot-to-code or URL-to-page reconstruction.
- Broader asset generation and creative screenshot interpretation.
- Complete custom-theme enqueue and build fallbacks.
- A final DataViews-based AI Studio experience.
- Broad compatibility testing across hosting providers and large existing sites.

## Current Verification Baseline

The current automated baseline contains:

- 67 PHP regression scripts.
- 12 non-GUI admin/runtime JavaScript unit checks.
- 8 MCP Node test files.
- PHP lint across 138 tracked PHP files, including tests and bundled compatibility code.
- `git diff --check`.
- Distribution build and package validation.
- Manual desktop and 390 px mobile review of the coding-agent setup guide.
- Manual desktop and 390 px mobile review of Setup, Connections, Build Plan, Theme Library, Abilities & Runs, and Command Deck.
- Real MCP discovery from Codex and OpenCode against a clean local WordPress site.

The legacy GUI smoke script is not part of this passing baseline. It contains stale localhost assumptions and requires interactive macOS/Chrome access; current onboarding QA is performed through the controlled browser workflow instead.

## Release Gate

Before calling a release production-ready, complete all of the following:

1. Verify Codex read, preview, apply, and rollback on local and remote staging sites.
2. Verify OpenCode, Claude, and Cursor connection paths with fresh installations.
3. Run Theme Library import and rollback on clean Picowind and existing-content sites.
4. Verify Full Access tools on at least two hosting environments with different filesystem policies.
5. Publish a compatibility matrix and document known host/plugin conflicts.

The detailed stack-by-stack matrix and remaining production gates are maintained in [Integration Completeness Audit](integration-completeness-audit.md).

Until those gates pass, use staging, confirm the site URL and fingerprint, preview every change, and keep rollback available.
