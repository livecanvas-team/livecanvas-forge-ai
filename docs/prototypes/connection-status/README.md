# Connection status prototype

Interactive design proposal for the existing AI Bridge Connect screen. All site names, codes and connection evidence are synthetic. It sends no requests to WordPress, runs no installer, grants no permissions and changes no production plugin files. Clipboard actions copy clearly marked sample text. Claude Code uses the sample session name `Hello Alfred`.

Serve this directory locally, then open `index.html`. From the repository root:

```sh
php -S 127.0.0.1:8768 -t docs/prototypes/connection-status
```

The startup prompt is visible on arrival, with a clipboard button beside its label and one destination sentence underneath. The progress steps and repeated explanations were removed at the user's request. Diagnostics live in “Details & help”. The collapsed “Preview controls” below the screen contains scenarios and simulated agent responses; these controls are excluded from production. The interface keeps the existing dark palette and cyan controls. English is the default language.

## Proposed behavior

| Status | Meaning | Next action |
| --- | --- | --- |
| Red: Not connected | No verified connection for the selected agent | Copy setup instructions |
| Amber: Setup needs your action | Automatic copy failed; instructions are available | Select and copy visible text |
| Amber: Waiting for agent | Instructions exist; no agent request received | Continue in the named agent or terminal |
| Amber: Approval needed | A site-bound request is waiting for consent | Compare code and review Full Access |
| Amber: Waiting for verification | Approval completed; authenticated proof is missing | Restart/reload the agent and request verification |
| Green: Connected and verified | Latest authenticated check matches site/session and required capabilities | Start a read-only test |
| Amber: Update required | Observed runtime lacks required capabilities | Update setup and verify again |
| Green: Compatible update | A separate update is available, but current compatibility is verified | Continue working; review update separately |
| Amber: Check needed | Last success is old; current reachability is unknown | Ask the agent for fresh verification |
| Red: Check failed | A real attempted check failed | Show exact error and scoped recovery |
| Red: Access removed | Session revoked or authorization removed | Obtain new explicit authorization |
| Amber: Setup expired | Setup attempt expired before completion | Generate fresh instructions |

Status never relies on color alone. Each state has a light and a short title. Clipboard success says “Copied!”; failure selects the visible prompt and shows the platform shortcut. Feedback persists per agent. Copying and approval cannot mark a connection green. Claude Desktop's setup command is intended for Terminal or PowerShell; verification prompts go into its chat. Full Access details remain visible during approval.

## Implementation handoff

This proposal requires backend work before it can be shipped. It does not claim these checks already exist in beta.6.

- Extend `LCFA_Connection_Attempt::public_status()` with machine-readable reasons, observed and required runtime/capabilities, verification time and explicit evidence provenance. Preserve ownership checks and redact tokens, session IDs and local filesystem paths from default UI.
- Replace the single `reconnect_required` message with separate compatibility, revocation, owner-permission, expiry and site-mismatch reasons. Do not diagnose an update from an unknown network error.
- Introduce a reviewed compatibility contract. Beta.6 currently compares exact package versions. The proposed compatible-update state must come from an explicit compatibility decision, never from a guessed semver relationship. A fresh handoff for this site/session must verify `get_write_context` support where required.
- Decide a verification freshness window with the product owner. The two-day stale example is illustrative, not a proposed timeout. Last-seen activity alone is insufficient proof of capabilities. No telemetry can prove an idle desktop app is continuously online.
- Keep monitoring the current state at a bounded cadence while the screen is visible, even after initial success. Revalidate on visibility and plugin/runtime changes. Network failure changes the UI to unknown/check-needed until there is evidence for failure. Expired setup must not erase an unrelated authorized connection.
- Track displayed status per client and session, with an explicit session choice when one client has multiple sessions. The prototype remembers only per-agent demo state in memory; it does not resolve production session selection.
- Keep copy feedback separate from connection status. Use `navigator.clipboard` when available, with a visible selected-text fallback on local HTTP or permission denial. A fallback must name the destination and keyboard shortcut. Do not auto-clear it during polling.
- A connection check must wait for a real authenticated client response. The WordPress page alone cannot inspect an agent's current process. Do not expose a green toggle or reproduce prototype simulation buttons in production.
- Keep access review visible. Repairs must preserve scopes where possible. Any broader access or new session requires informed approval. This prototype's cancellation does not implement a server-side revoke; production must define cancellation of a pending attempt separately from revocation of an authorized session.
- English strings must use WordPress translations. Announce meaningful state changes through a polite live region without moving focus on polls. Preserve manual selection and keyboard focus during rerenders.

## Verification

Run `node docs/prototypes/connection-status/verify.cjs` with the local server running and the repository's Playwright dependency installed. It tests 12 states, five clients and five widths, plus exact copied text, clipboard fallback, consent and verification transitions. Current screenshots and JSON evidence are under `.impeccable/review/connection-status/minimal/`. Captures in the parent folder document the earlier, longer design.

These checks qualify the prototype's UI behavior only. Installation, WordPress permissions, compatibility detection and authenticated MCP transport need integration tests after production implementation. No release or deployment is included in this proposal.
