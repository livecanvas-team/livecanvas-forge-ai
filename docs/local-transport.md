# Authenticated local transport

The development HTTP listener requires a current, owned WordPress session with Full Access and the matching MCP runtime version. It binds only to `127.0.0.1`. It does not connect the editor to a Codex desktop conversation. The desktop chat gate stays closed until that separate integration is qualified.

Normal coding-agent setup continues to use the generated installer and MCP stdio. Do not ask users to provision local transport secrets. Automatic browser pairing and a bundled desktop conversation adapter remain unfinished.

## Developer contract

An integration must supply `LCFA_BRIDGE_TOKEN_FILE` or `--bridge-token-file` before starting `--transport=bridge`. The capability is a JSON file outside the WordPress root, owned by the current OS user, with mode `0600` in an owner-only directory (`0700`). Symlink files and hard-linked files are rejected. Windows permission validation is not qualified and this listener refuses Windows startup.

The JSON fields are `version` (1), `token` (32 cryptographically random bytes encoded as base64url without padding), `expires_at` (Unix seconds, at most one hour ahead), `session_id`, `site_fingerprint` and integer `owner_user_id`. Identity fields must match the approved WordPress session. Never put the token in a URL, model prompt, log, screenshot or public web directory. An eventual browser pairing flow needs its own authenticated delivery protocol; this developer contract does not supply one.

WordPress exposes `GET /lcfa/v1/mcp/transport-identity` only to an owned session in the `X-LCFA-MCP-Session` header. Administrator cookies, legacy tokens and query-only tokens do not authorize this endpoint. It returns the bound site, canonical WordPress root, owner, session and required runtime version without credentials or project labels.

Every actual HTTP request requires `Authorization: Bearer` with the local capability. Host and remote address must match the loopback listener. Browser Origin must exactly match the verified WordPress origin. Preflight is limited to that origin, GET/POST and the Authorization/Content-Type headers. Query strings are refused. The listener rechecks the private file and WordPress session before every request, and again after reading a tool-call body. Revocation, identity changes or unavailable verification fail closed. A running listener never silently re-pairs. Restart with a newly verified binding after resolving the problem.

## Endpoints

| Endpoint | Result |
| --- | --- |
| `GET /health` | Authenticated listener status; `desktop_conversation_verified` remains false |
| `GET /tools` | Explicit reviewed subset of site tools |
| `POST /tools/call` | JSON object with `name` and optional object `arguments` |

Local asset discovery, browser automation, worker claims, compilers and generic command execution are excluded. Newly added registry tools are excluded until reviewed. Old passthrough HTTP routes return 410. The former WebSocket endpoint is disabled. JSON bodies are limited to 8 MiB and four active requests. Errors omit raw filesystem paths and tool exceptions.

Session telemetry and revocation use database compare-and-swap updates. Authorization reads bypass the options object cache. A delayed heartbeat cannot replace a newer revocation or another newly approved session. Legacy ownerless sessions are not reassigned or accepted for this local listener.

Bridge guards govern Bridge tools. Arbitrary shell commands, direct database edits, another plugin or a compromised administrator can operate outside those guards. Local capability possession does not establish the identity of a Codex desktop conversation.
