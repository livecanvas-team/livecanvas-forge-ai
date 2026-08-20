# LiveCanvas AI Bridge Development Status

Last reviewed: 2026-08-20

## Product Status

LiveCanvas AI Bridge `0.2.0-beta.4` is a **staging beta, not production guaranteed**. It is useful for connecting coding agents to WordPress and LiveCanvas, but complex write workflows still need broader real-site verification before production guarantees are appropriate. This document describes the beta.4 release candidate; the previous public prerelease is beta.3.12.

The plugin has three responsibilities:

1. Connect a coding agent to exactly one WordPress site.
2. Give the agent structured read, preview, apply, audit, and rollback operations.
3. Install validated Picowind or Picostrap starter themes through the Theme Library.

The private LiveCanvas Theme Forge source analyzer and the planned LiveCanvas AI Vision product are separate projects. Arbitrary website cloning is not part of this public plugin.

## Ready For Beta Testing

These areas have implemented runtime code and automated regression coverage:

- Site setup, framework detection, reset, and connection diagnostics.
- Project-scoped configuration generation for Codex, OpenCode, Claude Code, Claude Desktop, and Cursor.
- Direct structural config merging preserves unrelated servers and settings, creates backups, uses atomic mode-`0600` writes, rejects relative paths and symlink escapes, and blocks credential-bearing project config inside the public WordPress root.
- Agent workspace roots are distinct from nested WordPress roots such as LocalWP `app/public`.
- Secure pairing sessions with site fingerprints, scopes, expiry, and revocation.
- Local project configuration uses secure pairing instead of a static MCP token, and stale sessions are invalidated and paired again once after a 401/403 response.
- Direct OAuth 2.1 + PKCE server contracts for compatible public HTTPS sites.
- WordPress 6.8 REST/pairing operation without a false degraded state, plus WordPress 7 Abilities/Direct OAuth with pairing fallback.
- Exact MCP package version pinning and package-version checks in handoff and smoke tests.
- WordPress, LiveCanvas, theme, WindPress, inventory, page HTML, and run inspection.
- Read-only validation plus preview/apply abilities for pages, partials, dynamic templates, design systems, and native pattern pages.
- Audit IDs and rollback for supported Command Deck writes.
- Local theme-file writes create restorable tombstone backups, so rollback deletes a file that did not exist before the agent created it.
- Targeted content patch preview/apply with failure on missing or ambiguous selectors.
- Guarded theme-file, media, Picostrap compile, debug/cache, Polylang, and SEO tools behind Full Access scopes.
- Picostrap design-system preview/compile/apply now uses native Customizer variables, deterministic fingerprints, an atomic bundle write, and unified rollback.
- GitHub/LiveCanvas-gated plugin update metadata and distribution checks.
- Theme Library catalog, package validation, child-theme installation, starter-data import, idempotency, and import rollback.
- Theme Library rollback now preserves the theme active before installation and backs up WindPress options, source CSS, generated CSS, sourcemap, and `theme.json` on disk with checksum verification.
- Theme Library now prunes inactive install handoffs older than seven days and removes only their orphaned WindPress backups; the active theme handoff is never deleted automatically.
- Theme Library failure injection is available under explicit `LCFA_E2E_MODE`; real LocalWP failures after media, partials, homepage, and build all pass automatic rollback and baseline comparison.
- Remote Theme Library builds remain `build_required` until the paired MCP runtime stores CSS and WordPress verifies the audit ID, import checksum, active theme, and cache checksum. Tailwind 4 is fully supported; Tailwind 3 is guided degraded mode.
- Asteria 1.0.1 now passes a real LocalWP preview/install/import/desktop/mobile/rollback run with separate LiveCanvas header/footer rendering and exact previous theme/homepage recovery.
- Progressive admin workspaces: three-step Setup, focused Connections, four-view Abilities & Runs, and five-view Command Deck.
- Local MCP visual QA now reports Playwright/browser readiness, verifies Chromium launch on demand, distinguishes its runtime from Direct OAuth WordPress-only abilities, and passes the Houseflow desktop/mobile LocalWP workflow.
- Stable/beta updater channels, exact prerelease selection, and a CI matrix for PHP 8.0-8.4, Node 18/20/22, Chromium, package build, and secret scanning.

## Beta: Needs More Real-Site Verification

These features are implemented, but should still be treated as beta:

