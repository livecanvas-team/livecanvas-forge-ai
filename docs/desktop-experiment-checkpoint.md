# Local Desktop transport experiment

Date: September 25, 2026. This is a development checkpoint, not a qualified product feature.

Current result, September 26: the correctly restarted app still rejects this shared-daemon path because it always supplies an internal configuration override. The temporary service has been stopped. Do not repeat the restart instructions below; they are retained as the experiment record. The next step requires a product decision about a separate frontend conversation or an app version that supports the required attachment.

The user approved the temporary local service and Desktop restart with “procedi non ti bloccare”, following the explicit restart request. Remote access, network exposure, permission changes and a separate CLI conversation remain outside this experiment.

Use only the existing local conversation **LiveCanvas Bridge Desktop test**, ID `01a0da2c-23f6-75b2-9b62-b9397aa65906`. It is idle after three completed turns. Its first response was `LCFA_DESKTOP_TEST_READY_20260925`. Do not enumerate other conversations or inspect their history.

Both WordPress sites have development build `0.2.0-beta.8-dev.9`. No site change is required for the transport experiment. The repository has existing uncommitted work. Preserve it. There is no current release or push request.

## Before startup

The installed CLI is `/Applications/ChatGPT.app/Contents/Resources/codex`, version `0.155.0-alpha.16.4`. The default control socket and daemon-state directory were absent. No managed standalone or daemon package was installed. Desktop's bundled code contains a local-daemon branch gated by `CODEX_APP_SERVER_USE_LOCAL_DAEMON=1` and other compatibility checks. Its default fallback is stdio.

The proposed one-launch environment variable must not be added to shell profiles, global launch environments or user configuration. A successful socket handshake alone does not prove that Desktop uses the same process.

## Qualification after restart

1. Check the local Unix socket, owner and private directory permissions. Record the exact service PID and executable. Do not enable Remote or bind a TCP listener.
2. Confirm that Desktop actually connected to that service. Stay within the dedicated test conversation.
3. Correlate an authorized test turn with the same thread and turn IDs in Desktop and the helper. Do not equate shared history with live delivery.
4. Keep frontend chat unavailable until site, owner, session and exact-thread binding, cancellation and reconnect tests pass.

## Recovery

If qualification fails, quit the experimental Desktop instance and reopen `/Applications/ChatGPT.app` without the one-launch variable. Stop only the service started for this experiment after Desktop has detached. Do not terminate pre-existing services. Preserve the test conversation and all site content.

The app restart may interrupt the current development turn. Resume from this file and `docs/development-progress-2026-09-25.md`; do not recreate the test conversation or replay an uncertain prompt.

## Execution record

The managed `daemon start` command failed because the bundled CLI has no complete standalone package. No package was installed. The installed executable was then started directly with `app-server --listen unix://`, a filtered environment and detached process PID `66543`. Its working directory is the dedicated test conversation directory.

The read-only preflight now reports daemon version `0.155.0-alpha.16.4` and `desktop_binding_unverified`. The control directory has mode 0700. Its canonical socket is an app-managed symlink whose resolved socket has mode 0600 and is owned by the current user. The service has no TCP listener. Desktop has not yet restarted, no prompt has been sent through this service, and frontend chat remains unverified.

The native computer-control tool refused access to the Codex app for safety reasons. No shell kill, alternate UI automation or forced restart was attempted. A manual user restart is required for the next experiment. The temporary service remains running for this handoff; no automatic startup or Remote setting was added.

The user can quit the app normally, then run this one-launch command in Terminal:

```sh
open --env CODEX_APP_SERVER_USE_LOCAL_DAEMON=1 -a /Applications/ChatGPT.app
```

This requires the app to be fully closed first. It does not persist the environment variable. If the normal app still uses stdio, do not change permission settings or switch to an independent conversation to obtain a passing result. Reopening the app normally restores its ordinary startup path.

`tests/integration/codex-unix-handshake.js` successfully exchanged only `initialize` and `initialized` with the running local service. No conversation was read or resumed. No model request was sent. Its result correctly retains `desktop_conversation_verified: false` and `frontend_chat_available: false`.

## September 26 continuation

Desktop restarted as PID `71678`, with its own App Server child `71746`. The process environment was observable, but `CODEX_APP_SERVER_USE_LOCAL_DAEMON=1` was absent. Only those startup indicators and configuration key names were reported; no environment values or unrelated conversation contents were printed. The approved local service remains PID `66543` with no TCP listener.

The dedicated Desktop conversation completed test turn `01a0dca3-2fee-7e13-ace3-0a708bdd2272`. Its app status is `idle`. The test prompt requested only a fixed marker, with no tools or site access. The task reader returned an empty item list, so the marker response itself was not verified. A separate metadata-only `thread/read` on the local service returned the exact dedicated thread ID with `notLoaded`. That read used `includeTurns: false`, never resumed the thread and sent no turn through the service. The app and local service still have separate runtime state.

An intermittent handshake failure was traced to the diagnostic's rejection of spontaneous startup notifications. The real service emitted `remoteControl/status/changed` and `account/updated`; their contents were discarded. A failing synthetic regression reproduced the ordering problem. The diagnostic now discards a bounded allowlist of startup notifications while preserving all response, ownership and privacy checks. All 12 diagnostic tests and the full Node suite passed. Optional visual E2E remains skipped. The real handshake passed after the fix.

No app restart was forced. The manual command above still needs to launch a fully closed app, not reactivate an existing process. Quit with Command-Q, rather than closing only the window. Keep frontend chat unavailable until the same-host and exact-thread checks succeed. Both sites and the packaged dev.9 build are unchanged.

## Correct startup and compatibility result

The next restart was correct: Desktop PID `74482` had `CODEX_APP_SERVER_USE_LOCAL_DAEMON=1`. The force-CLI and executable-override environment variables were absent. Desktop nevertheless created App Server child PID `74555`.

Read-only inspection of the installed app, version `26.917.71314` (build `10954`), identified the rejecting condition. Its shared-daemon transport requires an empty list from `getConfigOverrides`. Its normal local connection supplies `plugins.codex-app-tools@openai-bundled.mcp_servers.codex_app.enabled` through that list on every startup, regardless of the resulting boolean. The nonempty list forces the separate stdio transport before the daemon-version probe. This explains why the valid environment opt-in is insufficient. No app code, plugin setting or permission was modified to bypass the condition.

The dedicated conversation's previous marker response is now visible through the app reader: `LCFA_DESKTOP_RESTART_CHECK_20260926`, turn `01a0dca3-2fee-7e13-ace3-0a708bdd2272`. No additional prompt was needed for this compatibility check.

The temporary service PID `66543` was sent SIGTERM only after its exact executable and arguments were rechecked. Desktop was not terminated. The service must not be restarted automatically for this failed attachment path. No file or conversation was deleted. The one-launch app environment variable is not persistent and ordinary app startup restores the default environment.

All 58 targeted preflight, Unix handshake and single-thread protocol regressions passed. Both sites and the packaged dev.9 plugin are unchanged. Frontend Desktop delivery remains unavailable. A dedicated Codex App Server conversation would change the requested product behavior and needs explicit user agreement; it must not be presented as the same live Desktop conversation.
