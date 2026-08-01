# Picostrap Site Generation Flow

This document records the tested developer flow used to build an original LiveCanvas one-page site from a visual reference. The reference informs composition and product patterns only. Brand names, copy and media are not copied.

## Tested Outcome

The `Houseflow` fixture generated the fictional `Hearthline` site on a clean LocalWP installation:

- Picostrap parent plus child theme installed and activated;
- 20 Bootstrap/Customizer design tokens applied;
- child-theme SCSS written through preview/write abilities and compiled with Dart Sass;
- three original images generated with Codex and imported into the Media Library;
- header and footer stored as separate LiveCanvas partials;
- one-page homepage stored as editable LiveCanvas HTML with page-scoped CSS and JavaScript;
- Journal page assigned as the WordPress posts index;
- single-post and posts-index layouts assigned through LiveCanvas dynamic templates;
- SEO title, description and test-site `noindex` metadata applied;
- caches flushed and the compiled bundle version bumped;
- WindPress frontend runtime suppressed on Picostrap to prevent Tailwind/Bootstrap utility collisions;
- desktop/mobile visual checks and an exact-hash rollback test passed.

The reproducible implementation is in [`tests/e2e/houseflow/`](../tests/e2e/houseflow/). The final machine-readable visual report is [`houseflow-visual-report.json`](screenshots/houseflow-visual-report.json).

## Developer Sequence

1. Inspect the reference site's hierarchy, rhythm, colors, typography and recurring component patterns.
2. Create a new brand, copy set and media brief. Do not reuse protected source assets.
3. Apply Bootstrap design tokens with `preview-design-system` and `apply-design-system`.
4. Preview and write child-theme `_theme_variables.scss` and `_custom.scss` with automatic backups.
5. Compile `sass/main.scss`, store `css-output/bundle.css`, then flush caches.
6. Generate and upload original media through `media-upload`.
7. Preview/apply the header and footer as separate global partials.
8. Preview/apply the homepage, including page-scoped CSS/JS and SEO metadata.
9. Create the WordPress posts index and apply single/index dynamic templates.
10. Run desktop/mobile visual QA, then prove rollback using a temporary targeted patch.

## Original Image Briefs

- Hero: warm documentary family breakfast scene, family planning together, wide editorial crop, no text or logos.
- Meal planning: parent and child preparing food and checking a handwritten plan, warm natural light, no text or logos.
- Routines: family preparing school bags beside a simple weekly routine board, candid editorial photography, no text or logos.

The generated source assets are stored in [`tests/e2e/houseflow/assets/`](../tests/e2e/houseflow/assets/), making the fixture deterministic after generation.

## Validation Result

Playwright checks the homepage, Journal and a generated single post at 1440x1000 and 390x844. The current report passes all six page/viewport combinations with:

- HTTP 200;
- one header, main and footer;
- no JavaScript page errors;
- no broken images;
- no horizontal overflow;
- visible desktop navigation and expanded mobile navigation links;
- visible Bootstrap accordion content;
- an operational mobile navigation toggle.

The rollback check applies one exact text patch, requires a stable `audit_id`, restores through `restore-audit-rollback`, and verifies the original SHA-256 hash byte-for-byte.

## Known Upstream Note

The current Picostrap Bootstrap source compiles successfully but Dart Sass reports upstream deprecation warnings for legacy `@import`, global built-ins and slash division. These warnings do not block the bundle today, but Picostrap should migrate before the corresponding Dart Sass removals.

Picowind is intentionally outside this test. It requires a separate WindPress/Tailwind generation flow and its own acceptance run.
