# LiveCanvas Stack Integration Completeness Audit

Last reviewed: 2026-08-02
Plugin baseline: LiveCanvas AI Bridge 0.1.28

## Purpose

This document measures whether AI Bridge is genuinely integrated with LiveCanvas, Picostrap, Picowind, and WindPress. A feature is not considered complete merely because a class or endpoint exists. Each capability is rated against the strongest evidence currently available.

## Verification Levels

1. **Implemented**: runtime code exists.
2. **Contract tested**: automated tests verify inputs, outputs, permissions, and failure behavior.
3. **Local E2E**: the workflow passes against a real local WordPress stack.
4. **Remote E2E**: the workflow passes through secure remote pairing on a staging host.
5. **Production qualified**: the workflow passes on multiple hosts and supported dependency versions, with rollback and failure recovery verified.

Production completeness requires level 5 for destructive workflows. Lower levels are reported explicitly.

## Reference Stack Inspected

The audit compared AI Bridge with installed source and runtime APIs from:

| Product | Inspected version | Evidence |
|---|---:|---|
| LiveCanvas | 4.9.3 | Local plugin source and registered post/meta/taxonomy APIs |
| Picostrap | 3.8.6 | Local theme source and Houseflow E2E fixture |
| Picowind | 0.0.14 | Local theme source, especially its WindPress integration provider |
| WindPress | 3.2.86 | Local plugin source and a running Tailwind 4 runtime |
| Tailwind runtime | 4 | `LCFA_WindPress_Bridge::get_status()` on the local reference site |

The running reference site reported WindPress 3.2.86 in `hybrid` mode with Tailwind 4. It was currently using the Bootstrap/Picostrap editor configuration, so Picowind providers and generated cache files were correctly absent. This proves that “WindPress active” and “Picowind runtime ready” must remain separate states.

## Executive Result

- **LiveCanvas core operations:** strong alpha, with broad contract coverage and real local/remote use.
- **Picostrap:** strongest framework path; site generation, Sass compilation, header behavior, and visual checks have a dedicated E2E fixture.
- **Picowind/WindPress:** broad API coverage with a passing local Asteria import, deterministic Tailwind 4 cache build, desktop/mobile visual check, and rollback. Cross-host and Tailwind 3 qualification remain open.
- **Theme Library:** functional beta. Package validation, install, import, deterministic build gating, separate LiveCanvas shell rendering, media, runtime rollback, and automatic failed-import recovery are implemented; the main flow passes on a real LocalWP stack.
- **Secure agent transport:** strong alpha. Pairing, site identity, scopes, preview-first writes, and revocation are implemented; multi-host qualification remains open.

The plugin is not yet “perfectly integrated” in the production sense. Its main remaining risk is not missing CRUD endpoints; it is proving that build output, framework state, and rollback remain coherent across versions and hosts.

## Capability Matrix

### LiveCanvas

| Capability | Current level | Evidence | Remaining work |
|---|---|---|---|
| Detect installation, activation, license, editor configuration | Contract tested | `LCFA_Environment`, environment and setup tests | Verify unusual LiveCanvas installation paths and multisite |
| Inventory pages, headers, footers, partials, dynamic templates, blocks, sections | Contract tested + field use | `LCFA_Inventory`, handoff/inventory tests, normalized `lc_partial_type` terms | Large-inventory performance and multisite fixtures |
| Read complete page HTML | Local/remote E2E | page HTML ability and real paired-site tests | Add large-page performance thresholds |
| Preview/apply page upsert | Local/remote E2E | ability registry, Command Deck, audit records | Production qualification on cache-heavy hosts |
| Targeted content patch | Contract tested | `LCFA_Content_Patch_Service` | Visual E2E for editable boundaries and nested LiveCanvas blocks |
| Header/footer partial preview/apply | Local/remote E2E + contract tested taxonomy | global shell abilities, real partial edits, `lc_partial_type` apply/rollback tests | Production taxonomy-assignment E2E |
| Dynamic template preview/apply | Contract tested + field use | native `is_*`, `menu_order`, `lc_use_template_of_slug`, language, preview-target, and rollback tests | Real Polylang and remote-site E2E |
| Page-only CSS, JS, SEO/noindex, public draft preview | Contract tested | page asset and preview services | Cross-plugin SEO verification and preview expiry E2E |
| Selected-section insertion | Implemented + contract tested | `LCFA_Command_Deck::insert_section_after_selected_anchor()` | Browser E2E inside the LiveCanvas editor |
| Revision/audit rollback | Contract tested | audit store and restore actions | Transaction-level recovery when an apply fails midway |

