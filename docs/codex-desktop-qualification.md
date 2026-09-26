# Codex Desktop connection qualification

The frontend is not connected to the same Codex Desktop conversation. MCP connectivity, an installed executable and an available App Server are separate checks. None of these proves that a frontend turn will appear in the Desktop conversation selected by the user.

The September 26 experiment reached a concrete compatibility limit in app `26.917.71314` (build `10954`). The startup opt-in was correctly set, but Desktop's local connection always supplies an internal configuration override, while its daemon transport requires no overrides. The app therefore retains its own stdio server. Further identical restarts cannot qualify this path. The temporary local service has been stopped. Do not alter the app or disable its tools to bypass that condition. Details and the required product decision are in `docs/desktop-experiment-checkpoint.md`.

## Evidence recorded on September 25, 2026

The installed executable reports `0.155.0-alpha.16.4`. The initial `app-server daemon version` diagnostic could not reach the default control socket. After the approved experiment described below, the temporary service answers the diagnostic and the Unix handshake. Desktop attachment remains unverified.

Read-only inspection of the installed application's shipped JavaScript found a local daemon connection branch. The branch requires an internal opt-in as well as a compatible daemon and additional runtime conditions. Its fallback is the app's own stdio transport. This identifies an experimental investigation path; it does not establish a supported WordPress integration or prove that the branch is active. Bridge must not toggle that setting, restart Desktop or alter another active conversation automatically.

