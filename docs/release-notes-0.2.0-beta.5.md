# Plugin 0.2.0-beta.5: connection update

Runtime: `0.2.0-beta.6`. Status on 25 September 2026: test build for supervised local and staging work. Qualification is incomplete; this is not a production release.

## Connection changes

Activation opens Connect before project setup. Codex, OpenCode, Cursor, Claude Code and Claude Desktop share one screen and the same approval states. The user chooses the agent and copies the instructions. Claude Desktop receives a terminal command because its Chat screen cannot run the project installer.

The installer archive is included in the WordPress ZIP. It merges JSONC or managed TOML configuration, preserves other servers and backs up changed files. It stops on configuration conflicts. The generated command is bound to the selected site, client, runtime version and installation attempt.

Full Access requires explicit WordPress approval for the new session. Its scopes cover content, media, theme files, builds, debugging, cache and SEO, within the owner's WordPress capabilities. Existing sessions and global policies retain their permissions. TLS verification and the agent's own approval settings stay enabled.

WordPress confirms the connection after the client calls `get_connection_handoff` successfully. Approval or installation alone does not complete verification. Each tab retains its own attempt, and expired requests have a restart path. Local HTTP browsers can use the manual-copy fallback.

The shared client registry now also feeds operational attribution and both PHP bootstrap builders. Claude Code and Desktop keep distinct identities; historical Claude records are preserved.

## Verification and remaining release gates

The PHP/Node/JS suite passes, with the opt-in visual E2E excluded. The ZIP passes package checks. The installed runtime package has a separate isolated test with simulated authorization.

The plugin update from beta.4 to beta.5 succeeded on the local test site, preserving existing settings and connections. Full Access was approved for all five clients. This was an upgrade and new-client setup test, not a clean database installation.

- Codex CLI, OpenCode Desktop and Claude Desktop completed authenticated handoff/snapshot reads and created separate English About us drafts. The drafts were inspected at desktop and 390 px mobile widths. No test page was published.
- Codex CLI and Claude Desktop completed validation, dry-run and apply before draft creation. OpenCode's first creation lacked an observed dry-run; a later draft update completed a separate preview, confirmation and apply sequence.
- Cursor completed installation and WordPress approval, but its account usage limit blocked reads and the page test. Claude Code completed configuration and approval, but sign-in remains incomplete. The Claude Code example uses the name `Hello Alfred`.
- Codex Desktop has not been qualified by the Codex CLI result. Windows, Linux and additional catalog clients remain unqualified. Additional client configurations are previews, not end-to-end support claims.

Known issue: early authenticated handoff responses can still contain legacy `not_connected` / `smoke_test` status while the attempt-specific Connect page shows Connected. If these disagree, stop before writes and report both results. Reconcile these states before treating the build as fully qualified.

Native OAuth, cloud access and local filesystem/build parity require separate tests. Older manual setup branches still contain migration work. Rollback was not exercised in this test run. New screenshot/GIF instructions are not complete.

Cold-install and reconnection timings have not been measured. No speed improvement percentage is claimed.
