# Local AI Bridge agent onboarding — 2026-08-20

## Test target

- Site: `http://wordpress-theme-test.local/`
- Agent workspace root: `/Users/commander/Local Sites/wordpress-theme-test`
- WordPress root: `/Users/commander/Local Sites/wordpress-theme-test/app/public`
- AI Bridge build under test: `0.2.0-beta.4`
- Bundled MCP package: `@livecanvas/ai-bridge-mcp@0.2.0-beta.5`
- The two roots were treated as distinct throughout the test.

No token, Application Password, or other credential is included in this report.

## Agent matrix

| Client | Configuration and discovery | Handoff / snapshot | Reversible write | Current result |
| --- | --- | --- | --- | --- |
| OpenCode | Passed | Passed | Passed, including rollback | Complete |
| Codex CLI/Desktop runtime | Passed | Passed | Passed, including tombstone-backup rollback | Complete |
| Claude Desktop Free | Passed | Passed | Passed, including rollback | Complete |
| Cursor | Passed; MCP server connected and 76 tools discovered | Passed | Passed, including audit rollback | Complete |

The Cursor configuration is valid: the project MCP server connects, the WordPress pairing is ready, and the project file does not require a static MCP token. Claude Code configuration generation was inspected, but the CLI was not fully qualified because the available account required login or a paid upgrade; it is preview/configuration-only for this beta.

## Cursor + LM Studio experiment

The lightweight local model selected for this Mac was:

- model: `mlx-community/Qwen3.5-2B-6bit`
- API identifier: `qwen3.5-2b`
- local size: approximately 2.22 GB
- LM Studio endpoint: `http://127.0.0.1:1234/v1`

LM Studio served the model successfully. A direct OpenAI-compatible request returned a valid tool call, proving that the model and local endpoint support basic function calling.

Cursor accepted the custom model, the local base URL, and a non-secret placeholder API value. However, Cursor warned that Agent features cannot be billed to a custom API key. In a fresh chat with `qwen3.5-2b` selected, submitting the read-only AI Bridge prompt was stopped immediately with `You're paused until your usage resets.` The request therefore never reached LM Studio or the AI Bridge MCP server.

Conclusion: LM Studio is not a usable free workaround for an exhausted Cursor Agent allowance in the tested Cursor build/account. This is a Cursor execution-policy limitation, not an AI Bridge connection failure.

## Cursor native-agent retest

After reloading the Cursor window and refreshing the account state, a new task started successfully with Cursor's native `Composer 2.5 Fast` model. LM Studio and the OpenAI base-URL override were not active during this successful run.

The read-only check completed both required calls:

- `get_connection_handoff`: `ready`, with transport ready;
- `get_snapshot`: complete runtime snapshot returned;
- site: `http://wordpress-theme-test.local/`;
- active stylesheet: `picowind-child`;
- connection mode: `local` through `local-mcp-bridge`;
- granted scopes: `read`, `preview`, `write`, `media`, `theme_files`, `debug`, `cache`, and `seo`;
- expected and detected MCP package: `0.2.0-beta.5`;
- installed AI Bridge plugin: `0.2.0-beta.4`.

Cursor then completed the reversible file smoke test on `lcfa-cursor-smoke-test.css`:

1. confirmed that the target did not exist;
2. previewed the 83-byte file creation;
3. applied the write and verified the exact content;
4. previewed an audit rollback with `delete_created_file`;
5. applied the rollback and confirmed the file was absent;
6. confirmed that no unrelated theme files, WordPress content, or settings changed.

Non-secret audit identifiers:

- write audit: `audit-zd2hypjbfrta`;
- rollback audit: `audit-udx2duvznfxi`.

An independent filesystem check also confirmed that the temporary CSS file is absent, `.cursor/mcp.json` is mode `0600`, `LCFA_WP_ROOT` points to `app/public`, and the MCP environment contains no token/password/secret/key variable.

## Final release-candidate repetition

The final `0.2.0-beta.4` plugin build, bundling MCP `0.2.0-beta.5`, was installed over the local test site. Updating either component invalidated the previous Ready state until the exact package performed a new handoff and the WordPress smoke test passed.

One Cursor-specific behavior was confirmed: **Reload Window** left the already-running MCP server on beta.4. Opening **Customize → MCPs → livecanvas-forge → Reload** restarted that server and exposed beta.5. The onboarding copy now names this exact action.

Final client results:

- **OpenCode 1.18.10:** the project writer produced a mode-`0600` `opencode.json`, preserved the sentinel configuration during the merge, and stored no static secret. A real run with `opencode/deepseek-v4-flash-free` completed secure pairing, `get_connection_handoff`, and `get_snapshot`; expected and detected MCP versions were beta.5, and the WordPress smoke test passed 3/3. Its temporary session and config were removed after the test.
- **Claude Desktop Free:** AI Bridge merged only `livecanvas-forge` into the existing global config, preserved preferences, created a private backup, and stored no static secret. After per-call **Allow once** approvals, the desktop app completed pairing, handoff, and snapshot with beta.5; the WordPress smoke test passed 4/4. The session was revoked and the original desktop config was restored byte-for-byte.
- **Codex CLI/Desktop runtime:** a project-scoped mode-`0600` `.codex/config.toml` used the absolute MCP script path and separate workspace/WordPress roots. The real client completed handoff and snapshot with beta.5, then previewed and wrote `lcfa-codex-smoke-test.css`, verified its exact contents, restored the tombstone backup, and confirmed final absence. The WordPress Codex smoke test passed, after which the temporary session, cache, config, and backup fixture were removed.
- **Cursor:** the real native-agent run completed handoff and snapshot with beta.5 before the final cleanup. Repeating the same prompt later hit the external free-plan usage limit, so the already-configured Cursor MCP runtime performed the final no-model handoff and WordPress returned 3 successful checks, 0 skipped. Cursor was left as the selected client in Ready state.

The final independent cleanup check found no `opencode.json`, no `.codex/config.toml`, no temporary CSS, and no project environment variable named token, password, secret, or key. Cursor alone remains configured and its project file is mode `0600`.

## Cleanup

- `Use OpenAI API key`: restored to **Off**.
- `Override OpenAI Base URL`: restored to **Off**.
- The Qwen model remains installed in LM Studio and listed in Cursor for a later retry.
- Cursor remains connected to AI Bridge through its native Agent path.
- OpenCode, Codex, and Claude Desktop test sessions were revoked; their temporary project/app configuration was restored or removed.
- No WordPress content or temporary theme file remains changed by the Cursor experiment.

## Cursor onboarding requirements confirmed by the test

1. Keep `agent_workspace_root` and `wordpress_root` explicit and separate.
2. Preserve unrelated entries when merging `.cursor/mcp.json` and keep the file at mode `0600`.
3. Do not treat MCP discovery alone as **Ready**. Runtime Ready requires an exact-version handoff and WordPress smoke test; release acceptance additionally requires snapshot, preview, reversible write, and rollback evidence.
4. Add a preflight note that Cursor Agent access is required for MCP tool execution; a custom OpenAI-compatible endpoint does not replace that access.
5. Detect and classify Cursor quota/model-policy failures as external blockers, while leaving the valid MCP configuration intact.
6. Offer OpenCode or Claude Desktop as tested alternatives when Cursor Agent is unavailable.
7. Keep **Copy Cursor setup prompt** as the fallback when direct project-file writing is unavailable.
8. Tell Cursor users to reload the individual `livecanvas-forge` server after an MCP package update; a window reload may retain the old process.
