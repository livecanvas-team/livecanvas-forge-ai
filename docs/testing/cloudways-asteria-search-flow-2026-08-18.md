# Cloudways Asteria Search flow test — 2026-08-18

## Environment

- Site: `https://wordpress-1194294-6620654.cloudwaysapps.com/`
- WordPress: 7.0.4
- PHP: 8.2.33
- LiveCanvas: 4.9.3, active license
- AI Bridge: 0.2.0-beta.3.7
- WindPress: 3.2.86, Tailwind 4, hybrid mode
- Initial child theme: `picowind-child-1`
- Target child theme: Asteria Search 1.0.2 (`asteria-search`)

## Checkpoints

- [x] AI Bridge secure pairing with OpenCode is ready.
- [x] Theme Library catalog loads four themes.
- [x] Asteria Search package preview passes ZIP, checksum, manifest, child-theme header, content, and media validation.
- [x] Asteria Search installs and activates; project context changes to `asteria-search`.
- [x] Starter data import completes.
- [x] Homepage #14, header #12, footer #13, media, LiveCanvas settings, and design system are imported.
- [ ] WindPress compiled cache is built and verified.
- [ ] Desktop/mobile visual QA passes.
- [ ] Contact page is created and matched to the Asteria visual system.

## Findings

### F-01 — Blocking native confirmation disrupts automated setup

**Observed:** submitting **Import starter data** opens a JavaScript `confirm()` dialog. Browser-control clicks wait on the dialog and time out. Accepting the dialog through automation also blocks while navigation is expected.

**Impact:** coding-agent onboarding cannot reliably complete the documented admin-only import flow. The operator cannot tell whether the POST began, so retrying risks duplicate work even though the importer is intended to be idempotent.

**Recommended correction:** replace inline `onsubmit="return confirm(...)"` with an accessible, non-blocking two-step confirmation UI. Disable the submit button after confirmation and expose a visible busy state.

### F-02 — Long synchronous import has no progress or recovery signal

**Observed:** after confirmation, the controlled tab remained unavailable for more than 30 seconds. A manual confirmation eventually completed the import, but the browser automation could not distinguish an in-progress POST from an unsubmitted form.

**Impact:** users see an apparently frozen page and cannot distinguish download, media import, build, timeout, or failure.

**Recommended correction:** run import as a staged background job or AJAX/REST workflow with durable status (`queued`, `validating`, `media`, `content`, `windpress`, `complete`, `failed`). Show current stage, elapsed time, audit ID, retry, and rollback guidance.

### F-03 — Active child theme fallback copy is misleading before starter import

**Observed:** the public fallback page says “no child theme is active” while AI Bridge and WordPress report `asteria-search` as the active child theme.

**Impact:** after a successful child-theme installation but before starter data import, the user receives a contradictory diagnosis.

**Recommended correction:** make the fallback detect the active child theme and state that starter content/homepage has not been imported yet.

### F-04 — Remote Picowind import ends in a build dead end

**Observed:** the successful import ends in `build_required`, while both **Build Tailwind CSS** and **Build CSS** are disabled with “The current WordPress URL is not detected as local, so local MCP execution is disabled.” The page does not present the documented remote next step: re-pair a trusted agent with `write,cache` scopes and call `build_theme_library_css`.

**Impact:** the primary Theme Library flow displays four steps but cannot complete step 4 on a normal remote host without knowledge from the README/source code.

**Recommended correction:** when `build_required` and local build is unavailable, replace the disabled button with an actionable remote-build panel. Show required scopes, active agent, current session scopes, a regenerate/re-pair action, and a copy-ready `build_theme_library_css {"theme_slug":"asteria-search"}` prompt.

### F-05 — “Let the coding agent make changes” does not grant remote write scopes

**Status:** fixed locally; pending packaging, release, and remote verification.

**Observed:** Setup Settings shows **Let the coding agent make changes** checked, and ability diagnostics say write exposure is enabled, but the generated OpenCode bundle still contains `LCFA_PAIRING_SCOPES=read,preview`. The separate advanced **Power Mode policy** remains `Auto: local/development only` and silently overrides the apparent full-access choice on this remote production site.

**Impact:** the setup UI appears write-capable while the actual paired session cannot run the required remote CSS build or create the requested contact page.

**Implemented correction:** onboarding now asks for one outcome-based choice, **Configure and build this site**. When enabled, it turns on the advanced permission profile, file fallback, MCP, every registered public write ability, and explicit administrator Power Mode. The next OpenCode pairing requests `read,preview,write,media,theme_files,debug,cache,seo`. Leaving the choice off explicitly selects inspect-only mode, disables Power Mode and write abilities, and exposes only `read,preview`. Changing the choice invalidates the previous generated bundle and requires a new pairing instead of leaving a stale ready state.

### F-06 — Import does not purge the host page cache

**Observed:** page #14 and Asteria media were imported correctly, and a cache-busting query renders Asteria. The normal homepage continued to serve the pre-import Picowind fallback from Breeze cache (last modified before the import).

**Impact:** users can complete the import and still see the old site, which looks like an import failure.

**Recommended correction:** integrate with common full-page cache purges (starting with Breeze on Cloudways) after theme activation, homepage assignment, build completion, and rollback. At minimum show a detected-cache warning and a purge action.

## Current authoritative state

- Active theme: `asteria-search`.
- Theme Library: Asteria Search package validated for 30 minutes.
- Starter data: imported with audit `theme-import-asteria-search-nk2d4dfr`.
- Imported objects: homepage #14, header #12, footer #13, media #8–#11.
- Theme Library status: `build_required`.
- Normal public homepage: stale Picowind fallback from Breeze cache.
- Cache-busting public homepage: Asteria markup and media are present.
