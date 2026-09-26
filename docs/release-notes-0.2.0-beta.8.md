# 0.2.0-beta.8: verified external-agent workflow

This beta keeps the supported workflow focused on one path: connect Codex, OpenCode, Cursor, Claude Code or Claude Desktop from **AI Bridge → Connect**, approve the matching WordPress request, reload the MCP connection, then work from that coding agent.

The LiveCanvas editor no longer renders an agent launcher, prompt composer or agent-start control. The earlier in-editor delivery path is still under development and is not presented as available in this release.

The bundled MCP runtime is `0.2.0-beta.8`. It preserves the verified write workflow: current site and target context before writes, rendering-target resolution, Picowind/WindPress and Picostrap/Sass checks, child-theme preference, content-preserving discussion updates, changesets and conflict-aware Undo.

Use a staging site and retain backups. WordPress reports a connection as ready only after the selected client completes an authenticated handoff. A green connection does not prove that the client remains open or that a future write has passed its required context and approval checks.

Current qualification is incomplete. Codex CLI, OpenCode Desktop and Claude Desktop have earlier real-site evidence; Cursor and Claude Code have unresolved account blockers. Windows, Linux and the new beta.8 installer have not completed the full client matrix. The in-editor agent transport, including an attachment to an existing Codex Desktop conversation, remains unavailable.
