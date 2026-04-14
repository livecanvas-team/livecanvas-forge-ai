# Picostrap Headless Compile Design

## Goal

Enable `Picostrap` Sass compilation to run headlessly for both `local` and `remote` sites through the Forge AI bridge, without depending on an authenticated admin browser hitting `?compile_sass=1`.

This closes the current gap in the flow:

`simple prompt -> compose -> preview -> apply theme mods -> compile bundle -> preview/live site`

Today, Forge AI can already:
- compose a Picostrap-compatible design system preview
- apply the resulting Bootstrap tokens to native Picostrap `theme_mods`
- return a browser compiler handoff URL

But it cannot yet guarantee automatic bundle compilation in the MCP-driven workflow. The new slice adds an explicit bridge-driven compile pipeline.

---

## Constraints

- Must work for both `local` and `remote` WordPress sites
- Must not assume `Node` or shell access on the remote WordPress server
- Must not depend on an admin browser session or browser JS execution
- Must preserve the existing Picostrap source of truth:
  - theme mods for Bootstrap variables
  - child-theme bundle save target
- Must remain compatible with the currently active Picostrap child/parent theme structure
- Must be explicit about whether compile actually executed; no false-success handoffs

---

## Recommended Approach

### Option A: Bridge-side Dart Sass compiler

The WordPress plugin exposes a compile manifest plus safe source-fetch/store endpoints. The MCP bridge running on the user's machine compiles Sass with `Dart Sass`, then stores the generated bundle back into WordPress.

### Option B: Server-side PHP Sass compiler

The plugin compiles on the server with a PHP Sass engine.

### Option C: Hybrid fallback tree

Primary bridge compiler, with PHP compiler fallback and browser handoff as tertiary path.

### Recommendation

Use **Option A**.

Reasoning:
- it is the only path that is reliable across both `local` and `remote`
- it uses a real Sass compiler with better Bootstrap/Picostrap compatibility than a PHP fallback
- it aligns with the product architecture, where the code agent and MCP bridge are already part of the normal workflow
- it avoids remote shell assumptions entirely

---

## Architecture Overview

The compile pipeline becomes:

1. `design_system_compose`
2. `design_system_apply`
3. `picostrap_compile_bundle`
4. `store bundle`
5. `frontend preview/live site`

The new compile action is independent and reusable.

### New units

#### `LCFA_Picostrap_Compile_Manifest`
Builds the normalized compile manifest from WordPress/Picostrap state.

#### `LCFA_Picostrap_Bundle_Store`
Validates and stores compiled CSS into the active stylesheet directory and bumps bundle version.

#### `LCFA_Picostrap_Compile_Service`
Coordinates manifest generation, optional bridge execution metadata, and fallback reporting.

#### MCP bridge Picostrap compiler
A Node-side compiler worker that:
- receives compile requests
- compiles with Dart Sass
- resolves imports from local disk or remote fetch endpoints
- stores the resulting bundle through the plugin

#### REST/MCP endpoints
- `get_picostrap_compile_manifest`
- `get_picostrap_compile_source`
- `store_picostrap_bundle`

---

## Contract: `picostrap_compile_bundle`

### Input

```json
{
  "action": "picostrap_compile_bundle",
  "mode": "apply",
  "force": false,
  "source": "theme_mods",
  "label": "optional audit label"
}
```

### Output

```json
{
  "ok": true,
  "action": "picostrap_compile_bundle",
  "mode": "apply",
  "execution_target": "local",
  "target_stack": "picostrap",
  "source_of_truth": "theme_mods",
  "build_strategy": "bridge_dart_sass",
  "build_required": true,
  "build_executed": true,
  "bundle_path": "wp-content/themes/picostrap5-child-base/css-output/bundle.css",
  "bundle_url": "http://localhost:8887/wp-content/themes/picostrap5-child-base/css-output/bundle.css?ver=23",
  "bundle_version": 23,
  "compiled_at": "2026-04-14 10:30:00",
  "warnings": [],
  "data": {}
}
```

### Semantics

- `build_executed: true` only when the bridge actually compiled and the plugin stored the bundle
- `build_executed: false` when the compile could not be run automatically
- `compile_url` may still be returned as a browser fallback, but it must not be treated as success

---

## Manifest Design

The bridge compiler must not receive only tokens. It needs a complete compile manifest.

### Manifest shape

```json
{
  "framework": "picostrap",
  "stylesheet": "picostrap5-child-base",
  "template": "picostrap5",
  "is_child_theme": true,
  "main_sass": "$primary: #ff2d55; ... @import 'main';",
  "entry_virtual_file": "main.scss",
  "base_relative_dir": "sass",
  "import_roots": [
    {
      "type": "child",
      "relative": "wp-content/themes/picostrap5-child-base/sass",
      "absolute": "/Users/commander/Studio/consultala/wp-content/themes/picostrap5-child-base/sass"
    },
    {
      "type": "parent",
      "relative": "wp-content/themes/picostrap5/sass",
      "absolute": "/Users/commander/Studio/consultala/wp-content/themes/picostrap5/sass"
    }
  ],
  "target_bundle_relative_path": "wp-content/themes/picostrap5-child-base/css-output/bundle.css",
  "current_bundle_version": 22,
  "theme_mods": {
    "SCSSvar_primary": "#ff2d55"
  },
  "source_map": false,
  "compile_mode": "expanded"
}
```

