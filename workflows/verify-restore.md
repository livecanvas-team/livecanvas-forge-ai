# Verify or restore a change

Record the exact target, requested scope and current revision. A saved response proves storage only. A compiled response proves a build only. Verify the resulting document at desktop and mobile sizes if a permitted browser is available, checking horizontal overflow, console errors, missing assets and the requested visual behavior. Report unavailable checks explicitly.

Before restoring a reference release, inspect its layout or screenshot and compare it with the current page. Identify what should return and what later content must remain. Do not rewrite the implementation repeatedly without comparing the reference.

Use a changeset's exact snapshot and expected post-write revision for Undo. If any affected content, metadata, file or asset changed after the original operation, stop and report the conflict instead of overwriting it. A failed snapshot must block the original mutation. A partially failed multi-target operation must report which writes were applied and which compensations succeeded; do not imply database/filesystem atomicity.

For a saved content operation, keep its changeset.id. Call list_changesets to find this user's recent changes. Read fresh get_write_context for the changeset's target_id, then call undo_changeset with changeset_id, target_id and that unchanged write_context. Shared targets require acknowledge_shared. Use dry_run first when the user needs a preview. Repeating a completed Undo is safe. Undo of newly created content moves it to recoverable Trash.

For a child-theme file, read get_write_context with target_type=theme_file and the changeset's exact path. Use undo_changeset with changeset_id, path, write_context and acknowledge_shared after reviewing impact. Contents and permissions must still match the saved state. Undo of an unchanged created file removes it and keeps its bytes in the private journal. Recovery tooling for removed bytes is not yet available in the UI. File writes and Undo can affect live rendering immediately; published=not_applicable does not mean a file change is a draft.

For a Picostrap bundle, read fresh get_write_context with target_type=site. Use undo_changeset with its changeset_id, write_context and acknowledge_shared after reviewing impact. Undo restores bundle bytes/permissions and its compilation metadata, rejects intervening changes, and preserves unrelated theme settings. Recheck synchronization against current Sass after restoration. Sass source edits have separate changesets.

WindPress cache Undo uses fresh site context, changeset_id and acknowledge_shared. It restores CSS, the optional source map and compilation evidence together, with conflict checks. Recheck current sources and browser/CDN cache freshness afterward. The separate theme.json output and source-volume writes are outside this changeset.

Current changesets cover granular pages, partials, Dynamic Templates, discussion settings, child-theme source files, Picostrap bundles and WindPress compiled CSS/maps/evidence. This includes managed content metadata and pages explicitly assigned to a Dynamic Template. Media and external save-hook effects still need coverage. Do not promise a whole-site Undo or infer coverage from a legacy backup reference. Legacy file restore is disabled; review old backup bytes before an explicit new write. A needs_recovery status requires inspection before retrying; never report it as rolled back. Interrupted file records in applying or undoing also require inspection and are not automatically replayed.

For comment or ping settings, use update_discussion_settings with current target context. Read back editorial content hashes and report content_preserved. Never re-save the whole article merely to close comments: WordPress sanitization depends on the execution identity and can strip embedded markup.

Final results should include saved, compiled, visually_verified and published states plus the target and Undo availability. Do not publish a draft as part of verification without authorization.
