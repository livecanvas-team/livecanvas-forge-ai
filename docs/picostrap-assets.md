# Picostrap compiled bundles

The Picostrap store requires current site context and the source fingerprint returned by its compile manifest. REST and remote Abilities use the same coordinator. The coordinator resolves the bundle destination from the active Picostrap child theme; callers cannot choose another output path. WordPress theme-editing permission and shared-impact acknowledgement are required.

## Compile, store and Undo

1. Inspect the existing child-theme Sass and the Picostrap compile manifest. Make any authorized source edit through its own exact-path context and changeset.
2. Read fresh `get_write_context` with `target_type: site` after source edits. Review the impact on every page using the shared bundle.
3. Call `picostrap_compile_apply` with this `write_context` and `acknowledge_shared: true`. The local MCP runtime compiles the current Sass with Dart Sass. When supplying `compiled_css` yourself, also pass its current manifest `source_fingerprint`.
4. Keep `changeset.id` from the result. Verify the CSS loaded by the page and check desktop/mobile rendering separately.
5. To Undo, obtain fresh site context and call `undo_changeset` with `changeset_id`, `write_context`, `acknowledge_shared: true` and `dry_run: true`. Apply after reviewing the preview. Do not send a theme-file path for a bundle changeset.

The private encrypted snapshot covers the bundle bytes and permissions plus exactly three theme metadata fields: `css_bundle_version_number`, `lcfa_picostrap_compiled_source_fingerprint` and `lcfa_picostrap_compiled_at`. Undo preserves unrelated theme metadata. Changed bundle bytes, permissions or any of these fields cause a conflict. Caller-supplied legacy rollback snapshots are rejected.

The server verifies source freshness, stored bytes and metadata. It reports compilation as `client_reported` because it receives externally compiled CSS. The local MCP runtime reports `compiled: true` after its own Dart Sass call succeeds. Neither result establishes visual correctness. Bundle writes can affect the live site immediately; `published: not_applicable` does not mean the write is isolated from visitors.

## Failure handling

Bridge saves the private snapshot before changing the bundle. It stages file bytes outside the web root and uses an atomic rename. Metadata uses a database compare-and-swap that preserves unrelated fields. A failed metadata or journal write triggers compensation only when the affected resource still matches the state written by that operation. Conflicting external changes remain in place and return `needs_recovery`.

The file and database do not form one crash-atomic transaction. An interrupted `prepared`, `applying` or `undoing` journal needs administrator review. Keep site backups. A source-file Undo is separate from bundle Undo; a restored historical bundle can be stale relative to current Sass. Check synchronization after restoration.

This changeset does not include Sass source edits, media or multi-file design-system changes. WindPress compiled caches have a separate changeset described in `windpress-assets.md`. Bridge cannot govern arbitrary shell commands, direct SQL or writes by other plugins. Windows support remains unqualified.
