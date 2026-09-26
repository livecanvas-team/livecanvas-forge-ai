# Private conversations and worker requests

Development build `0.2.0-beta.8-dev.5` uses a dedicated private-record table for conversations and frontend requests. Each record belongs to a verified WordPress user and site. The local MCP connection must have an identified owner. Ownerless legacy credentials cannot read private conversations or claim requests.

Payloads are encrypted with AES-256-GCM. Authentication binds the ciphertext to its site, owner and record identity. Authentication-salt rotation makes old encrypted records unreadable, so keep salts and the database together in private backups. Database administrators remain inside the trust boundary.

## Existing history

The old `lcfa_command_threads` and `lcfa_agent_requests` options remain unchanged. Their contents are not assigned to the first user who opens Bridge. They are excluded from the private chat API because the old format did not prove ownership. An empty new chat does not mean the old history was deleted. A reviewed migration and recovery interface is still pending.

Private conversation edits use a revision comparison in the database. Concurrent appends retry against the current record and preserve both messages. The new store retains history beyond the old 80-message cutoff. Its current record limit is 16 MiB; exceeding it returns an error without replacing the conversation. The index currently returns up to 200 recent records. Pagination and retention controls remain pending. Deleting a conversation in the current UI archives its private record; clearing a conversation removes its messages.

## Request lifecycle

1. The browser submits a prompt with an idempotency key. Repeating the same delivery returns the existing request and does not add another user message. Reusing the key for different content is rejected.
2. A worker uses `POST agent/request/claim`. The database assigns a queued request to one worker. `GET agent/request` only reads a specified request; it never starts work.
3. The claim returns a private lease capability to the worker. The bundled MCP runtime retains it in memory and renews the 120-second lease every 30 seconds. It removes the capability from model-visible tool output.
4. Completion requires that capability and the same authenticated connection. Retrying an identical completion does not add another result message. A different terminal result is rejected.

An expired worker appears as `needs_attention`. Bridge does not automatically hand the same request to another worker, because the original worker may have already changed the site. Inspect the result and its changesets before submitting another operation. Acknowledged recovery for lost workers and desktop streaming bindings still require implementation.

## Stop and conversation edits

Use Stop in the current conversation. A queued request becomes `cancelled` before a worker can claim it. A running request becomes `stop_requested`. The UI waits for the worker to finish any already-running Bridge tools and acknowledge Stop. Network errors keep the result unconfirmed and allow another check. Reloading the editor reads pending requests from the private API so Stop remains available.

The current runtime checks its lease before each tool. WordPress also binds the worker session to its claimed request. Writes require that request's current lease; omitting the headers cannot bypass a stopped or expired request. The runtime keeps capabilities out of model output. Once Stop is acknowledged, the runtime requires a different explicit request ID before accepting new frontend work. The session write fence persists across runtime restarts.

`stopped` means the Bridge worker acknowledged that its in-flight Bridge tools finished. It does not prove that the coding agent stopped thinking or that shell, database or other external operations ended. Check the agent itself. Bridge cannot roll back earlier changes merely by stopping. Use reviewed changesets and Undo where appropriate.

Clear and archive are refused while the conversation has unfinished work, including an expired worker whose result is unknown. Terminal requests remain private records. Clearing advances the conversation's message generation, so a delayed result cannot repopulate its messages. Archived conversations reject new work. Lifecycle checks fail closed when an unfinished-state index reaches 1,000 records; queue pagination and recovery tooling remain pending.

An uncertain prompt delivery retains its idempotency key while this editor instance remains open. Retrying that same prompt does not create another request. Reload recovers server-side unfinished work, but does not preserve the browser's unsent prompt or an unacknowledged delivery key. Review recovered work before sending again.

The lease controls request ownership. It does not grant permission to change pages or files, and it does not replace target-specific write context. Bridge's direct-write safeguards still apply. Shell commands and direct database operations outside Bridge are outside that boundary.

## Client update and qualification

This development build uses `post_claim_lease_v2`. Re-run the connection instructions and reload the coding agent so it uses the bundled `0.2.0-beta.8-dev.5` MCP runtime. Older runtimes cannot claim through GET or write for a bound job without its lease headers. This is an unreleased development package.

Codex frontend submission remains gated until a verified binding to the same desktop conversation exists. Private storage and worker leases do not establish that binding. An independent CLI process would require separate qualification and would not satisfy the desktop-conversation requirement.

The integration fixture `tests/integration/private-chat.php` uses only local PHP workers on the two authorized sites. It checks concurrent messages, one-worker claims, duplicate deliveries, owner isolation, invalid and expired leases, and duplicate completion. It removes only its fixture records. It does not invoke a coding agent or send prompts to a model provider.

## Protecting open editor changes

Save your LiveCanvas edits and close its code panel before sending a request. Bridge checks the actual native editor buffer, including pending Ace input and rich-text editing. An unavailable or changed editor state blocks Send. This guard never sends the unsaved page HTML with the prompt.

If you edit while a request runs, Bridge keeps your editor buffer. Use **Review saved page** to open the saved version in a separate tab before deciding what to keep. Do not save the older local buffer over an agent's changes without comparing them. This page-level warning stays visible when you switch conversations.

Automatic refresh requires a matching page target, a clean original buffer and a successful apply result. For agent requests it also requires a committed, owner-scoped Bridge content changeset bound to that worker request. WordPress checks that the target still matches the saved journal snapshot. Agent-authored `saved: true` is not proof. Later edits or Undo invalidate that evidence. A recovered request has no original in-memory buffer baseline and cannot automatically replace the editor. Bridge checks again after the page fetch, immediately before replacing the preview.

Result details distinguish saved, compiled, visually checked and published. Missing checks say **Not checked**; an agent's claims are labelled **Agent reports**. Completion alone does not mean compilation, visual verification or publication succeeded.

The guard covers this chat's send and automatic-refresh paths. It does not add conflict resolution to LiveCanvas's own Save button, coordinate every other open tab, or govern arbitrary shell/database writes. File-only and compiler-cache changes do not yet provide automatic-refresh evidence. Actual agent-driven native refresh still requires end-to-end qualification.
