# Child-theme file changesets

Bridge now saves child-theme source files through one authenticated WordPress coordinator. Local MCP verifies the canonical local roots first, then calls this coordinator. REST and remote WordPress Abilities use the same implementation. A failed request never falls back to a direct local write.

The connected owner needs WordPress theme-editing permission. Parent themes, symlinks and hard-linked files are rejected. WordPress file-edit restrictions still apply. Sources must use an allowed extension and be at most 8 MiB.

## Write and Undo

1. Call `get_write_context` with `target_type: theme_file` and the exact child-relative `path`. Review the framework and shared impact.
2. Call `write_theme_file` with that path, content and unchanged `write_context`. Set `acknowledge_shared` only for an authorized shared change. Preview with `dry_run` when needed.
3. Keep the returned `changeset.id`. `list_changesets` returns this owner's recent paths and statuses without source bytes.
4. To restore, read fresh context for the same path. Call `undo_changeset` with `changeset_id`, `path`, `write_context`, `acknowledge_shared` and `dry_run: true`. Apply only when the preview matches the requested restoration.

Bridge encrypts the original and intended file states in the private changeset table, then verifies read-back before touching the target. Writes use a private staging directory outside the web root on the same filesystem and an atomic rename. Existing file permissions are preserved. New files use mode 0644. Storage or staging failure blocks the write.

Undo checks both saved bytes and permissions. An intervening edit is a conflict even if a caller passes `force`. Undo of a file that did not exist before the change removes only the unchanged created file. Its bytes remain encrypted in the journal. Administrator recovery tooling for those bytes is still pending. Empty directories created for a source are left in place.

## Failure states and limits

Files and the database cannot share a transaction. Bridge persists `applying` or `undoing` before filesystem changes, checks the result, then records `saved` or `undone`. If a later journal step fails, Bridge attempts compensation only while the current file matches the state written by this operation. It verifies compensation and reports `rolled_back`, or returns the original `saved` state after a failed Undo. A conflicting external edit is preserved and reported as `needs_recovery`.

A process interruption can leave `prepared`, `applying` or `undoing` records. They require inspection and are never replayed automatically. WordPress authentication-salt rotation makes existing snapshots unreadable. Preserve the private journal and salts in site backups. Crash recovery, retention and cleanup of interrupted private staging directories need administrator tooling. This implementation is tested on macOS; Windows is unqualified.

File changes can affect live rendering immediately. The result reports `saved`, `compiled: not_checked`, `visually_verified: not_checked`, `published: not_applicable` and `may_affect_live_rendering: true`. It does not claim that a PHP/Twig template executes correctly or that the page looks correct. Validate and preview the site separately.

Picostrap bundles and their compilation metadata have a separate site-scoped journal described in `picostrap-assets.md`. WindPress compiled CSS/maps/evidence are covered by `windpress-assets.md`. Media and coordinated multi-file source changes still need journal integration. Legacy file restore tools are disabled because old backups lack verified ownership and post-write revisions. Existing backup files remain readable for explicit review and have not been deleted or migrated.

Advisory locks coordinate Bridge operations only. Arbitrary shell commands, direct SQL, other plugins and simultaneous external filesystem changes are outside Bridge's control. No atomic compare-and-swap guarantee is made for external filesystem writers.

`tests/integration/file-changesets.php` tests REST, Abilities and command writes on unused source fixtures in the two authorized local sites. It checks exact bytes and modes, fresh context, ownership, damaged snapshots, write/Undo failure compensation, conflicts and permission enforcement. The script removes its fixtures and compares existing theme sources and post rows before and after. It does not invoke an actual coding agent.
