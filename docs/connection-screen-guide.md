# Connecting a coding agent

Open **AI Bridge → Connect** and choose your agent. Its site-bound setup prompt appears automatically. Click **Copy prompt**, then paste it into the named agent's chat. For Claude Desktop, use **Copy command** and run it in Terminal or PowerShell.

When the agent requests access, compare the site and verification code. Check the matching-code box and review **Full Access** before approving. Reload the agent's MCP connection if needed, then use the verification prompt. WordPress turns green only after that client completes an authenticated handoff for this site and session.

If automatic copy fails, the complete text is selected. Press Command+C on macOS or Ctrl+C elsewhere. Polling does not erase the message or change the selected text. Once the workflow moves to a different prompt, old clipboard feedback is cleared.

## Status and recovery

| Indicator | Meaning | Action |
| --- | --- | --- |
| Red, not connected | Setup text exists, but there is no verified connection | Copy the prompt and continue in the agent |
| Amber, waiting or approval | Setup needs the agent or your approval | Follow the current action |
| Green, connected | A matching authenticated handoff succeeded | Use the read-only first-task prompt |
| Amber, update required | The attempt uses a different required runtime version | Update connection to generate fresh instructions |
| Red, access removed or changed | Session access was revoked, changed or no longer matches | Review permissions and start setup again |
| Amber, status unavailable | The status request failed | Retry the same attempt; do not assume it is disconnected |

**Details & help** contains the last verification timestamp, runtime versions and manual connection management. Green describes the last successful authenticated check; it does not prove that a desktop app is continuously online. The screen checks active setup every three seconds and ready connections every 30 seconds while visible. Failed status checks back off to at most once per minute. Returning to the tab requests a fresh status.

Each WordPress user resumes a separate latest attempt for each client. An explicit attempt URL keeps its tab bound to that attempt. Starting setup again does not revoke an existing authorized session. Managing multiple sessions for one client remains in manual connection management.

The existing exact-runtime-version contract is retained. A plugin-only update with the same required runtime does not force reconnection; differing required versions do. No semver compatibility is guessed. Old site/runtime instructions are rejected before pairing or approval and cannot restore a green status.

## Implementation and verification

The compact layout follows the approved Impeccable prototype: selected-agent logo, named status light and visible prompt, with diagnostics disclosed. UI strings use WordPress translations with English source text. Existing plugin SVGs are reused without external requests.

Run the regression suites:

```sh
bash scripts/test-php.sh
bash scripts/test-js.sh
node tests/js/connection_screen_browser.cjs
```

The browser suite renders the actual PHP component with shipped CSS and JavaScript. Its REST responses, site and clipboard are synthetic. It tests desktop/mobile layout, logos, exact clipboard text, denied-clipboard recovery, consent gating, successful proof, incompatible-runtime detection and revocation. Evidence is saved under `.impeccable/review/connection-integration/`.

The tests do not install or authenticate a real coding agent. They do not update MarketingRocks or publish a release. Real-client installation tests and deployment are separate steps.
