# Forge client validation, 25 September 2026

The user authorized Full Access for five clients on test-ai-forge.local. All five requests were approved. Codex CLI, OpenCode Desktop and Claude Desktop completed authenticated MCP reads and created separate About us drafts. Cursor reached its account usage limit. Claude Code is installed but not signed in. The beta.5 release remains unqualified.

## Installation and scope

WordPress's ZIP uploader updated the active plugin from beta.4 to beta.5, with MCP runtime beta.6. Existing settings and connections were preserved. This checks an upgrade and new connections; a clean database installation was not tested. Previous plugin files were backed up locally before the upgrade. The backup excludes the database.

Project clients used separate temporary test folders. Claude Desktop's global configuration was merged with a backup. Existing work projects were not used. Test content is English and explicitly fictional. No test page was published.

## Client results

| Client/version | Setup and read | Draft result |
| --- | --- | --- |
| Codex CLI 0.155.0-alpha.16.4 | Project config installed, folder trusted, consent approved; handoff/snapshot succeeded; Connected | Page 141 after validation and dry-run. First apply was cancelled to resolve an unreadable terminal approval; inventory confirmed no draft, then one approved retry succeeded. |
| OpenCode Desktop 1.18.26 | Agent ran installer, consent approved; restarted and reopened same project; handoff/snapshot succeeded; Connected | Page 139 created. The first creation lacked an observed dry-run. A later one-line CTA update completed explicit dry_run=true, a separate confirmation and dry_run=false, all through the app's MCP tools. |
| Claude Desktop 2.9939.2 | Terminal installer, config merge, consent and restart completed; normal Chat handoff/snapshot succeeded; Connected | Page 140 after validation, explicit dry-run and apply. Tool permissions used Allow once. |
| Cursor 3.16.17 | Installer and WordPress consent completed | Usage limit prevents authenticated reads and page test. |
| Claude Code 2.1.237 | Project config and WordPress consent completed | Auth status reports loggedIn false. Launched with claude --name 'Hello Alfred'; first-run/sign-in is incomplete, so a persisted named conversation is not claimed. |

Codex CLI evidence does not qualify the Codex desktop app. OpenCode CLI 1.18.10 is installed, but this page test used Desktop. Windows/Linux, cloud agents and additional catalog clients were not tested.

## Draft evidence

| Client | Page and slug | Audit | Browser verification |
| --- | --- | --- | --- |
| OpenCode | 139, about-us-opencode-test | audit-a8okob3dryd6 | WordPress Draft/LiveCanvas confirmed. Desktop and 390 px mobile inspected; width 390 px, no broken images. |
| Claude Desktop | 140, about-us-claude-desktop-test | audit-lmaxqwfioukn | WordPress Draft/LiveCanvas confirmed. Desktop and 390 px mobile inspected; width 390 px, no broken images. Hero has an intentional image placeholder. |
| Codex CLI | 141, about-us-codex-test | audit-fcmle7nedakd | MCP apply confirmed draft creation; WordPress's draft count increased and the authenticated browser preview renders. Desktop and 390 px mobile inspected; document width 390 px, no broken images. |

Use the WordPress editor or authenticated ?page_id=139&preview=true URLs with the corresponding IDs. Token-bearing URLs are omitted. Rollback was not executed. Later cleanup should use WordPress Trash.

The OpenCode follow-up changed only the test CTA label to "Explore our demo story". The preview call visibly targeted page 139 with dry_run=true. The separately approved apply call targeted page 139 with dry_run=false and returned audit-b3ecfcdsdcre. WordPress's editor still shows Draft; the authenticated page preview displays the new label. This qualifies the supervised preview/confirm/apply update sequence, without changing the evidence for the initial creation.

The inherited Hearthline header links to homepage section anchors that are not all present on the drafts. It was left unchanged. These tests do not establish production-ready copy, imagery or navigation.

## Findings

- Local HTTP denied automatic clipboard access. Manual copy fallback worked without changing browser protections.
- OpenCode restored a different project after restart. Reopening the test project loaded the generated server; the guide now explains this.
- Claude Desktop and Codex handoff responses contained legacy not_connected/smoke_test fields while authenticated reads succeeded and Connect showed Connected. Reconcile these states before release. The guide tells users to stop before writes if they disagree. These supervised tests verified site identity separately before draft writes.
- Earlier testing found cross-client attempt selection on reload. Owner-checked attempt URLs and PHP/JS regression tests now cover this.
- Earlier tests found Claude Code audit attribution falling back to Codex. Admin, REST, Command Deck and bootstrap builders now use the shared registry; identity and redaction tests pass.
- Connect's unrelated theme notices were removed. The corrected OpenCode attempt was inspected at 390 px: viewport and document width match, the selected client remains OpenCode, and the page reports Connected. No unrelated theme notice appears above the connection form.

## Automated checks

The full PHP/Node/JS suite and package_dist_phase1.php pass. Visual E2E was skipped by its opt-in flag. Tests cover authorization polling, attempt isolation, five-client config preservation and readiness.

An isolated npm installation checked the packed runtime binary/config merge with mocked authorization. Runtime source, bins and manifest match the installed package; its README was updated afterwards. No npm or GitHub release was published.

## English guide and privacy

https://livecanvas.com/bridge-doc/ was saved and verified in a separate browser tab. It distinguishes published plugin beta.4 from the beta.5 setup preview, describes five-client reload differences and includes an English About us draft prompt. Claude Code's demonstration command uses Hello Alfred.

Two older screenshots exposed unrelated tabs or project/site names. Their image elements were removed from the public guide and local fragment. Source files were preserved; copies in the repository/history remain.

New screenshots were captured for inspection, but no new PNG/GIF files have been saved or uploaded. Permission for local screenshot commands is pending. Illustrated instructions are incomplete. Public assets must exclude project/chat lists, usernames, credentials, pairing descriptors and token-bearing URLs. Use English text and captions.

The guide's 390 px check found horizontal overflow caused by grid minimum width. A scoped grid/sidebar correction was saved and verified: viewport and document width are both 390 px. The dark/plum/cyan design was preserved. The mobile navigation labels remain crowded and need a later focused check.

At the first handoff, tab persistence marking reported that the ChatGPT Chrome extension needed an update. On continuation, browser reads and viewport checks worked again, so this is not a confirmed blanket browser outage. A native Chrome action was interrupted by user activity; no further native Chrome actions were sent. PNG/GIF export remains incomplete.

The continuation rechecked Claude Code authentication and Cursor's UI: the missing login and usage-limit blockers remain. The guide's new section, Hello Alfred command and absence of the old screenshot elements were confirmed again in the browser. The web retrieval service returned a two-week-old crawl, which cannot verify the current anonymous page or invalidate the browser's newer content.

A third consecutive goal turn confirmed loggedIn=false in Claude Code and the usage-limit notice plus disabled Send control in Cursor. A native Preview selection-capture attempt produced no usable document or file; it was cancelled. No new screenshot/GIF was uploaded. The outstanding account actions and permission request for local capture commands require user input. Goal completion is blocked, with the full five-client and illustrated-guide scope retained.

## Remaining work

1. Sign in to Claude Code and restore Cursor availability, then test authenticated reads and separate drafts.
2. Test Codex Desktop separately. Codex draft/Connect mobile checks and OpenCode's supervised preview/confirm/apply update are complete.
3. Save and inspect cropped/redacted English screenshots or GIFs, upload them and add them to the live guide.
4. Reconcile legacy handoff status and complete the release gates in unified-connection-implementation-status.md.
