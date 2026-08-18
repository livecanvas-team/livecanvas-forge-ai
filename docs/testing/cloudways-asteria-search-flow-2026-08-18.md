# Cloudways Asteria Search flow test — 2026-08-18

## Environment

- Site: `https://wordpress-1194294-6620654.cloudwaysapps.com/`
- WordPress: 7.0.4
- PHP: 8.2.33
- LiveCanvas: 4.9.3, active license
- AI Bridge: 0.2.0-beta.3.8 during the second-cycle test; 0.2.0-beta.3.9 during the clean third cycle; 0.2.0-beta.3.10 remote-build hotfix candidate
- WindPress: 3.2.86, Tailwind 4, hybrid mode
- Initial child theme: `picowind-child-1`
- Target child theme: Asteria Search 1.0.2 (`asteria-search`)

## Checkpoints

- [x] AI Bridge secure pairing with OpenCode is ready.
- [x] Theme Library catalog loads four themes.
- [x] Asteria Search package preview passes ZIP, checksum, manifest, child-theme header, content, and media validation.
- [x] Asteria Search installs and activates; project context changes to `asteria-search`.
- [x] Starter data import completes.
- [x] Clean rerun imported homepage #24, header #22, footer #23, media, LiveCanvas settings, and design system. Audit: `theme-import-asteria-search-cjt36ptu`.
- [x] Native WindPress Performance generation produced a 50.75 kB compiled cache at 2026-08-18 12:37:18.
- [x] AI Bridge 0.2.0-beta.3.10 and MCP 0.2.0-beta.4 reconcile the verified native cache with audit `theme-import-asteria-search-cjt36ptu`.
- [x] Asteria desktop/mobile visual QA passes: no horizontal overflow, broken images, console errors, or page errors; one header, main, and footer.
- [x] Contact page #27 is created, matched to the Asteria visual system, and published at `/contact/` after explicit approval. Audit: `audit-kuv2tl7s40kl`.
- [x] The public URL was rechecked without preview parameters; page content, inherited header, and inherited footer are present.

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

**Status:** fixed and remotely verified in AI Bridge 0.2.0-beta.3.10 with MCP 0.2.0-beta.4.

**Observed:** the successful import ends in `build_required`, while both **Build Tailwind CSS** and **Build CSS** are disabled with “The current WordPress URL is not detected as local, so local MCP execution is disabled.” The page does not present the documented remote next step: re-pair a trusted agent with `write,cache` scopes and call `build_theme_library_css`.

During the clean third cycle, `build_theme_library_css` reached WordPress with all required scopes but failed because the OpenCode host has no local WordPress root (`LCFA_WP_ROOT`). WindPress itself compiled the remote site successfully through `WindPress > Settings > Performance > Generate`, proving that FTP/SSH credentials are not required. The remaining gap was reconciling that native cache with the pending Theme Library audit.

**Impact:** the primary Theme Library flow displays four steps but cannot complete step 4 on a normal remote host without knowledge from the README/source code.

**Implemented correction:** when local compilation is unavailable, Theme Library now shows a two-action remote panel: open WindPress Performance, generate the cache, then verify it. The MCP tool first reuses an eligible native cache and otherwise returns the same exact WindPress action. Completion still requires the matching audit, import checksum, active stylesheet, semantic CSS checks, SHA-256 checksum, and a cache modification time after the import. Server filesystem paths are removed from the MCP payload.

**Remote verification:** after OpenCode restarted with MCP 0.2.0-beta.4, `build_theme_library_css {"theme_slug":"asteria-search"}` returned `ready` with strategy `windpress_native_cache`. It reused the 50,758-byte cache, verified all three required and two forbidden fragments, and did not require local compilation, FTP, or SSH. Theme Library now displays **Compiled cache verified** and **Ready**.

### F-05 — “Let the coding agent make changes” does not grant remote write scopes

**Status:** fixed and remotely verified in 0.2.0-beta.3.8.

**Observed:** Setup Settings shows **Let the coding agent make changes** checked, and ability diagnostics say write exposure is enabled, but the generated OpenCode bundle still contains `LCFA_PAIRING_SCOPES=read,preview`. The separate advanced **Power Mode policy** remains `Auto: local/development only` and silently overrides the apparent full-access choice on this remote production site.

**Impact:** the setup UI appears write-capable while the actual paired session cannot run the required remote CSS build or create the requested contact page.

**Implemented correction:** onboarding now asks for one outcome-based choice, **Configure and build this site**. When enabled, it turns on the advanced permission profile, file fallback, MCP, every registered public write ability, and explicit administrator Power Mode. The next OpenCode pairing requests `read,preview,write,media,theme_files,debug,cache,seo`. Leaving the choice off explicitly selects inspect-only mode, disables Power Mode and write abilities, and exposes only `read,preview`. Changing the choice invalidates the previous generated bundle and requires a new pairing instead of leaving a stale ready state.

**Remote verification:** the generated OpenCode configuration contains all eight requested scopes and WordPress reports **Power Mode: Enabled by administrator**.

### F-06 — Import does not purge the host page cache

**Observed:** page #14 and Asteria media were imported correctly, and a cache-busting query renders Asteria. The normal homepage continued to serve the pre-import Picowind fallback from Breeze cache (last modified before the import).

