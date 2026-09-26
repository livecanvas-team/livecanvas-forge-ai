# Shared site instructions

In WordPress, open **AI Bridge > Build Plan > Instructions for coding agents**. Add the site preferences you want connected agents to read, then choose **Save shared instructions**. Save an empty field to stop sharing. Only a WordPress administrator can save this form.

Keep these instructions about the site. For example: “Use concise English, keep the existing color palette and leave new pages as drafts.” Do not add passwords, access tokens or private conversations. Existing Project Brief notes and chat history are not copied into this field. Instructions are stored as site-wide WordPress options and are available to authorized site readers, including connected agents with read access. They are not a private per-user notebook or encrypted secret store.

Agents call `get_site_knowledge`, or the remote `livecanvas-forge-ai/get-site-knowledge` Ability. Both return the same approved text and revision. The current workflow and startup handoff advertise this step. Agents compare that revision with `site_knowledge_revision` from fresh `get_write_context`. A difference requires reading the instructions again before preparing a change.

Shared instructions are user-authored context. They cannot grant permissions, verify a framework plugin, change the effective renderer or override Bridge's write validation. A note asking for Bootstrap does not make Bootstrap appropriate on a verified Picowind site. The current user request and verified technical context still govern the change. Bridge cannot govern arbitrary shell commands, direct database writes or operations outside its tools.

## Edits and recovery

Saving or withdrawing instructions changes their revision and invalidates previously prepared write contexts. An agent using an old context receives `stale_context`. The save form uses compare-and-swap storage, so an older open form cannot silently overwrite a newer review. On a conflict, the recovery form preserves the submitted draft and shows the current shared text for comparison.

Storage errors do not produce a success notice. If a save cannot be confirmed, check the current text before retrying; the database may already contain the change. If storage is unavailable or corrupt, Bridge withholds the instructions and refuses to prepare new write context. Site copies retain the original site binding and need explicit administrator recovery before those records can be used elsewhere. Automatic migration, retained revision history and a repair interface are not implemented.

The field accepts plain UTF-8 text up to 12 KB. Displayed content is escaped. The read response does not include reviewer identities or private conversation data. Agents have no tool or REST write route that approves new instructions. The WordPress form requires the administrator capability and a valid nonce; an authenticated MCP worker cannot use that service to approve its own instructions.

## Verification scope

PHP regressions cover administrator review, stale-form conflicts, withdrawal and scope isolation. The real WordPress integration fixture uses a temporary option on each approved local site. It compares REST and Ability output and verifies stale-context rejection without changing existing site instructions, content or Project Brief notes. Browser fixtures render the production controls with synthetic data at desktop and mobile sizes. These checks do not prove that a model follows every preference or that the frontend is connected to Codex Desktop.
