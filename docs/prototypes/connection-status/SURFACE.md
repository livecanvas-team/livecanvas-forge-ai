# Connection status surface

Mode: **Operate**. Standalone prototype, not a plugin release.

## Current direction

The user rejected the text-heavy proposal. The refinement shows a short status, a visible startup prompt and a clipboard button. One sentence names the paste destination. Removed the progress strip, repeated status paragraphs and task headings; moved diagnostics and demo controls into disclosures. Access scope and code matching remain visible at approval.

## Constraints

English interface. Synthetic example.local data only. Five agent variants; Claude Code uses Hello Alfred. Claude Desktop setup names Terminal or PowerShell. Sample prompts contain no installer or token. No WordPress requests, permission grants or production changes.

## Verification

`verify.cjs` covers 300 state/agent/width combinations, exact clipboard text, selected-text fallback, approval gating and simulated verification. Current evidence lives at `../../../.impeccable/review/connection-status/minimal/`. Desktop captures use 1280 × 720; mobile captures use 390 × 844. The earlier reviewer verdict applies to the superseded long-form design, not this refinement.

## Unimplemented

Real installation, authenticated connection evidence, compatibility detection, session persistence and translation integration remain backend work. Freshness and compatibility policy are undecided. See README.md for the production handoff.
