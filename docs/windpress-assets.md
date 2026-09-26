# WindPress compiled-cache changesets

Bridge stores compiled CSS, its optional source map and compilation evidence through one private changeset. The installed WindPress Cache API supplies the destinations under the canonical uploads root. A caller cannot choose another file path. The verified theme pipeline must use Tailwind/WindPress. Site administration, the connection's cache scope and WordPress file modifications are required.

## Compile and verify

1. Read `get_write_context` with `target_type: site` and inspect the active theme and source revision. Review the shared impact before acknowledging it.
2. Use `build_windpress_cache` with this `write_context` and `acknowledge_shared: true`. The local MCP runtime resolves the installed compiler manifest, including `assets/dist`, scans all enabled providers and checks source freshness before storing.
3. Keep the returned `changeset.id`. Verify the CSS actually loaded by the page, then inspect desktop and mobile layouts where permitted. Report any unavailable check.
4. To restore, read fresh site context and call `undo_changeset` with `changeset_id`, `write_context`, `acknowledge_shared: true` and `dry_run: true`. Apply after reviewing the preview. Recheck source compatibility and browser/CDN cache freshness afterward.

Remote WordPress Abilities can use `livecanvas-forge-ai/store-windpress-cache` with externally compiled `css`, optional `sourcemap`, current `source_revision`, site context and shared acknowledgement. Its administrator-controlled MCP allowlist entry must be enabled explicitly; installing this update does not expand existing remote write permissions. REST uses the same coordinator.

DaisyUI and Typography remain required when the active sources declare them. A failed plugin import, incomplete scan, missing plugin output or changed source revision blocks storage. Bridge does not disable a required plugin to make the build pass. Compilation evidence is tied to the current native cache path, CSS digest and source revision. Cache bytes and evidence participate in the context fingerprint, including builds without framework plugins.

The server verifies storage and reports external compilation as `client_reported`. The local MCP runtime reports `compiled: true` after its actual compiler succeeds. Neither result proves visual correctness. CSS changes can affect visitors immediately; `published: not_applicable` does not make a cache write a draft.

## Restore and failure behavior

The encrypted snapshot contains original and intended CSS/source-map bytes and permissions plus the exact compilation-evidence option state. A build without a map removes the obsolete map; Undo can restore it. Changes to either file or the evidence cause an Undo conflict. Private snapshots must be stored and read back before any artifact changes.

File replacement uses private staging outside the web root. The evidence option uses database compare-and-swap. Failed evidence writes, failed final journal writes or thrown cache hooks trigger compensation only where the current resource still matches this operation's output. An external edit remains in place and returns `needs_recovery`. Hook side effects outside these artifacts are not covered.

The file group and database are not crash-atomic. Interrupted `prepared`, `applying` or `undoing` records need administrator review. Undo restores recorded bytes; it does not recompile them against newer sources. Keep backups and check the result before reporting completion.

This changeset does not include WindPress source-volume entries, its separate theme.json output, media, browser/CDN caches or the volatile last-build timing hint. Those resources require separate handling. Arbitrary shell, direct SQL and other plugins remain outside Bridge enforcement. Windows remains unqualified.
