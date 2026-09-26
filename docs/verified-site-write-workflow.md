# Verified site writes

Bridge now rejects relevant mutations without a current, server-signed context. This applies to the Command Deck, WordPress REST routes, public WordPress Abilities and local MCP theme-file writes. Existing permissions and session scopes still apply. A context token does not grant permission.

Update the local MCP runtime together with the plugin and restart connected agents. Older runtimes cannot discover `get_write_context`; their writes will be rejected rather than silently bypassing the contract.

## Agent workflow

1. Call `get_write_context` with the exact `target_id` and, when available, `target_url`. For a new page use `target_type: "new_page"`. For a file use `target_type: "theme_file"` and its child-relative `path`. The remote ability is `livecanvas-forge-ai/get-write-context`.
2. Read the site identity, active parent/child theme, canonical roots, framework and `rendering`. A public request probe observes LiveCanvas's selected Dynamic Template and the actual Timber loader path. An article can therefore resolve to `picowind-child/views/single.twig`, rather than its editorial `post_content`. Shared partials, Dynamic Templates and theme files affect multiple pages. A failed/private-URL probe is explicitly unverified; it is not a visual check.
3. Read the effective template and existing assets before proposing a layout. Pass the returned `write_context` object unchanged to preview/apply. Shared targets also require `acknowledge_shared: true`. Refresh context after every mutation, theme/configuration change or expiration (ten minutes).
4. Compile the relevant assets. Inspect desktop and mobile rendering where the available browser permits it. Report each verification state separately. Do not call a saved draft “published” or a successful compile “visually verified”.

Example page creation:

```json
{"target_type":"new_page"}
```

Pass that response's `write_context` to `run_lc_command`:

```json
{
  "action": "create_page",
  "title": "About us",
  "status": "draft",
  "body_html": "<section class=\"py-12\"><h1>About us</h1></section>",
  "write_context": "REPLACE WITH THE RETURNED OBJECT",
  "dry_run": true
}
```

This example is Picowind markup. For Picostrap, use Bootstrap classes instead. After reviewing the preview, send the same unchanged proof with `dry_run: false`, provided the source state has not changed. The proof placeholder above must be replaced with an object, not a string.

## Rendering and assets

The dashboard's **Editor preset** is LiveCanvas's editing configuration. For example, `daisyui-5` can appear even when the imported WindPress sources do not compile DaisyUI. **API compatibility** describes the Bridge integration APIs and does not confirm CSS output. Neither value authorizes dependent classes.

Snapshot `framework_slug` and context/inventory `editor_config` remain available for older clients. The adjacent `editor_profile` object labels the value `editor_preset_only`, with `is_compile_evidence: false`. Read the current `get_write_context.context.pipeline`: only `daisyui: "compiled"` or `typography: "compiled"` permits the corresponding classes. `unverified` includes missing or stale evidence; it does not by itself prove that a plugin is uninstalled. Use plain Tailwind while optional plugin compilation is unverified.

| Verified stack | Markup and build workflow |
| --- | --- |
| Picowind | Tailwind classes scanned by WindPress. DaisyUI and Typography are allowed only when the current source revision has successful compiled CSS evidence for those plugins. Otherwise use plain Tailwind. |
| Picostrap | Bootstrap markup. Edit child-theme Sass sources, refresh site context, then use `picostrap_compile_apply` with its existing source-fingerprint validation. |
| Barebone or unknown | Inspect the theme's templates and enqueued assets. Use theme-native semantic HTML. Bridge does not invent a framework or claim a compiler exists. |

Editorial articles must not contain layout CSS, script tags or inline event handlers. Layout belongs in the effective child-theme template and asset pipeline. Supported LiveCanvas pages can use managed `page_css` and `page_js`; use JavaScript only for required behavior. Content is a fragment without an outer `html`, `head`, `body` or `main` element. Parent-theme file writes are blocked even if an optional caller flag asks to allow them.

WindPress supports both `assets/dist` / `resources/packages/...` and the older `build` / `assets/packages/...` layout. It rejects truncated scans, changed source revisions, uncompiled directives and missing required plugin output. Commented or unimported plugin declarations do not count as active plugins. Failed builds leave the previous valid CSS cache untouched; accepted CSS is staged and atomically replaced. Framework diagnostics go to stderr so they cannot corrupt MCP protocol output.

