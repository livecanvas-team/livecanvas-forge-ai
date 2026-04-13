# Design System Apply First Slice Design

## Goal

Add a first production-grade `design_system_apply` action to `livecanvas-forge-ai` so external coding agents can apply a design system to the active LiveCanvas stack using the stack's native source of truth:

- `Picostrap` -> WordPress `theme_mods` / Customizer-backed Bootstrap Sass variables
- `Picowind + WindPress` -> WindPress runtime cache, `theme.json`, and DaisyUI/WindPress-native assets

This first slice must be deterministic, previewable, idempotent, and explicit about whether a build was executed or handed off.

## Non-Goals

This slice does **not** include:

- full headless `Picostrap` Sass compilation in every context
- arbitrary full custom DaisyUI theme generation from scratch
- library integrations such as Flowbite, Magic UI, or component pack installation
- advanced remote font discovery/import pipelines
- a full admin UI for authoring design-system payloads

## Product Intent

`LiveCanvas Forge AI` must become the reliable execution layer for large AI-driven site setup tasks. `design_system_apply` is one of those foundation actions.

The agent should be able to say:

- apply these brand colors
- set typography defaults
- update button radius
- activate a DaisyUI skin

and receive a deterministic result envelope describing:

- what changed
- where the source of truth was written
- whether a build was required
- whether the build was executed
- what manual handoff remains, if any

## First-Slice Principle

The first slice uses **deterministic apply + capability-aware build**.

That means:

- always write to the stack-native source of truth
- never fake a compile/build that did not actually happen
- return explicit `build_required`, `build_executed`, and `build_strategy`

## Contract

### Action Name

`design_system_apply`

### Modes

- `preview`
- `apply`

### Input Shape

```json
{
  "action": "design_system_apply",
  "mode": "preview",
  "framework": "picostrap",
  "label": "Brand refresh",
  "preset": {
    "skin": "light",
    "active_theme": "light"
  },
  "colors": {
    "primary": "#0d6efd",
    "secondary": "#6c757d",
    "success": "#198754",
    "info": "#0dcaf0",
    "warning": "#ffc107",
    "danger": "#dc3545",
    "light": "#f8f9fa",
    "dark": "#212529",
    "body_bg": "#ffffff",
    "body_color": "#212529"
  },
  "typography": {
    "font_family_base": "\"Inter\", sans-serif",
    "headings_font_family": "\"Inter\", sans-serif",
    "font_size_base": "1rem",
    "line_height_base": "1.5"
  },
  "radius": {
    "border_radius": "0.375rem",
    "border_radius_sm": "0.25rem",
    "border_radius_lg": "0.5rem"
  },
  "buttons": {
    "btn_padding_y": "0.5rem",
    "btn_padding_x": "1rem",
    "btn_border_radius": "0.375rem"
  },
  "font_assets": {
    "body_font_object": {},
    "headings_font_object": {},
    "fonts_header_code": ""
  },
  "custom_css": ""
}
```

### Output Envelope

```json
{
  "ok": true,
  "action": "design_system_apply",
  "mode": "apply",
  "target_stack": "picostrap",
  "source_of_truth": "theme_mods",
  "summary": "Applied design system tokens to Picostrap theme mods.",
  "changed_keys": [],
  "build_required": true,
  "build_executed": false,
  "build_strategy": "picosass_handoff",
  "compile_url": "http://example.test/?compile_sass=1&sass_nocache=1",
  "warnings": [],
  "data": {}
}
```

## Stack Resolution

`design_system_apply` must not trust the payload blindly when the stack can be inferred from runtime state.

Resolution order:

1. explicit `framework` in payload, if valid
2. detected stack from theme/runtime snapshot
3. fail with a clear error if the stack is unsupported or ambiguous

Valid first-slice values:

- `picostrap`
- `picowind`

## Picostrap Executor

### Source Of Truth

WordPress `theme_mods`, following the native Picostrap Customizer model.

### Why

Picostrap builds its Sass payload from `theme_mods` prefixed with `SCSSvar_...`, then compiles via the existing Picosass browser-driven flow. The first slice should stay aligned with that architecture instead of inventing a parallel configuration source.

### Mapping

#### Colors

- `colors.primary` -> `SCSSvar_primary`
- `colors.secondary` -> `SCSSvar_secondary`
- `colors.success` -> `SCSSvar_success`
- `colors.info` -> `SCSSvar_info`
- `colors.warning` -> `SCSSvar_warning`
- `colors.danger` -> `SCSSvar_danger`
- `colors.light` -> `SCSSvar_light`
- `colors.dark` -> `SCSSvar_dark`
- `colors.body_bg` -> `SCSSvar_body-bg`
- `colors.body_color` -> `SCSSvar_body-color`

#### Typography

- `typography.font_family_base` -> `SCSSvar_font-family-base`
- `typography.headings_font_family` -> `SCSSvar_headings-font-family`
- `typography.font_size_base` -> `SCSSvar_font-size-base`
- `typography.line_height_base` -> `SCSSvar_line-height-base`

#### Radius

- `radius.border_radius` -> `SCSSvar_border-radius`
- `radius.border_radius_sm` -> `SCSSvar_border-radius-sm`
- `radius.border_radius_lg` -> `SCSSvar_border-radius-lg`

#### Buttons