### Picostrap

| Capability | Current level | Evidence | Remaining work |
|---|---|---|---|
| Detect Picostrap parent/child theme and Bootstrap editor | Local E2E | environment detection and Houseflow fixture | Version matrix beyond the inspected 3.8.6 release |
| Generate Bootstrap/LiveCanvas markup | Local E2E | framework validator and Houseflow generation flow | More component fixtures and accessibility assertions |
| Preview/apply design system | Contract tested | Picostrap design-system executor | Verify Customizer values and Sass variables remain bidirectionally coherent |
| Read/write SCSS in allowed child-theme paths | Contract tested | theme file bridge and Picostrap compile service | Restrictive-host filesystem E2E |
| Compile Sass without committing a failed bundle | Contract tested + local E2E | compile service and Houseflow scripts | Test different Sass/Picostrap build layouts |
| Prevent Tailwind runtime from hiding Bootstrap components | Local E2E | `LCFA_Framework_Compatibility`, visible nav/accordion checks | Regression matrix with cache plugins and minifiers |
| Desktop/mobile visual verification | Local E2E | `tests/e2e/houseflow` | Automate this fixture in CI with managed browser installation |

### Picowind And WindPress

| Capability | Current level | Evidence | Remaining work |
|---|---|---|---|
| Detect Picowind and Tailwind editor mode | Contract tested | `LCFA_Environment` | Running E2E on clean and migrated Picowind sites |
| Discover WindPress version, Tailwind version, performance mode, providers, handlers, cache | Runtime inspected + versioned profile | `LCFA_WindPress_Bridge::get_status()`, `stack-capabilities.v1` | Validate supported/degraded outcomes across multiple WindPress releases |
| Initialize Picowind runtime | Contract tested + source parity | `ensure_picowind_runtime()` matches Picowind's WindPress setup behavior | Verify Tailwind 3 and Tailwind 4 variants on real sites |
| Scan providers and volume entries | Contract tested | REST/MCP WindPress endpoints | Large-volume pagination and payload limits |
| Store `theme.json` and compiled CSS | Contract tested | WindPress bridge and design-system executor | Remote E2E and cache-plugin matrix |
| Build WindPress cache locally | Local E2E | Asteria build produced and verified a 59,097-byte Tailwind 4 cache through the local MCP gateway | Deterministic remote build strategy when no local filesystem bridge exists |
| Import Tailwind source from Theme Library | Local E2E | Asteria 1.0.1 imported, compiled, persisted, and rendered through WindPress hybrid mode | Remote-host and Tailwind 3 qualification |
| Backup and restore runtime/options/cache | Local E2E | focused tests plus Asteria import/rollback on LocalWP | Retention cleanup and multi-version/host matrix |
| Prevent WindPress runtime on Picostrap | Local E2E | framework compatibility filter | Confirm behavior across WindPress releases |

### Theme Library

| Capability | Current level | Evidence | Remaining work |
|---|---|---|---|
| Catalog and checksum validation | Contract tested | catalog/validator tests | Remote catalog availability and stale-cache UX |
| Safe ZIP paths and Picowind child-theme validation | Contract tested | traversal, required-file, parent-theme tests | Signature support if distribution becomes private |
| Install and activate child theme | Local E2E | Asteria 1.0.1 preview/install on LocalWP | Multi-host filesystem matrix |
| Preserve pre-install active theme | Local E2E | pending install handoff restored `picostrap5-child-base` | Verify abandoned installs and stale pending-state cleanup |
| Import settings, media, partials, homepage, menus | Local E2E | Asteria created separate header/footer, homepage, and four media items | Multilingual fixtures |
| Idempotent re-import | Contract tested | import key/version/checksum logic | Force-update migration rules between manifest versions |
| WindPress runtime rollback | Local E2E | disk backup with SHA-256 plus real import rollback | Multi-version/host matrix and backup retention |
| Automatic failed-import recovery | Contract tested | optional transaction rollback reports original failure plus recovery outcome | Real failure-injection E2E on local and restrictive remote hosts |
| Automatic compile after source import | Local E2E | explicit `ready`, `build_required`, and `build_failed`; persistent cache checksum verification; admin retry endpoint; passing Asteria import/build/rollback | Remote-host fallback matrix |

