# Image Generation Provider Architecture

## Purpose

AI Bridge should let a trusted coding agent request original page assets during a generation flow without tying the feature to Codex, OpenCode or another client. WaveSpeed and fal.ai are image inference providers, not coding-agent model choices, so provider settings must remain independent from the connected agent.

The current Picostrap case study uses built-in Codex image generation followed by `media-upload`. The architecture below keeps that workflow valid while allowing server-side providers later.

## Proposed Provider Contract

Every provider adapter should implement the same internal operations:

```text
capabilities()       models, sizes, formats, edit/reference support
estimate(request)    estimated cost and execution time without generation
submit(request)      create a provider job after explicit apply
status(job_id)       pending, running, complete, failed or cancelled
cancel(job_id)       stop a supported job
download(job_id)     return validated result metadata and bytes
```

Initial adapters can be:

- `codex_builtin`: manual/handoff mode; Codex creates the asset and AI Bridge imports it;
- `openai`: OpenAI image generation/edit API;
- `wavespeed`: WaveSpeed generation/edit endpoints;
- `fal`: fal.ai image generation/edit endpoints.

## Agent-Facing Flow

1. The coding agent submits an image brief, purpose, aspect ratio, target page and optional Media Library reference IDs.
2. `image-generation-preview` validates policy and returns provider/model, estimated cost, dimensions and the planned Media Library operation.
3. An explicit `image-generation-apply` creates the paid provider job.
4. AI Bridge polls with `image-generation-status`; long jobs never block an MCP request indefinitely.
5. The server downloads the result, validates MIME type, dimensions, byte size and checksum, then imports it through the existing media service.
6. AI Bridge returns attachment ID, URL, alt-text proposal, provider metadata, cost and `audit_id`.
7. The page or partial is changed only in a separate preview/apply operation.

Suggested future abilities:

```text
livecanvas-forge-ai/image-generation-preview
livecanvas-forge-ai/image-generation-apply
livecanvas-forge-ai/image-generation-status
livecanvas-forge-ai/image-generation-cancel
```

## Settings UX

The WordPress admin should expose one `Image generation` section:

- enabled/disabled master switch;
- default provider and model;
- speed/quality preset;
- allowed dimensions and formats;
- maximum images and estimated spend per run;
- optional style and brand constraints;
- provider connection test;
- recent jobs, costs and revoke/delete controls.

Codex and OpenCode use the same MCP abilities. They should not each receive a separate provider configuration screen.

## Secret Handling

Provider keys must never be included in MCP configuration, prompts, tool results, JavaScript or page HTML.

Preferred resolution order:

1. server environment or `wp-config.php` constants such as `LCFA_WAVESPEED_API_KEY` and `LCFA_FAL_API_KEY`;
2. an encrypted WordPress secret store when hosting supports a server-held encryption key;
3. no provider: fall back to the `codex_builtin` handoff flow.

WordPress stores only a masked key hint, provider status and secret reference. Logs must redact authorization headers and provider responses that contain credentials.

## Guardrails

- Image generation remains disabled by default on production.
- The MCP session needs a future `image_generation` scope plus the existing `media` scope.
- Preview is mandatory before an operation that can incur cost.
- Per-run and monthly quotas are enforced server-side.
- Downloads reject executable content, SVG with scripts, unexpected MIME types and oversized files.
- Provider, model, prompt hash, cost, output checksum, attachment ID and actor are audited.
- Generated media remains separate from page apply, so page rollback does not silently delete shared assets.

## Delivery Phases

1. Add provider interface, job storage, estimate/status API and `codex_builtin` adapter.
2. Add server-side WaveSpeed and fal.ai adapters plus secure settings and quotas.
3. Expose scoped MCP preview/apply abilities and connect them to `media-upload`.
4. Add reference-image editing, design-system-aware prompts and visual QA integration.

This keeps the current bridge client-neutral and lets page generation request images without exposing provider credentials to Codex or OpenCode.