### Source of `main_sass`

`main_sass` must be built from Picostrap’s native function:
- `ps_get_main_sass()`

This is important. The plugin must not reimplement the Sass source builder; it must reuse the exact theme logic used by Picostrap today.

---

## Source Loading Strategy

### Local

In `local`, the bridge can compile directly from the host filesystem.

Manifest includes absolute host paths for child and parent import roots. The bridge importer tries:
1. child theme `sass/`
2. parent theme `sass/`

This matches the current browser compiler semantics.

### Remote

In `remote`, the bridge cannot read the remote filesystem directly. The plugin must expose a safe source endpoint.

#### Endpoint
- `get_picostrap_compile_source`

#### Input
```json
{
  "import_path": "bootstrap/_variables.scss"
}
```

#### Output
```json
{
  "ok": true,
  "normalized_path": "bootstrap/_variables.scss",
  "contents": "...scss file contents...",
  "origin": "parent"
}
```

### Remote importer behavior

During compile, the bridge importer:
- requests missing files from the remote endpoint
- caches them for the duration of the compile
- resolves imports only from approved roots

### Security rules

The source endpoint must:
- accept only relative paths
- reject `..`
- reject paths outside theme `sass/`
- reject non-`.scss` requests
- resolve only against:
  - child theme `sass/`
  - parent theme `sass/`

---

## Bundle Store Strategy

After compile, the bridge calls:
- `store_picostrap_bundle`

The plugin must:
- save CSS into `get_stylesheet_directory()/css-output/bundle.css`
- increment `css_bundle_version_number`
- return final bundle path and bundle URL

This preserves Picostrap’s actual frontend behavior, where the active stylesheet (child theme) owns the bundle.

---

## Integration With `design_system_compose`

### Current state

`design_system_compose` can already:
- build preview payload
- optionally auto-apply theme mods
- return `preview_url`
- return browser `compile_url`

### New state

When `auto_apply` is enabled for Picostrap:
1. compose preview
2. apply tokens
3. call `picostrap_compile_bundle`
4. return:
   - `preview_url`
   - `frontend_url`
   - `bundle_url`
   - `bundle_version`
   - `compiled_at`

If automatic compile fails:
- return `build_executed: false`
- include a clear warning
- optionally include the old browser `compile_url` as fallback

This preserves automation while staying honest about actual execution.

---

## Fallback Policy

### Primary
- `bridge_dart_sass`

### Secondary
- explicit browser fallback via `compile_url`

### Not included in first slice
- PHP Sass compiler fallback
- watch mode
- sourcemaps
- minification
- non-Sass asset pipelines

The first slice must prefer correctness and explicit behavior over wide fallback coverage.

---

## Error Handling

### Manifest failure
- `ok: false`
- no compile attempt
- message indicates manifest/source generation failure

### Bridge unavailable
- `build_required: true`
- `build_executed: false`
- warning: bridge compiler unavailable

### Source fetch failure
- `ok: false`
- include missing import path in warnings/data

### Store failure
- `ok: false`
- compile may have succeeded in memory, but bundle save failed
- do not bump bundle version

### Remote path violation
- hard fail
- no source returned
- warning/message indicates invalid import path

---

## First Slice Testing

### 1. Manifest generation
- returns `ps_get_main_sass()` output
- includes import roots
- identifies active child stylesheet target
- exposes target bundle relative path

### 2. Bundle store
- stores CSS in active stylesheet directory
- increments `css_bundle_version_number`
- returns final bundle URL

### 3. Local bridge compile
- compile a real Picostrap manifest through the bridge
- store resulting bundle
- verify bundle URL and version change

### 4. Remote simulated compile
- importer resolves SCSS through endpoint fetch
- compiled bundle stores successfully
- returned URL is correct

### 5. Compose auto-apply integration
- `design_system_compose` with `auto_apply: true`
- if compiler available:
  - `build_executed: true`
  - `bundle_url` present
- if unavailable:
  - explicit warning
  - fallback compile URL present

---

## Milestone Scope

This first slice includes:
- headless Picostrap compile for local and remote
- manifest generation from native Picostrap source builder
- safe remote SCSS source loading
- bundle store into active child theme
- integration with `design_system_compose`

This first slice excludes:
- Picowind/WindPress compile orchestration
- sourcemaps
- minification
- watch mode
- PHP compiler fallback
- asset pipelines beyond Sass

---

## Recommendation

Implement the bridge-driven Dart Sass compiler as the primary Picostrap compile path.

This is the only option that is technically coherent with the product direction:
- AI/code agent driven
- same semantics in local and remote
- no server shell assumptions
- no dependency on a hidden browser admin session
