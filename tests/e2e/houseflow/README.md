# Houseflow Picostrap generation flow

This fixture simulates a coding agent building an original one-page LiveCanvas site after studying a reference site. It does not copy the reference brand, text, or assets.

The flow uses WordPress Abilities for design-system preview/apply, child-theme SCSS preview/write, media upload, global header/footer partials, page preview/apply, dynamic template preview/apply, Picostrap bundle storage, and cache flush.

## Run on the Local test site

```bash
export LCFA_WP_ROOT="/Users/commander/Local Sites/test-ai-forge/app/public"
export LCFA_PHP_BIN="/Users/commander/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php"
export LCFA_PHP_INI="/Users/commander/Library/Application Support/Local/run/noPwcVjz7/conf/php/php.ini"
bash tests/e2e/houseflow/run.sh
```

The script is idempotent for the homepage, templates, sample posts, and uploaded generated assets. It stores the original homepage and LiveCanvas settings in `lcfa_houseflow_state` before changing them.

The flow also performs a reversible targeted text patch against the generated homepage. It requires a stable `audit_id`, restores through `restore-audit-rollback`, and verifies the final content hash byte-for-byte. The final stage runs Playwright against the homepage, Journal index and one generated single post at desktop and mobile sizes. Screenshots and the machine-readable visual report are written to `docs/screenshots/`. Set `LCFA_RUN_VISUAL_CHECK=0` only when running in an environment without a browser runtime.

Generated assets were created with the built-in Codex image generation tool and copied into `assets/` so the test is reproducible.
