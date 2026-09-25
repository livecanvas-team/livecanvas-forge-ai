# Unified connection implementation status

Work in progress, 25 September 2026. The user confirmed Full Access for the five primary clients on test-ai-forge.local during the renewed test run. All five connection attempts were approved. Codex CLI, OpenCode Desktop and Claude Desktop completed authenticated MCP reads, show Connected and created About us drafts 141, 139 and 140 respectively. Cursor reached its account usage limit after installation; Claude Code is installed but not authenticated. The public English guide has a labelled beta.5 preview; new screenshot/GIF assets remain pending. The release is not yet qualified for delivery; the remaining gates below are still open.

## Implemented

- Shared PHP/Node agent registry. Canonical Claude Code and Claude Desktop identities coexist with the legacy Claude target. Settings, session provenance, bundle identity, connection testing, operational audit attribution and both PHP bootstrap builders use the registry. The older project-setup forms and some manual recovery branches still need migration.
- Node `livecanvas-forge-connect` installer. One descriptor and installation path for the five declared clients, with per-client configuration schemas. It merges JSONC and managed TOML, preserves unrelated servers, creates backups, handles conflicts and avoids credentials in configuration or stdout.
- Connection attempts bound to WordPress owner, client, site fingerprint, consent scopes, authenticated session and package version. Pending consent is separate from a verified handoff. Concurrent attempts, outdated versions, wrong sites and revoked sessions cannot complete one another.
- Full Access for newly approved attempts is session-scoped. Command policy and Power Mode use that context; previous global policy and old session scopes are unchanged. New sessions check the owner's WordPress capabilities on use.
- Bounded login polling, isolated cache per attempt, POST device-secret polling for new attempts, request timeouts and credential-safe redirect refusal. The helper requires client reload/handoff and does not mark MCP connected merely by authenticating.
- Unified connection screen, consent code, automatic status refresh, clipboard fallback, Desktop terminal instructions, retry and manual recovery path. Activation/default page now opens Connections before project setup.
- Plugin version `0.2.0-beta.5`, runtime `0.2.0-beta.6`. Build script packs the runtime archive into the plugin's assets and includes its parsers. The generated command uses that site's archive instead of depending on npm publication.

## Evidence so far

- The pre-UI full PHP/Node/JS suite passed, with the existing visual E2E test skipped by its opt-in flag.
- The post-UI PHP suite passed after updating the intended default-tab expectation.
- New PHP tests passed for registry identity, attempt isolation/consent/expiry/revocation and screen descriptors.
- New Node tests passed for configuration preservation, the five primary client installers, credential redaction and authorization polling.
- New JS test passed for copy, explicit approval, automatic readiness, Desktop behavior and clipboard fallback.
- The complete PHP/Node/JS suite passed after the latest audit/bootstrap corrections. The opt-in visual E2E remains skipped. The beta ZIP was rebuilt and `tests/php/package_dist_phase1.php` passed. The artifact is not a substitute for real installation testing.
- The user selected `test-ai-forge.local` and requested real tests in Claude Desktop, OpenCode and Cursor, including draft About us pages. WordPress's ZIP upload/update succeeded from plugin beta.4 to beta.5. Existing settings and connections were preserved. The previous plugin files were backed up locally.
- The packed runtime was installed through npm in an isolated temporary folder. Its executable bin and config merge were exercised from the installed package, with mocked approval and no real credentials. This is package evidence, not app qualification.
- The initial OpenCode, Cursor and Claude Desktop requests expired while waiting for consent. They were renewed during the next run. The user then approved Full Access for all five primary clients. Real OpenCode and Cursor sessions executed the generated installer in separate temporary test workspaces; Desktop's generated command ran in Terminal. Codex and Claude Code commands also ran in separate test workspaces. All five reached approved state. Authenticated reads succeeded in Codex CLI, OpenCode Desktop and Claude Desktop after their configuration reloads. Desktop and CLI evidence are recorded separately in the validation report.
- The first browser inspection found irrelevant theme metadata/advisory above Connect on mobile, plus outdated smoke-test copy. The Connect header now omits that metadata/advisory and describes automatic verification. Desktop and the corrected OpenCode attempt at 390 px were confirmed. Mobile document width is 390 px and the attempt reports Connected for OpenCode.
- Multi-client testing exposed a reload issue: user-level last-attempt metadata could reopen another client's attempt. The page now carries an owner-checked attempt ID in its URL; generated verification URLs target the same attempt. Legacy verification URLs open manual connection management. PHP/JS regression tests pass for these changes.
- A failing regression proved that admin operation attribution converted `claude-code` into `codex`. Admin, REST and Command Deck now preserve all registered client/processor identities, normalize aliases and map unknown explicit clients to generic. Historical `claude` attribution remains unchanged. Both bootstrap builders now generate each client's own command/environment rather than aliasing Claude Code to Claude; Desktop and other catalog entries are no longer missing. Tests cover the entire registry, including secret redaction. These PHP changes were deployed to the test site and compared byte-for-byte with the workspace.
- Plugin/MCP READMEs, the shipped HTML guide and beta.5 release notes describe the new installer and qualification limits. The guide uses the same approval-before-verification order for five clients and no longer claims a verified connection from checklist clicks. Its JS regression passes. Browser inspection confirmed the Desktop instructions, checklist disclaimer, desktop layout and 390 px mobile layout without horizontal overflow. The complete suite and rebuilt ZIP pass after these edits. The runtime archive changed only its README; source, bins and manifest match the isolated installation already tested.