**Impact:** users can complete the import and still see the old site, which looks like an import failure.

**Recommended correction:** integrate with common full-page cache purges (starting with Breeze on Cloudways) after theme activation, homepage assignment, build completion, and rollback. At minimum show a detected-cache warning and a purge action.

### F-07 — OpenCode pairing approval panel is absent from the generic wizard

**Status:** fixed in the 0.2.0-beta.3.9 hotfix candidate.

**Observed:** OpenCode successfully calls `get_connection_handoff` and receives pairing code `2FXL-MCYH`, but the verification URL points to `#lcfa-secure-codex-pairing-sessions`, an anchor that was rendered only in the Codex-specific fast path. The generic OpenCode wizard displayed no pending request and no approval action.

**Impact:** first-time OpenCode onboarding stops after configuration even though the agent reached WordPress correctly.

**Implemented correction:** every remote MCP client now receives the shared secure pairing/session panel in the connection wizard. Pending requests show the client, matching code, expiry, access profile, and exact requested scopes before approval.

### F-08 — Setup may activate the wrong duplicate compatible child theme

**Status:** fixed in the 0.2.0-beta.3.9 hotfix candidate.

**Observed:** the second-cycle test started with `picowind-child-1` active. Saving an otherwise valid Picowind project switched WordPress to the alphabetically earlier duplicate `picowind-child`.

**Impact:** onboarding changes the live theme unexpectedly and may select a stale or incomplete child theme.

**Implemented correction:** if the currently active theme is a compatible candidate, setup preserves it. Candidate fallback is used only when the active theme is incompatible.

### F-09 — Reset leaves coding-agent sessions and pending pairings valid

**Status:** fixed in the 0.2.0-beta.3.9 hotfix candidate.

**Observed:** **Reset setup and start again** rotated the legacy MCP token and cleared setup settings, but active secure MCP sessions and pending device pairings remained stored and usable.

**Impact:** reset did not fully reset remote access, contrary to the user-facing expectation.

**Implemented correction:** reset now revokes every active coding-agent session, removes all pending/consumed pairing records, rotates the MCP token, reports the affected counts, and explicitly documents what is preserved.

### F-10 — Starter import status can disagree with imported WordPress objects

**Observed:** before the second-cycle cleanup, the Theme Library UI reported that starter data was not imported while homepage #14, partials #12/#13, Asteria media, and the prior import audit existed.

**Impact:** the user may repeat an import or assume rollback/cleanup completed when imported objects still remain.

**Recommended correction:** derive import state from both the durable audit and imported object markers. If they disagree, show **Import needs attention** with reconciliation, cleanup, and resume actions instead of a fresh-import CTA.

### F-11 — Command suggestion misclassifies a new page request as a header edit

**Observed:** the read-only Contact-page planning request explicitly said to inherit the global header/footer and create a new page. `suggest_lc_command` returned `update_header` with medium confidence because the prompt mentioned header/navigation inheritance.

**Impact:** an agent that trusts the suggestion without validating intent could edit the global header instead of creating a page.

**Mitigation during test:** OpenCode rejected the suggestion, validated the markup, and used `page_upsert` in dry-run mode. The reviewed payload was then applied as draft page #27 with `no_theme_edits=true`; header #22, footer #23, and theme files remained unchanged.

**Recommended correction:** give explicit create/update-page intent precedence over negative or inheritance-only header/footer mentions. Add classifier fixtures for “inherit the global header/footer”, “do not edit navigation”, and other negated shell references.

### F-12 — Same-version update notice remains briefly after installation

**Observed:** immediately after installing 0.2.0-beta.3.10, the Plugins screen still offered an update from 0.2.0-beta.3.10 to 0.2.0-beta.3.10.

**Result:** a WordPress **Check again** request regenerated the update transient. The duplicate notice disappeared, AI Bridge remained on 0.2.0-beta.3.10, and the only remaining update was Akismet. This is a transient refresh artifact, not a persistent updater defect.

## Final authoritative state after the 0.2.0-beta.3.10 verification

- AI Bridge 0.2.0-beta.3.10 is installed on Cloudways; GitHub prerelease `v0.2.0-beta.3.10` is public.
- `@livecanvas/ai-bridge-mcp@0.2.0-beta.4` is published with dist-tag `beta`; npm `latest` remains `0.1.4`.
- The clean Asteria rerun is active with homepage #24, partials #22/#23, and audit `theme-import-asteria-search-cjt36ptu`.
- WindPress 3.2.86 generated a 50.75 kB Tailwind 4 cache in hybrid mode; AI Bridge verified it and marked Asteria ready.
- OpenCode project configuration is installed with `read,preview,write,media,theme_files,debug,cache,seo`.
- OpenCode session `sess_5panx2hu-mk8zigc` is active. The final WordPress smoke test reports 1 successful, 0 skipped, and expected/detected MCP version 0.2.0-beta.4.
- The 0.2.0-beta.3.9 fixes for generic OpenCode pairing, active child-theme preservation, and access reset were all remotely verified.
- Asteria visual checks pass at 1280×800 and 390×844 with no horizontal overflow, broken images, console errors, or page errors.
- Contact page #27 is published at `/contact/` with the Asteria tokens, inherited global shell, SEO metadata, and rollback audit `audit-kuv2tl7s40kl`. The public URL was verified without preview parameters.
