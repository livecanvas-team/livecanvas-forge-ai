# LiveCanvas AI Bridge: 0.2.0-beta.4 Release Plan

Last updated: 2026-08-20

## Release candidate

- Plugin: `0.2.0-beta.4`.
- MCP package: `@livecanvas/ai-bridge-mcp@0.2.0-beta.5`.
- Previous public plugin prerelease: `0.2.0-beta.3.12`.
- Previous npm beta: `0.2.0-beta.4`.
- Release policy: staging beta, not production guaranteed.

The MCP package advances to beta.5 because npm beta.4 is already immutable and the MCP source changed during the local-agent onboarding work. The plugin and MCP versions do not need identical prerelease counters; the plugin pins the exact package it expects.

## Scope

This release candidate contains:

- separate agent workspace and WordPress roots, including LocalWP `app/public` layouts;
- project-scoped setup for Codex, OpenCode, Cursor, and Claude Code, plus direct Claude Desktop app configuration;
- structural config merging that preserves unrelated settings and MCP servers;
- automatic config backups, atomic writes, mode `0600`, absolute-path checks, and symlink containment;
- secure local pairing without static MCP tokens in project files;
- pairing recovery after expired or revoked sessions and one authenticated retry after a stale session;
- handoff-aware readiness: discovery alone no longer marks a connection Ready;
- compact MCP catalogs for clients with provider tool-count limits;
- standards-compliant MCP annotations and protocol negotiation for `2025-06-18` and `2025-11-25`;
- tombstone backups so rollback removes files created by an agent;
- updated onboarding copy for Codex, OpenCode, Cursor, Claude Code, and Claude Desktop.

## Automated gates

All gates must pass against the exact release commit:

1. PHP regression suite and lint on PHP 8.0, 8.1, 8.2, 8.3, and 8.4.
2. MCP and admin JavaScript suites on Node 18.17, 20, and 22.
3. Chromium visual runtime on Linux, macOS, and Windows with `LCFA_VISUAL_E2E=1`; no skip is accepted in release CI.
4. `git diff --check`.
5. Gitleaks over full history and the release commit.
6. Composer and npm dependency audits with no high or critical advisory.
7. Distribution build and `package_dist_phase1.php`.
8. GitHub updater regression and exact plugin/tag/version checks.
9. npm package dry-run verifying only `bin/`, `src/`, and `README.md` are published.

## Manual acceptance gates

Each client advertised as supported must complete configuration, `get_connection_handoff`, `get_snapshot`, one preview, one reversible write, and rollback:

| Client | Local status before final RC | Remaining release action |
| --- | --- | --- |
| OpenCode | Complete on final plugin/MCP versions | None; beta.5 handoff, snapshot, 3/3 smoke, write, and rollback passed |
| Cursor | Complete on final plugin/MCP versions | None; native beta.5 handoff plus final 3/3 Ready verification passed |
| Claude Desktop Free | Complete on final plugin/MCP versions | None; beta.5 handoff, snapshot, 4/4 smoke, write, and rollback passed |
| Codex | Complete on final plugin/MCP versions | None; beta.5 handoff, snapshot, Codex smoke, tombstone write, and rollback passed |
| Claude Code | Configuration generation only | Publish as preview/configuration-only; do not claim full CLI qualification |

The final repetition also confirmed that Cursor may retain the old MCP process after **Reload Window**. Users must open **Customize → MCPs**, select `livecanvas-forge`, and click **Reload** after a package update.

Theme and hosting acceptance:

1. LocalWP Picowind/WindPress/Tailwind 4 install, import, verified build, reversible file write, and rollback: complete on the current beta baseline.
2. LocalWP Picostrap design-system compile, apply, visual check, and rollback: complete on the current beta baseline.
3. Cloudways OpenCode/Asteria/WindPress build verification: complete on the preceding beta baseline; the public beta.3.12-to-beta.4 updater check remains post-publication.
4. Restrictive shared hosting remains explicitly unqualified and is not reported as fully supported.

## Publication order

1. Review and commit the release candidate on `codex/release-0.2.0-beta.4`.
2. Push the branch and require the complete GitHub Actions matrix to pass.
3. Merge the exact reviewed commit to `main` and require the `main` matrix to pass.
4. Publish `@livecanvas/ai-bridge-mcp@0.2.0-beta.5` and verify the npm `beta` dist-tag.
5. Build or retrieve `dist/livecanvas-forge-ai.zip` from the exact green commit and record SHA-256.
6. Create tag and GitHub prerelease `v0.2.0-beta.4` with the asset named exactly `livecanvas-forge-ai.zip`.
7. Force an update check on an installed beta.3.12 site and verify the update to beta.4.
8. Verify that the stable channel does not offer the prerelease.

## Go/no-go criteria

Publish only when:

- the release commit has no unintended dirty or untracked files;
- every CI job is green and the visual runtime was not skipped;
- npm beta.5 resolves and reports the expected package version;
- the ZIP contains plugin beta.4 and pins MCP beta.5;
- no config contains an Application Password or static MCP token;
- no credential-bearing config can be written or downloaded from the public WordPress root;
- all advertised client claims match the recorded manual matrix;
- Claude Code is explicitly described as preview/configuration-only until its CLI flow is qualified;
- the beta.3.12 to beta.4 updater test passes;
- every acceptance write returns an audit or backup identifier and a verified rollback.

Published npm versions, Git tags, and release assets are immutable. If a post-release defect appears, retain beta.3.12 and publish a new corrective version rather than rewriting beta.4 or beta.5.
