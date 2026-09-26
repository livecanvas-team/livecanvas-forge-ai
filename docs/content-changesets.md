# Content changesets and Undo

Bridge saves an encrypted snapshot before a supported content write. It reads that snapshot back before changing the target. If storage fails, the target is left unchanged. Changesets belong to the authenticated WordPress user and site. An ownerless legacy connection must reconnect before it can create private changesets.

The current journal covers `create_page`, `page_upsert`, `update_page`, `update_partial`, `create_dynamic_template`, `update_dynamic_template` and `update_discussion_settings`. It captures post fields, all post metadata and taxonomy assignments. Managed `page_css` and `page_js` fields are included. Changing a Dynamic Template's page-specific assignment also captures the old and new assigned pages.

## Use Undo

1. Keep the `changeset.id` returned by a successful write. `list_changesets` returns this user's recent summaries without snapshot content.
2. Call `get_write_context` with the changeset's `target_id` and inspect the current target.
3. Call `undo_changeset` with `changeset_id`, `target_id`, the returned `write_context` object and `dry_run: true`. Set `acknowledge_shared: true` only when the user's request includes that shared target.
4. If the preview matches the requested restoration, repeat with `dry_run: false`. Refresh context if anything changed after the preview.

Undo rejects an intervening content or metadata edit, including a change to a page assigned to the template. It restores stored bytes without re-sanitizing editorial content under a different WordPress execution identity. Undo of new content moves it to WordPress Trash. A repeated completed Undo returns `already_undone`.

## Status and limits

`saved` means the database transaction committed. `rolled_back` means the operation failed and Bridge verified that the captured content returned to its previous state. `needs_recovery` means the result is uncertain and must be inspected before retrying. A changeset left in `prepared` after a process interruption is not advertised as undoable.

Undo reports `compiled` and `visually_verified` as `not_checked`. Restoring database content does not compile CSS or compare screenshots. Publication state is reported separately. Restoring published content requires the relevant post type's publishing capability.

The journal requires InnoDB and OpenSSL. WordPress authentication-salt rotation makes older encrypted snapshots unreadable. Include the journal table and authentication salts in the site's private backup policy; do not publish them. Retention and administrator recovery tooling are still pending.

Child-theme source files now use the same private table and Undo tools through a separate filesystem coordinator. See [File changesets](file-changesets.md). Compiler caches and media files still need changeset coverage. Filesystem or network side effects from other plugins' save hooks cannot be rolled back by the database transaction. Bridge validates operations through its tools; arbitrary shell commands, direct SQL and other WordPress APIs are outside that boundary. Whole-site automatic application remains unqualified until asset changesets and multi-resource compensation are implemented.

`tests/integration/changesets.php` runs isolated draft fixtures only on the two authorized local test hosts. It checks conflict rejection, snapshot tampering, snapshot failure, transaction interruption, Dynamic Template assignments and editorial preservation with WordPress KSES active under execution user 0. Its cleanup removes only its own fixtures and journal rows.
