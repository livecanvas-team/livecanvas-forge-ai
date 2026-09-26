# Desktop delivery journal

`LCFA_Desktop_Delivery` prevents a Bridge controller from receiving a second send grant for the same WordPress request. Its records survive a helper restart. The service is internal: no REST, Ability or browser route exposes it. It does not establish a connection to Codex Desktop. Development build dev.8 includes this component with production delivery still disabled.

The caller needs a current, owned Codex MCP session with Full Access. An administrator cookie alone is insufficient. The future controller must separately verify the exact Desktop conversation and its binding to the WordPress site and user. Storage records are not proof of that binding.

## Controller contract

| Method | Input | Result |
| --- | --- | --- |
| `reserve` | Queued WordPress request ID, native thread ID, SHA-256 input digest | A new, verified reservation grants one attempt and returns a private receipt. A duplicate never grants another attempt. |
| `pending` | Native thread ID | The unfinished reservation or acknowledgement, including its private recovery receipt. No send permission. |
| `record` | Request ID, native thread ID, receipt, turn ID and status | Stores an acknowledgement or terminal outcome. The turn ID cannot change and a terminal outcome cannot regress. |

The Node `deliveryJournal` adapter must preserve these semantics. `reserve` and `record` accept objects; `pending` accepts the native thread ID. The adapter used by integration tests passes inputs through private process pipes to PHP. It is test tooling; production controller authentication and routing remain unimplemented.

The reservation requires an owned, queued Codex request and an active conversation with the same message epoch. A per-owner, per-site native-thread lock prevents competing reservations within that scope. The request-scoped primary key also prevents reserving the same request against a different native thread. The future binding issuer must prevent the same native conversation from being shared across unrelated owners or sites.

Records use the existing private InnoDB store with AES-GCM encryption and authenticated site/owner identity. The new record kind is `delivery`; it fits the existing schema. Records contain an input digest, session hash and private receipt, without another copy of the prompt. Receipt tokens must never appear in browser responses, logs or screenshots. Normal chat and request listings exclude delivery records.

## Failure and recovery

A lost reservation response can leave a committed record even when no prompt was sent. Bridge preserves that record and refuses automatic replay. Starting a replacement helper does not remove the restriction. A pending record without a confirmed native turn ID requires review; matching conversation text is insufficient evidence of delivery.

With a confirmed turn ID, the protocol component can reconcile that exact turn after reconnect. The journal checks the original WordPress session. Revocation or a different session prevents access. There is no automatic session rebinding, reservation release or expiry that permits resending. A recovery UI and retention policy remain open work.

`reserved`, `acknowledged` and `settled` describe delivery only. They do not claim a queue worker, complete its request or prove page changes. The response keeps `desktop_conversation_verified` and `site_changes_verified` false. Bridge's worker lease, Stop write fence and mutation checks remain separate requirements. Arbitrary shell and database writes outside Bridge tools are outside these protections.

## Verification

From the repository root:

```sh
php tests/php/desktop_delivery_phase1.php
node --test mcp/tests/codex-thread-protocol.test.js
```

`tests/integration/desktop-delivery.php` is restricted to the two approved local WordPress fixtures. It loads the working-tree plugin only inside its CLI processes and leaves the installed plugin and activation option unchanged. Supply `LCFA_TEST_HOST`, `LCFA_TEST_WP_ROOT` and the site's PHP MySQL socket configuration. It requires an existing administrator and private-store table.

The integration creates temporary sessions and private fixture records. It tests concurrent reservations, lost database acknowledgements, receipt recovery and session isolation. A Node process exercises the real PHP journal with a synthetic Codex transport. Cleanup removes only the fixture records and sessions. Before/after checks compare existing posts, metadata, private records, sessions and plugin activation. These tests send no provider prompt and do not qualify the actual Desktop conversation.