- Direct OAuth discovery and authorization across different hosts, proxies, security plugins, and WordPress MCP Adapter versions.
- Remote Full Access writes across restrictive hosting filesystems.
- Picostrap Sass and WindPress/Picowind compilation across plugin versions.
- Visual checks inside real Windows/Linux and restricted coding-agent installations; Chromium launch itself is verified by CI on macOS, Linux, and Windows.
- Theme Library import across migrated, multilingual, cache-heavy, and restrictive remote sites. The first LocalWP Picowind/Tailwind 4 transaction now passes.
- Polylang and SEOPress operations across different content models and language assignments.
- The React Abilities & Runs shell and its progressive fallback to the PHP interface.
- End-to-end onboarding for Claude Code and generic MCP clients. OpenCode, Cursor, Claude Desktop Free, and Codex now have complete local handoff and reversible-write evidence on plugin beta.4/MCP beta.5. Claude Code remains preview/configuration-only for this beta because its CLI flow was not fully qualified.
- Remote staging qualification beyond the passing Cloudways/OpenCode flow, especially across proxies, WAFs, cache plugins, and restrictive shared hosting.

## Not Complete

The following work remains intentionally open:

- Rich WooCommerce product and archive generation.
- Deeper ACF-aware field mapping and markup generation.
- Production-grade screenshot-to-code or URL-to-page reconstruction.
- Broader asset generation and creative screenshot interpretation.
- Complete custom-theme enqueue and build fallbacks.
- A final DataViews-based AI Studio experience.
- Broad compatibility testing across hosting providers and large existing sites.
- Full migration to the stateless MCP 2026 protocol. The beta deliberately keeps the currently supported protocol versions until dedicated conformance tests pass.

## Current Verification Baseline

The current automated baseline contains:

- 83 PHP regression scripts in the default PHP runner.
- 14 non-GUI admin/runtime JavaScript checks in the default JavaScript runner.
- 13 MCP Node test files, including the opt-in real Chromium visual runtime check.
- PHP lint across the plugin, tests, and bundled compatibility code.
- `git diff --check`.
- Distribution build and package validation.
- Manual desktop and 390 px mobile review of the coding-agent setup guide.
- Manual desktop and 390 px mobile review of Setup, Connections, Build Plan, Theme Library, Abilities & Runs, and Command Deck.
- Real local MCP discovery, exact beta.5 handoff, snapshot, and reversible file writes from OpenCode, Codex, Claude Desktop Free, and Cursor.
- Cursor-specific package refresh evidence: **Reload Window** retained the previous MCP process, while **Customize → MCPs → livecanvas-forge → Reload** loaded beta.5 and allowed the exact-version smoke test to pass.
- Passing LocalWP Picostrap Houseflow generation, compile, targeted patch, exact rollback, and six-view visual check.
- Passing LocalWP Picowind Asteria import, Tailwind 4 cache verification, desktop/mobile visual check, and rollback to the original Picostrap theme/homepage.
- Passing LocalWP Theme Library failure injection at all four checkpoints, with theme, homepage, and compiled-cache state restored each time.
- Passing public-baseline GitHub Actions run `32132984137` across PHP 8.0-8.4, Node 18.17/20/22, Chromium on macOS/Linux/Windows, Gitleaks, and the distribution ZIP. The beta.4 release commit requires its own green run before publication.

The legacy GUI smoke script is not part of this passing baseline. It contains stale localhost assumptions and requires interactive macOS/Chrome access; current onboarding QA is performed through the controlled browser workflow instead.

## Release Gate

Before calling a release production-ready, complete all of the following:

1. Verify Codex read, preview, apply, and rollback on local and remote staging sites.
2. Verify the preview-only Claude Code CLI and generic MCP paths; repeat the qualified OpenCode, Codex, Claude Desktop, and Cursor paths on additional machines.
3. Run Theme Library import and rollback on clean Picowind and existing-content sites.
4. Verify Full Access tools on at least two hosting environments with different filesystem policies.
5. Publish a compatibility matrix and document known host/plugin conflicts.

For `0.2.0-beta.4`, the plugin pins `@livecanvas/ai-bridge-mcp@0.2.0-beta.5`. Local OpenCode, Cursor, Claude Desktop Free, and Codex handoff/write/rollback flows pass on the final versions; Claude Code is labelled preview/configuration-only. The Cloudways OpenCode/Asteria/WindPress flow passes on the previous beta baseline. The remaining publication gates are the exact-commit CI run, npm beta.5 publication, GitHub prerelease asset verification, and the beta.3.12-to-beta.4 updater check.

The detailed stack-by-stack matrix and remaining production gates are maintained in [Integration Completeness Audit](integration-completeness-audit.md).

Until those gates pass, use staging, confirm the site URL and fingerprint, preview every change, and keep rollback available.