### Agent, Security, And Developer Operations

| Capability | Current level | Evidence | Remaining work |
|---|---|---|---|
| Project-scoped Codex setup | Local/remote E2E | generated TOML and pairing flow | Fresh-install UX testing on Windows/Linux Codex clients |
| Secure pairing without WordPress Application Password in config | Local/remote E2E | session manager, hashed tokens, revocation | Proxy/WAF/host matrix |
| Site fingerprint and project identity | Local/remote E2E | handoff and session checks | Stronger warning when a project config targets a migrated domain |
| Scoped read/preview/write/full access | Contract tested + field use | ability registry and session scopes | Per-operation approval policies for production |
| Theme files, media, debug, cache, SEO, Polylang | Contract tested | Full Power services | Production E2E across plugin combinations |
| Visual check | Contract tested | MCP tool and Playwright dependency | Managed Chromium readiness check during onboarding |
| MCP schema/caching metadata | Contract tested | Node registry tests | Complete migration after the MCP 2026 release candidate stabilizes |
| Versioned stack capability profile | Contract tested | `stack-capabilities.v1`, snapshot, admin hero, and agent handoff | Validate the profile against second-host and future-version fixtures |

## Confirmed Gaps

### P0: Required Before A Production Claim

1. **Multi-host Theme Library build gate qualification**
   - The deterministic LocalWP compile/import/visual-check/rollback run passes.
   - Production evidence still requires remote-host `build_required` verification and a second supported WindPress host/version.

2. **Transaction recovery**
   - Optional automatic rollback is implemented and contract tested, with separate original-error and rollback outcomes.
   - Production evidence still requires injected mid-import failures on LocalWP and a restrictive remote host.

3. **Versioned capability detection**
   - `stack-capabilities.v1` publishes tested ranges and returns `supported`, `degraded`, or `unsupported` with explicit missing APIs.
   - Production evidence still requires qualification against a second host and supported dependency matrix.

4. **Broader Picowind qualification**
   - Repeat the passing Tailwind 4 LocalWP flow on a clean baseline, a migrated site, and a restrictive remote host.
   - Add a Tailwind 3 fixture where the supported WindPress version still exposes that runtime.

### P1: Required For Complete Editorial Coverage

1. Verify Picostrap Customizer/Sass/design-token synchronization rather than treating file compilation alone as completion.
2. Add a managed browser readiness check and guided Chromium install for `visual_check`.
3. Add remote build orchestration or a cloud runner for hosts where PHP cannot compile or write build artifacts.
4. Add backup retention and orphan cleanup for abandoned Theme Library installs.
5. Run real multilingual dynamic-template assignment and rollback tests with Polylang active.

### P2: Product Expansion

- WooCommerce product/archive workflows.
- ACF field-aware generation and validation.
- Deeper Polylang/SEO archive and taxonomy flows.
- Generic custom-theme enqueue/build adapters.
- Screenshot-to-code and AI asset generation as separate, permissioned products.

## Local Picowind Acceptance Evidence

The 2026-08-02 LocalWP acceptance run used LiveCanvas, Picowind, WindPress 3.2.86, and Tailwind 4:

1. `Asteria Search` 1.0.1 passed ZIP preview and checksum validation.
2. The child theme installed and activated from a Picostrap baseline.
3. Import created a homepage, separate LiveCanvas header/footer partials, and four media attachments.
4. The local MCP/WindPress build compiled 3,923 candidates from seven providers and stored a 59,097-byte `tailwind.css` cache with SHA-256 `d8571f467de2ed63d6791b7be8d6fc18943d8238b67d57d40c843397e3712efc`.
5. Desktop 1440 px and mobile 390 px checks found exactly one header, main, and footer, no broken images, no console errors, and no horizontal overflow.
6. Rollback restored `picostrap5-child-base`, front page ID 25, WindPress options, and runtime files with no errors.
7. Baseline `main.css` remained present; imported `theme.json` and compiled cache files were absent again after rollback, and the import state changed to `rolled_back`.

This run closes the deterministic local build gate. If the compiler or cache verification is unavailable, the importer now returns `build_required` or `build_failed` and never reports the theme as ready.
