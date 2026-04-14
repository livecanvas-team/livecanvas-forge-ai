# Design System Compose Preview Design

## Goal

Add a new high-level `design_system_compose` capability to `livecanvas-forge-ai` so a coding agent can take a simple creative prompt and turn it into a previewable, stack-valid design system before any write happens.

This feature sits **above** `design_system_apply`.

Flow:

1. user writes a simple natural-language brief
2. AI composes a structured design system
3. plugin returns a preview package
4. user or agent confirms
5. plugin applies the already-structured payload through `design_system_apply`

## Why This Layer Is Needed

`design_system_apply` is intentionally deterministic. It expects normalized tokens and writes them to the active stack's native source of truth.

That is correct for execution, but it is the wrong interface for a human or for a generic AI model prompt. A model given an open-ended request like:

- "make me a vibrant premium design system"
- "use bold colors and elegant typography"
- "make it look fashion-tech"

will usually invent:

- freeform keys
- component-level concepts not mapped to the stack
- unsupported token names
- payloads stuffed into `content`

The product therefore needs a separate composition layer that:

- accepts vague or creative input
- constrains the output to supported stack tokens
- returns a preview first
- only hands off valid payloads to `design_system_apply`

## First Slice Scope

This first slice is `Picostrap-first`.

Supported path:

- `simple prompt`
- `compose`
- `preview`
- `optional apply`

Supported stack:

- `Picostrap`

Not in this slice:

- `Picowind` composition
- image-based brand extraction
- multi-variant comparison mode
- fully automatic font asset import resolution
- generated screenshots or visual canvas rendering

## Product Intent

The user experience should feel like this:

> "Give me a playful but premium Bootstrap design system with bright colors, round buttons, and expressive headings."

The user should **not** need to know:

- Bootstrap variable names
- Picostrap `SCSSvar_*` keys
- which tokens are supported
- how `PicoSass` compiles

The plugin should return:

- a short creative summary
- the selected palette
- typography choices
- button/radius choices
- warnings for anything unsupported
- the exact `apply_payload` that can be executed safely

## New Capability

### Action Name

`design_system_compose`

### Mode

This action is inherently `preview-first`.

In the first slice it does **not** write to theme state directly.

It returns:

- `mode: "preview"`
- `apply_payload`
- `preview`

Optional later behavior may allow:

- `auto_apply: true`

but that is not part of this slice.

## Input Contract

### Minimal Input

```json
{
  "action": "design_system_compose",
  "framework": "picostrap",
  "prompt": "Create a bold, vibrant, slightly premium Bootstrap design system with bright colors, rounded buttons, and expressive headings."
}
```

### Extended Input

```json
{
  "action": "design_system_compose",
  "framework": "picostrap",
  "prompt": "Create a vibrant premium design system for a fashion-tech brand.",
  "brand_personality": [
    "bold",
    "premium",
    "energetic"
  ],
  "accessibility_level": "balanced",
  "references": [],
  "auto_apply": false
}
```

### Accepted First-Slice Fields

- `framework`
- `prompt`
- `brand_personality` optional
- `accessibility_level` optional
- `references` optional but ignored unless safely understood
- `auto_apply` optional, but must be ignored in the first slice

## Output Contract

```json
{
  "ok": true,
  "action": "design_system_compose",
  "mode": "preview",
  "execution_target": "local",
  "target_stack": "picostrap",
  "summary": "Composed a vibrant Picostrap design system preview.",
  "message": "Design system preview prepared.",
  "warnings": [],
  "preview": {
    "mood": "vibrant, premium, energetic",
    "palette": {
      "primary": "#ff2d55",
      "secondary": "#6a00ff",
      "success": "#39ff14",
      "info": "#00cfff",
      "warning": "#ffb703",
      "danger": "#ff3b30",
      "light": "#fff4d6",
      "dark": "#111827",
      "body_bg": "#fff8ef",
      "body_color": "#1f2937"
    },
    "typography": {
      "font_family_base": "\"Poppins\", sans-serif",
      "headings_font_family": "\"Bebas Neue\", sans-serif",
      "font_size_base": "1rem",
      "line_height_base": "1.6"
    },
    "radius": {
      "border_radius": "1rem",
      "border_radius_sm": "0.6rem",
      "border_radius_lg": "1.4rem"
    },
    "buttons": {
      "btn_padding_y": "0.75rem",
      "btn_padding_x": "1.4rem",
      "btn_border_radius": "999px"
    }
  },
  "apply_payload": {
    "action": "design_system_apply",
    "framework": "picostrap",
    "colors": {},
    "typography": {},
    "radius": {},
    "buttons": {}
  },
  "data": {
    "supports_apply": true,
    "preview_only": true
  }
}
```