For a stored WindPress build, first obtain a `target_type: "site"` context, then call `build_windpress_cache` with that `write_context` and `acknowledge_shared: true`. The runtime scans every enabled provider and forwards the original source revision to storage. Partial-provider builds are preview-only (`store: false`). Do not remove a required plugin merely to make a build pass.

## Discussion settings

Use `update_discussion_settings`, or the matching remote ability, with an inspected post ID, current `write_context`, and `comment_status` / `ping_status` set to `open` or `closed`. It preserves existing title, excerpt and content bytes after WordPress sanitization for that exact post. It does not grant `unfiltered_html` or accept replacement editorial HTML. The result includes the actual execution identity and a content checksum.

## Scope and limitations

- Existing page writes cannot replace a verified theme/Dynamic Template renderer with the LiveCanvas Empty Page shell. Inspect the effective template and target it directly.
- Update a shared header/footer through its explicit `lc_partial` ID and `update_partial`. Do not infer a global target from a variant alone.
- Legacy multi-target foundation, design-system auto-apply, library import/rollback and similar composite paths are blocked where they cannot preserve target-scoped validation. Native pattern-page and translation-copy paths are also blocked. Use separately inspected writes. This is an intentional compatibility change, not a silent success.
- Source fingerprints are conservative: content, assignments, theme sources, active plugin entry files and relevant configuration changes invalidate context. Large sites may incur extra read/hash cost. Inaccessible sources fail closed.
- Public rendering probes reflect an anonymous visitor, not every personalized or authenticated variation. PHP/Twig routing is observed; unsupported template-engine internals may remain unknown. No screenshot, comparison to a reference release or visual approval is fabricated.
- Before restoring a reference release, inspect its existing layout and compare desktop/mobile references. File restoration is not permission to redesign it. If the reference is unavailable, report that limitation before changing the implementation.
- **Bridge cannot govern arbitrary shell commands, direct SQL, external WordPress APIs or writes performed by other plugins.** Agents must stay within Bridge tools for these safeguards to apply. HTML checks are framework/style guardrails, not a general sandbox for arbitrary PHP or JavaScript.

## Regression evidence (2026-09-25)

- PHP/Node/browser-script regression suite passed, including signed-context tampering, staleness, cross-target writes, parent-theme guards, framework mismatch, unavailable DaisyUI/Typography, commented plugin declarations and failed cache storage.
- Real WordPress REST and Abilities rejected missing context and inline CSS. Valid context allowed draft creation through REST and a draft update through an Ability; that fixture was then removed. Invalid/stale CSS submissions preserved the previous cache checksum.
- Actual public request routing detected the test site's assigned LiveCanvas Dynamic Template, then the Picowind child `views/single.twig` fallback when dynamic templating was temporarily disabled. Original theme and settings were restored.
- WindPress compiled MarketingRocks sources in memory using its installed `assets/dist` compiler: 4,045 candidates, 58,262 minified bytes. No MarketingRocks files, content or persistent CSS cache were changed. Neither DaisyUI nor Typography was active in those imported sources.
- A Picowind test-site build compiled and stored 255,009 bytes with DaisyUI evidence. Required plugins were not disabled. Picostrap Sass compiled 453,802 bytes in memory using its installed sources. The installed Picostrap WindPress provider has an unrelated undefined `$wpTheme` error; Picostrap verification used its native Sass compiler.
- With real WordPress KSES enabled and `user_id=0`, closing comments and pings preserved the fixture article byte for byte. The fixture was deleted afterwards.
- The restored test site's existing article was inspected at 1440 and 390 pixels: one `main`, no horizontal overflow, broken images or browser errors. This checks the existing test layout, not a reference-release comparison. No reference release was supplied or restored.

Run `bash scripts/test-all.sh`. Live integration tests under `tests/integration/` require an explicit local WordPress root and database socket. Mutating tests refuse any host other than `test-ai-forge.local`, create only their own draft fixtures, and restore temporary theme/settings changes. `real-stack-compile.js` is read-only by default; enable `LCFA_TEST_STORE=1` only on the disposable test site.