OpenAI documents App Server thread IDs, turn streaming and approvals, including Unix-socket connections. The page does not establish that an independently started server shares a running Desktop conversation. Its App Server command and WebSocket transport are experimental. See [Codex App Server](https://learn.chatgpt.com/docs/app-server).

OpenAI's Remote setup connects authorized ChatGPT devices and gives them access to the host's tools and credentials. It is not documented as a WordPress browser endpoint. Bridge does not enable Remote or use its credentials. See [Remote connections](https://learn.chatgpt.com/docs/remote-connections).

## Repeatable diagnostic

From the repository root, run:

```sh
node tests/integration/codex-desktop-preflight.js
```

The default executable is the one bundled with the macOS ChatGPT app. `LCFA_CODEX_BINARY` can identify another absolute executable path for a developer-run check. The diagnostic executes only `--version` and `app-server daemon version`, with a five-second timeout for each. It does not enumerate conversations, resume a thread, send a prompt or enable a daemon. Provider API-key environment variables are not forwarded. Output excludes raw errors, filesystem paths, account fields and unrelated daemon response fields.

The diagnostic exits with code 2 because it cannot qualify a Desktop conversation binding. Configuration errors exit with code 1. A failed daemon probe is reported as `daemon_probe_unavailable`; this includes a missing socket, permission errors, timeout and malformed responses. Do not interpret it as proof that Desktop is closed or the account is signed out.

A successful daemon response yields `desktop_binding_unverified`. Both `desktop_conversation_verified` and `frontend_chat_available` stay false. The diagnostic is test tooling, not a new production adapter, and is not included in the plugin distribution.

Regression command:

```sh
node --test mcp/tests/codex-desktop-preflight.test.js
```

Five tests cover the fixed diagnostic command allowlist, credential-free environment, bounded calls, redacted failures, malformed versions and the refusal to promote a daemon response into verified Desktop chat. Synthetic responses do not qualify an actual Desktop connection.

## Authorized dedicated conversation

After explicit user approval, the Codex app created the projectless conversation **LiveCanvas Bridge Desktop test**. Its first turn returned the requested handshake marker. Subsequent scoped messages asked only about already-available Bridge tool metadata. These turns completed in the same dedicated conversation through the app's task-control tools. This proves that the dedicated app conversation works; no message originated from the WordPress frontend.

The conversation reported no exposed LiveCanvas tools. The inspected turn record contains no actual Bridge MCP invocation or metadata-discovery tool result, so MCP availability and execution remain unqualified. No WordPress content or credentials were sent to that conversation. Its prompts contained only the two authorized local hostnames and the test instructions. Existing personal conversations were not inspected.

At the time of the dedicated conversation test, the default daemon socket was absent. Read-only inspection confirmed the internal startup opt-in and compatibility checks. The later local-service experiment is recorded below. This is an experimental path and is not yet an implementation that can be shipped to users.

## Startup experiment authorization

The user subsequently approved the proposed temporary local service and Desktop restart. The scoped experiment and recovery checkpoint are in `docs/desktop-experiment-checkpoint.md`. This approval does not enable Remote, authorize permission changes or make the transport available to plugin users. Continue with the dedicated test conversation only. Do not list personal chats or copy their history. Restore the ordinary startup if qualification fails.

The experimental adapter remains unavailable until this sequence passes:

1. Confirm that Desktop and the local helper use the same running host and exact authorized conversation ID. A shared history file or equal preview text is insufficient.
2. Bind that conversation to the verified WordPress site, user and session. Reject another origin, owner or conversation ID before reading events or sending a turn.
3. Send one scoped frontend prompt and observe it in Desktop with the same turn ID. Stream only that conversation's public messages and progress; exclude private reasoning and unrelated events.
4. Verify approval prompts, model selection, Stop and reconnect. Do not change the user's permission settings. Reconnect must reconcile the known turn and must not replay a prompt whose delivery is uncertain.
5. On isolated draft fixtures, exercise the Bridge workflow and current write context on Picostrap and Picowind. Confirm editor-buffer protection and distinguish saved, compiled, visually verified and published results.
6. Revoke the WordPress session and verify that the local helper stops disclosing events and accepting new requests. Preserve Desktop's own history and disclose the limits of cancellation outside Bridge tools.

Only after these checks may the frontend show the Desktop chat as connected. If the installed app cannot support this binding, ask the user to choose a different product scope. An independent CLI or App Server chat would be a scope change.

The internal single-thread protocol component and its synthetic tests are documented in `docs/codex-thread-protocol.md`. It remains disconnected from production entry points and does not change this qualification gate.

The private delivery journal is documented in `docs/desktop-delivery.md`. Real WordPress integration tests verify recovery after a helper restart with a synthetic Codex transport. Durable storage does not replace the same-conversation proof or authorize a live Desktop experiment.

## Local Unix handshake diagnostic

The approved temporary service starts from the existing bundled executable with `app-server --listen unix://`. Managed `daemon start` failed because this app bundle lacks a complete standalone package; no new package was installed. The service has a private Unix socket and no TCP listener. The computer-control tool denied access to the Codex app for safety reasons. Desktop was not restarted through another automation path. The user must perform that step manually before same-process qualification can continue.

Run the read-only diagnostic against an already-running, explicitly authorized service:

```sh
node tests/integration/codex-unix-handshake.js
node --test mcp/tests/codex-unix-handshake.test.js
```

The diagnostic sends only `initialize` and `initialized`. It checks private ownership of both the canonical socket directory and the resolved socket, supports Codex's private short-path alias, and rejects a changed socket before sending initialization. The handshake has a five-second timeout, a 64 KiB frame limit and no redirects or compression. Errors and server metadata are not printed. No conversations, credentials or model prompts are requested.

The actual handshake passed on the authorized service. Twelve synthetic regressions cover the command boundary, private paths, malformed/duplicate frames, send failures, timeout, socket replacement and startup notifications. The diagnostic discards at most eight `account/updated`, `remoteControl/status/changed`, `configWarning` or `warning` notifications without retaining their bodies. They cannot replace the initialization response. Requests, unknown notifications and floods still fail closed. Success still exits with code 2 because it cannot qualify Desktop attachment. The diagnostic is excluded from the plugin package and does not activate frontend chat. The internal WebSocket helper from the existing Playwright test dependency is used only by this diagnostic; it is not a production transport dependency.

The first September 26 restart had no local-daemon startup flag. A metadata-only read of the dedicated test thread returned `notLoaded` from the local service, while the app's own task tools reported `idle` after its new test turn. The subsequent restart had the correct flag and exposed the override incompatibility described above. The same persisted thread ID does not establish a shared running host. No further restart is requested for this app build.