## Core Rule

`design_system_compose` must only output keys that `design_system_apply` already supports for the target stack.

For `Picostrap`, the first-slice supported output is limited to:

### Colors

- `primary`
- `secondary`
- `success`
- `info`
- `warning`
- `danger`
- `light`
- `dark`
- `body_bg`
- `body_color`

### Typography

- `font_family_base`
- `headings_font_family`
- `font_size_base`
- `line_height_base`

### Radius

- `border_radius`
- `border_radius_sm`
- `border_radius_lg`

### Buttons

- `btn_padding_y`
- `btn_padding_x`
- `btn_border_radius`

Anything outside this set must either:

- be dropped
- be translated
- or produce a warning

It must not leak into `apply_payload`.

## Architecture

The clean architecture is:

- `design_system_compose`
  creative composition, validation, preview generation
- `design_system_apply`
  deterministic execution

That means the system should not ask a model to call `design_system_apply` directly from a vague prompt. Instead it should:

1. compose to a normalized intermediate design intent
2. validate against stack support
3. emit `apply_payload`
4. let the caller explicitly apply it

## First-Slice Internals

### 1. Prompt Parsing

The composition layer should accept a natural-language brief and normalize it into:

- `mood`
- `color direction`
- `typography direction`
- `shape language`

This does not need a separate ML subsystem. In the first slice it can be implemented as:

- AI-assisted composition in the coding agent
- plugin-side normalization and validation

The key requirement is that the final plugin contract returns a strict, stack-safe payload.

### 2. Validation Layer

Before returning success, the plugin must:

- remove unsupported keys
- ensure token values are scalar and usable
- ensure the output is valid for `Picostrap`

If the prompt asks for unsupported things such as:

- card elevation systems
- animation systems
- custom shadow recipes
- alternate semantic color names like `accent`

the plugin should return warnings such as:

- `"accent" is not a first-slice Picostrap token and was omitted`
- `"card shadow" preview is conceptual only and was not included in apply_payload`

### 3. Preview Package

The preview package should be the exact human-facing layer.

It should contain:

- creative summary
- chosen palette
- chosen typography
- chosen button feel
- apply-ready payload

It should not require reading raw technical JSON to understand the result.

### 4. Apply Handoff

The `apply_payload` returned by `design_system_compose` must be directly executable by:

- `run_lc_command`
- `design_system_apply`

No additional translation should be needed after compose completes.

## Example User Experience

### User Prompt

> "Create a bold, vibrant premium design system with bright pink, electric blue, rounded UI, and strong display headings."

### Compose Result

The plugin returns:

- summary: vibrant premium fashion-tech direction
- palette preview
- typography preview
- button/radius preview
- explicit warning if fonts are conceptual only
- `apply_payload`

### Apply Result

If accepted, the agent calls `design_system_apply` with the returned payload.

For `Picostrap`, the apply result then returns:

- changed `SCSSvar_*`
- `build_required: true`
- `build_executed: false`
- `build_strategy: picosass_handoff`
- `compile_url`

## Error Handling

### Unsupported Stack

If the active or requested stack is not supported:

- `ok: false`
- clear message
- no apply payload

### Empty Composition

If the prompt is too vague and no safe token set can be inferred:

- `ok: false`
- clear message asking for more direction

### Partial Composition

If only some tokens can be safely inferred:

- `ok: true`
- warnings included
- partial `apply_payload`

### Unsupported Concepts

If the prompt includes concepts outside first-slice support:

- keep preview safe
- warn explicitly
- do not leak unsupported keys into `apply_payload`

## Testing Strategy

Minimum first-slice coverage:

1. `compose preview for Picostrap`
   - simple prompt in
   - preview out
   - apply payload contains only supported keys

2. `unsupported concept is dropped`
   - prompt mentions `accent` or `card shadow`
   - warning returned
   - unsupported keys absent from `apply_payload`

3. `apply payload roundtrip`
   - compose result passed to `design_system_apply`
   - apply succeeds

4. `unsupported stack`
   - clean error

## Future Work

### Next Slice

- `Picowind` composition support
- DaisyUI skin-aware composition
- richer preview card rendering in admin

### Later

- image-inspired palette extraction
- multi-variant preview generation
- font import assistance
- saved design-system drafts/history

## Recommendation

Implement `design_system_compose` as a strict preview-first orchestration layer for `Picostrap` before attempting generalized stack composition.

That gives:

- simpler prompts
- safer AI behavior
- deterministic apply
- a UX that matches how humans actually think about design systems

without weakening the correctness of `design_system_apply`.