- `buttons.btn_padding_y` -> `SCSSvar_btn-padding-y`
- `buttons.btn_padding_x` -> `SCSSvar_btn-padding-x`
- `buttons.btn_border_radius` -> `SCSSvar_btn-border-radius`

### Font Asset Handling

Optional extra writes:

- `font_assets.body_font_object` -> `body_font_object`
- `font_assets.headings_font_object` -> `headings_font_object`
- `font_assets.fonts_header_code` -> `picostrap_fonts_header_code`

If font assets are missing but family names are present:

- update only the `SCSSvar_*` family settings
- emit a warning that the import/header snippet was not regenerated automatically

### Build Behavior

For the first slice:

- `build_required = true`
- `build_executed = false`
- `build_strategy = "picosass_handoff"`
- `compile_url = home_url('/?compile_sass=1&sass_nocache=1')`

The action must **not** claim a successful compile unless a real compile path exists and was actually executed.

## Picowind / WindPress Executor

### Source Of Truth

WindPress runtime/cache layer plus theme-native DaisyUI preset files and the active theme mod used by Picowind.

### Why

Picowind ships with DaisyUI-based preset files and relies on WindPress for Tailwind runtime/build integration. The first slice should use the bridge that already exists in the plugin.

### First-Slice Targets

#### 1. DaisyUI Skin / Active Theme

- `preset.skin` updates the DaisyUI preset source when supported
- `preset.active_theme` updates Picowind's active `data_theme` theme mod when present

The first slice supports preset/skin activation cleanly, but does **not** attempt full arbitrary DaisyUI custom theme synthesis.

#### 2. Theme JSON / Runtime Tokens

The normalized token payload maps into a generated `theme_json` object and is saved through:

- `save_theme_json`

Supported first-slice areas:

- `settings.color.palette`
- `settings.typography`
- relevant button/global border radius styles

Optional extra writes may use WindPress volume entries only when needed, and only if they support a clear native target.

### Build Behavior

For WindPress:

- `build_required = true`
- try `build_windpress_cache` when capability exists
- if build succeeds:
  - `build_executed = true`
  - `build_strategy = "windpress_runtime_build"`
- if build capability is missing or fails after apply:
  - `build_executed = false`
  - keep `build_required = true`
  - emit warning with the exact reason

## Preview Semantics

`preview` mode must:

- resolve the target stack
- compute the native writes that would happen
- return `changed_keys`
- return the resolved `build_strategy`
- never persist anything

Preview output should remain deterministic and suitable for agent-side confirmation or UI summary.

## Apply Semantics

`apply` mode must:

- perform only the native writes for the resolved stack
- keep the operation idempotent
- collect changed keys
- attempt stack-appropriate build execution if the capability exists
- return an explicit build result

## Error Handling

### Blocking Errors

- unsupported or unresolved stack
- `framework=picowind` but WindPress inactive/unavailable
- invalid or empty `theme_json` payload after normalization
- malformed payload that cannot be normalized into a valid stack write

### Non-Blocking Warnings

- Picostrap font families updated without font import assets/snippets
- WindPress apply succeeded but runtime build not available
- WindPress build attempted and failed after successful writes
- unknown normalized keys ignored because no native mapping exists in the first slice

## Result Data

The `data` field should include stack-specific diagnostics such as:

### Picostrap

- `changed_theme_mods`
- `compile_url`
- `compile_query_args`

### WindPress

- `theme_json`
- `saved_theme_json`
- `build_response`
- `active_theme_mod`

## Architecture

### New Execution Boundary

Introduce a dedicated design-system execution path instead of burying the logic inside generic command branching.

Recommended internal split:

- `LCFA_Design_System_Mapper`
  - normalize incoming payload
  - map normalized tokens to stack-native writes
- `LCFA_Picostrap_Design_System_Executor`
  - preview/apply theme mods
  - resolve compile handoff metadata
- `LCFA_WindPress_Design_System_Executor`
  - preview/apply `theme_json`
  - perform WindPress-native writes/build

This keeps the contract readable and makes later expansion easier.

## Testing Strategy

### Required First-Slice Tests

1. `Picostrap preview`
   - no writes
   - changed theme mods computed correctly
   - `build_required = true`
   - `build_executed = false`

2. `Picostrap apply`
   - correct theme mods written
   - result uses `picosass_handoff`
   - `compile_url` present

3. `WindPress preview`
   - normalized `theme_json` computed
   - no writes
   - `build_strategy = windpress_runtime_build`

4. `WindPress apply`
   - `save_theme_json` invoked
   - `build_windpress_cache` invoked when available
   - result envelope reflects actual build outcome

5. `Error cases`
   - unsupported stack
   - WindPress unavailable
   - invalid payload

## Milestone Definition

The first slice is complete when:

- `design_system_apply` exists as a stable contract
- `Picostrap` writes native `theme_mods`
- `Picowind/WindPress` writes native `theme_json` / runtime payloads
- the result envelope clearly distinguishes apply from build
- preview/apply are both tested

## Future Phases

### Phase 2

- headless or semi-automated Picostrap compile orchestration where technically reliable
- richer DaisyUI preset/theme synthesis
- improved font import automation
- admin-side authoring UI for design-system payloads

### Phase 3

- design-system extraction/import flows
- design-system handoff from screenshots/reference pages
- multi-site and remote-first orchestration