## Required next work

1. Keep regression/package checks current after further edits. The latest full suite, ZIP contents and isolated packed-helper checks pass; actual client qualification is still separate.
2. Test the new page in actual WordPress at desktop and mobile widths in one visual pass, followed by at most one correction/confirmation pass. Test consent, reload, handoff, recovery and returning-user state on the selected test site.
3. Qualify the five declared clients with runtime beta.6. The old beta.5 evidence does not prove the new flow. Test Windows command quoting and Desktop configuration on supported platforms; the current environment is macOS.
4. Complete the remaining admin/bootstrap legacy allowlists, Claude alias migration and existing connection behavior. Check request-scoped Full Access against all operational routes and against legacy/OAuth sessions, not merely the scope list.
5. Complete and qualify the additional major clients in the plan. Registry entries/configuration fixtures are not end-to-end support. Windsurf/current Devin and Amazon Q need version-specific adapters; Cline extension differs from Cline CLI. Preserve these distinctions.
6. Extend the existing `--tool` CLI with stable login/status/doctor and outcome codes, add the Forge skill and catalog destinations. An inner pairing/error result must not be wrapped in outer `ok: true`.
7. Qualify native OAuth and cloud routes separately; no public reachability or credential changes are implied. Local/private sites must not be advertised as usable by a cloud client.
8. Measure cold install and reconnection time. The shipped guide, plugin/MCP READMEs and release notes now describe the new flow; update their qualification statements only after real-client and release checks pass.

## Known implementation review points

- The first authenticated Claude Desktop and Codex handoff responses still contained legacy `not_connected` / `smoke_test` fields while the attempt-specific Connect page correctly showed Connected. This can confuse agents after a successful setup. Reconcile the legacy handoff/snapshot status with the authenticated attempt before release; do not ask users to repeat consent to hide this discrepancy.
- Partial administrative roles now receive an explicit error before creating/approving Full Access; capability loss also invalidates the visible ready state. Limited-scope setup remains available manually.
- Cancellation and the overall authorization deadline now abort in-flight fetches. Regression tests cover both cases.
- Windows `cmd` launch now rejects package URLs containing shell metacharacters or encoded characters before editing config. Real Windows qualification is still required; this safeguard does not prove Windows end-to-end support.
- The new helper defaults to REST-backed bridge tools. Local filesystem/build runtime discovery and native OAuth selection still need qualification before claiming complete parity.
- The runtime manifest and build output must stay synchronized. Rebuild the `.tgz` whenever runtime source changes, before measuring or testing the packaged install.
