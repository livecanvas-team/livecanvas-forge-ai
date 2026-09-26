# Codex thread protocol component

`mcp/src/codex-thread-protocol.js` implements a single-thread App Server protocol client. It is not imported by the HTTP listener, MCP entry point or WordPress plugin. It does not open sockets, launch Codex or create conversations. Development build dev.8 packages the component with production delivery still disabled.

The implementation was checked against [OpenAI's App Server documentation](https://learn.chatgpt.com/docs/app-server) and JSON schemas generated locally by the installed `0.155.0-alpha.16.4` executable. No model request was sent while generating those schemas. Tests use a synthetic message transport.

## Internal contract

The controller supplies an exact `threadId` and an asynchronous `authorize` callback. A private `deliveryJournal` is mandatory. Its `pending`, `reserve` and `record` methods must use durable storage; no in-memory fallback is provided. See [Desktop delivery journal](desktop-delivery.md) for the PHP service and recovery rules.

The supplied transport has EventEmitter `message`, `close` and `error` events, a synchronous `send(text)` method and `close()`. It must report send failures by throwing or emitting `error`/`close`. Each connection attempt needs a fresh transport object. Transport authentication is outside this component.

The authorizer must validate the actual Desktop conversation binding and the current WordPress site/user/session. It runs before connecting, before prompt delivery, before Stop and before an event reaches the consumer. A denied, failed or timed-out check revokes this instance. The production binding issuer and authorizer are not implemented. A test callback that returns true is never acceptable production evidence. `status().desktopConversationVerified` always remains false.

The handshake uses `initialize`, `initialized` and `thread/resume` with `excludeTurns: true`. It never lists conversations. Responses must identify the bound thread. Resume does not override the model or permission settings. `startTurn` accepts only a request ID, prompt text and optional model/effort choices. It does not accept shell commands, permission overrides, another working directory or another thread ID. A fresh metadata read must show an idle thread before delivery.

Events contain public agent text and bounded lifecycle fields for the bound thread. Other thread events, reasoning payloads, raw tool results and raw server error messages are excluded. Consumers must render public text as untrusted text, not HTML. Approval events identify the pending request without exposing its command or credentials. The user must review approvals in Desktop. This component never answers them or changes approval policies. Actual routing with a second Desktop client remains unqualified.

## Delivery, Stop and reconnect

Before sending a prompt, the component reserves its WordPress request ID in the private journal with a SHA-256 digest of the exact protocol input. Only a freshly stored and verified reservation grants one send attempt. An ambiguous send is never replayed automatically. Reusing an attempted request ID is rejected, including after a helper restart. Connection setup reads pending delivery state before the protocol handshake and checks authorization again afterward.

The component serializes its own submissions and rechecks events arriving during authorization. App Server does not provide a documented atomic idle-thread reservation here; another client can still race between the last check and `turn/start`. That cross-client behavior needs live qualification. The journal does not lock another Desktop client or prove which client initiated a native turn.

Acknowledged turn IDs and terminal outcomes are stored before reporting the corresponding result. A completion notification that arrives before the start response stays private until the response matches its turn ID and the terminal receipt is saved. The `confirming` phase identifies terminal storage still in progress. Storage failure leaves delivery uncertain. A fresh authorization check and transport-generation check prevent output disclosure after revocation or disconnection during storage. A saved terminal receipt confirms only the native turn outcome; site changes still need their own evidence.

Stop targets the known turn ID. An RPC acknowledgement means cancellation was requested. Only an observed `interrupted` outcome confirms interruption. Natural completion is reported as completion, including when it races Stop. Bridge's existing server-side write fence remains a separate requirement. Interrupting Codex does not establish rollback or cancellation of arbitrary external tools.

After a dropped connection, the controller can supply a fresh transport. A known turn requires explicit reconciliation through `thread/read` on the same thread. The client uses only that turn's ID and terminal state; it does not return the history payload. A missing turn or an unacknowledged start with no known turn ID requires review. The client must not infer delivery from similar history text. Terminal events take precedence over older response snapshots.

The durable journal is implemented in WordPress and exercised through a test-only PHP adapter. A new helper process recovers pending delivery without permission to replay it. Production integration still needs an authenticated controller route and a verified Desktop binding. Session replacement, ambiguous-delivery release and retention require reviewed recovery policies. No automatic reconnection loop is implemented. Model discovery, paginated transcript hydration and frontend approval controls also remain open.

## Bounds and tests

Frames are limited to 2 MiB. The event queue has a 4 MiB limit and at most 128 entries. At most four RPCs can be pending. Authorizer, RPC and event-consumer timeouts are bounded. Overflow disconnects the transport and retains an uncertain active turn. Late events from a detached transport cannot affect a replacement connection. Stored request IDs and approval records also have limits.

Run the synthetic protocol tests:

```sh
node --test mcp/tests/codex-thread-protocol.test.js
```

The 41 tests cover exact-thread metadata, permission-preserving payloads, public event filtering, Desktop approval notices, Stop outcomes, lost acknowledgements, reconnect reconciliation, revocation, malformed frames, queue limits and asynchronous races. Delivery tests cover process restart, storage failures, early completion, conflicting turn IDs and private receipts. The full Node suite also covers the existing Bridge security and compiler paths. These tests do not prove a connection to the installed Desktop app or a working frontend chat. Follow `docs/codex-desktop-qualification.md` before connecting this component to a production route.
