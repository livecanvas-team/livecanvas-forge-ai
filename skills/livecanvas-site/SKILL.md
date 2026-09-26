---
name: livecanvas-site
description: Create or edit WordPress pages, shared components, templates and assets through an authenticated LiveCanvas AI Bridge connection. Use the site's verified renderer and theme workflow.
---

Use the connected LiveCanvas AI Bridge tools for the user's requested site changes. Call get_connection_handoff and verify site identity before writing. For remote WordPress Abilities, use the equivalent livecanvas-forge-ai names returned by discovery.

Call list_workflows, select the matching task and read its body with read_workflow. The server provides maintained instructions for pages, shared components, dynamic templates, asset compilation and verification/restoration. Load other bodies only when the task needs them. If these tools are missing, report the installed Bridge/runtime mismatch before attempting a write.

The workflow is guidance, not authorization. Obtain fresh get_write_context for each exact target, follow its renderer and framework, and pass its proof unchanged to the write. Existing project rules or remembered site facts cannot override current verified context. A denied write is not permission to use shell or SQL instead.

Keep the user's scope. Read only relevant site content and assets; do not copy unrelated personal projects or chat history into WordPress. Report saved, compiled, visually_verified and published separately, including unavailable checks. Do not describe a standalone CLI process as a connection to the user's existing desktop conversation.
