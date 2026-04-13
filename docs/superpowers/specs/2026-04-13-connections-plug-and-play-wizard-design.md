# LiveCanvas Forge AI Connections Plug-And-Play Wizard Design

Date: 2026-04-13
Status: Proposed
Scope: `livecanvas-forge-ai` plugin only

## Goal

Turn the `Connections` page into the shortest reliable path between a WordPress site and a coding agent.

The page should stop behaving like a transport settings form and start behaving like a guided onboarding surface.

The core outcome is:

- choose a coding agent
- choose local or remote mode
- generate the correct MCP configuration
- install it locally when possible
- provide a downloadable bundle when direct install is not possible
- verify the connection with a smoke test

## Product Context

`LiveCanvas Forge AI` is not trying to replace LiveCanvas.

It is the execution layer that lets a coding agent perform structural work inside WordPress and then hand the result back to LiveCanvas for fine tuning.

That makes agent connection the first real product moment. If this part is hard, the rest of the plugin feels broken even when the runtime is technically correct.

## Problem

The current `Connections` page mixes too many concepts in one place:

- coding-agent bootstrap
- local MCP bridge internals
- remote WordPress-to-WordPress settings
- package URLs for framework installers
- low-level host, port, command, and transport controls

This is useful for advanced users, but it is the wrong default UX.

The most important workflow should be:

1. open `Connections`
2. choose `Codex`, `OpenCode`, `Cursor`, `Claude Code`, or `Generic MCP`
3. confirm whether the site is local or remote
4. install or download the correct connection payload
5. run a smoke test
6. see `Ready`

## Non-Goals

- redesign the entire Forge AI admin
- replace the existing MCP contract
- implement a true remote MCP server endpoint in this milestone
- auto-write arbitrary global config files outside the WordPress workspace
- collapse the remote WordPress companion flow into the coding-agent flow

## Design Principles

### Plugin-first, transport-second

The page should explain the user outcome first and expose transport details only when needed.

### Same wizard, different outputs

All supported coding agents should use the same onboarding flow.

The UI stays consistent. The output artifact changes per client.

### Local automation where it is safe

If the site is local and the target file lives in the current workspace, the plugin may write the config artifact after explicit confirmation.

### Bundle-first for remote

If the site is remote, the plugin should generate a client-ready bundle and let the user download or copy it. The plugin should not pretend it can write onto the user machine from the remote server.

### Verification before “ready”

The page should not claim success until the generated config has been validated with a smoke test.

## Page Structure

The new `Connections` page should be rendered in this order:

1. `Connect your coding agent`
2. `Connection wizard` or `Connection status`
3. `Remote site`
4. `Advanced settings`

## Page States

### State 1: Not Connected

Show the coding-agent block first, followed immediately by the wizard.

This state is used when:

- no preferred client is configured
- no usable bootstrap payload exists
- the latest smoke test has not passed yet

Primary CTA:

- `Start connection setup`

### State 2: Ready

Show a compact readiness card instead of the full wizard.

The card should show:

- client name
- local or remote mode
- REST base
- last verified time
- smoke test status

Primary actions:

- `Reconfigure`
- `Regenerate bundle`
- `Run checks`

### State 3: Needs Attention

Show the last known client and mode, but reopen the wizard at the failing step.

Typical causes:

- token rotated
- REST base changed
- local file missing
- remote credentials invalid
- smoke test failed

## Wizard Flow

The wizard is shared across all clients.

### Step 1: Choose your coding agent

Choices:

- `Codex`
- `OpenCode`
- `Cursor`
- `Claude Code`
- `Generic MCP client`

The selected client determines:

- output file format
- command examples
- install mode options
- smoke test copy

### Step 2: Choose where the agent will connect

Choices:

- `This local site`
- `Remote site`

Behavior:

- local mode uses the current site bootstrap and may enable workspace writes
- remote mode uses the remote target payload and disables local file writes

### Step 3: Review connection details

Resolve and display:

- server name: `livecanvas-forge`
- `LCFA_REST_BASE`
- `LCFA_MCP_TOKEN`
- `LCFA_WP_ROOT` when local filesystem access is available
- effective command

This step must clearly explain:

- coding agents use `REST base + MCP token`
- WordPress Application Passwords belong to the remote WordPress companion flow, not the coding-agent flow

### Step 4: Choose install method

Possible install methods:

- `Write config in this workspace`
- `Download client bundle`
- `Copy command or snippet`

Availability rules:

- local mode may expose all three, depending on client
- remote mode exposes only `Download` and `Copy`

### Step 5: Verify connection

Run or display a standard smoke test based on `get_snapshot`.

The wizard should record:

- success or failure
- timestamp
- client
- mode
- key warning message if any

Completion state:

- success -> `Ready`
- failure -> `Needs Attention`

## Client Outputs

### Codex

Primary artifact:

- generated `codex mcp add livecanvas-forge ...` command

Optional local helper:

- shell script in the workspace for repeatable registration

### OpenCode

Primary artifact:

- `opencode.json`

Expected config model:

- local MCP server entry
- command array
- environment object
- `enabled: true`

### Cursor

Primary artifact:

- workspace-level MCP config file

The exact file path should remain configurable by implementation, but the artifact should be generated from the same normalized bundle model.

### Claude Code

Primary artifact:

- client-ready MCP registration snippet

Optional local helper:

- shell script or JSON snippet stored in the workspace

### Generic MCP Client

Primary artifact:

- server command
- environment block
- smoke test command

## Local vs Remote Rules

### Local

Definition:

- WordPress runs on the current machine
- the coding agent runs on the current machine
- workspace path is known or can be inferred

Allowed behavior:

- write workspace-safe config files after confirmation
- generate helper scripts
- include `LCFA_WP_ROOT` when local filesystem access is meaningful

### Remote

Definition:

- the target WordPress site is not the current local runtime

Allowed behavior:

- generate bundle files
- provide download and copy actions
- run remote smoke tests against the remote REST base when credentials are available

Disallowed behavior:

- pretend to write local client config files on the user machine

## Normalized Connection Bundle

All wizard outputs should be generated from one normalized bundle object.

```json
{
  "client": "opencode",
  "mode": "local",
  "server_name": "livecanvas-forge",
  "command": [
    "node",
    "/absolute/path/to/mcp/bin/livecanvas-forge-mcp.js",
    "--transport=stdio",
    "--agent=opencode"
  ],
  "environment": {
    "LCFA_REST_BASE": "http://localhost:8887/wp-json/lcfa/v1/",
    "LCFA_MCP_TOKEN": "token",
    "LCFA_WP_ROOT": "/Users/example/project"
  },
  "workspace_files": [
    {
      "path": "/absolute/path/to/project/opencode.json",
      "type": "json",
      "label": "OpenCode config"
    }
  ],
  "download_files": [
    {
      "name": "opencode.json",
      "mime": "application/json"
    }
  ],
  "smoke_test_command": "node ... --tool get_snapshot --output pretty",
  "status": "ready"
}
```

This object becomes the single source for:

- UI rendering
- file generation
- downloads
- copy actions
- smoke test commands

## Data Model Additions

The plugin should store lightweight onboarding state in options.

Recommended fields:

- `preferred_client`
- `connection_wizard_client`
- `connection_wizard_mode`
- `connection_status`
- `connection_last_verified_at`
- `connection_last_error`
- `connection_last_bundle_hash`

These values are operational metadata, not secrets beyond the existing token handling.

## UI Components

### Coding-agent hero

A top card that explains the purpose in one sentence and shows the current state badge.

### Wizard stepper

A compact, linear UI for:

- client
- mode
- install method
- verify

### Install artifact panel

A dedicated panel that shows exactly one of:

- files to write
- files to download
- commands to copy

### Readiness card

A compact summary shown when setup is complete.

### Advanced settings

The old low-level form should remain available, but collapsed below the primary onboarding flow.

It still owns:

- transport
- host
- port
- custom server command
- remote companion credentials
- package URLs

## Local File Writing Rules

Automatic file writing is allowed only when all of the following are true:

- the site is local
- the destination file is inside the current workspace
- the artifact is known and deterministic
- the user explicitly confirms the write

The plugin must never silently overwrite an existing file without warning.

If a file already exists, the UI should offer:

- overwrite
- create backup then overwrite
- cancel

## Remote Bundle Rules

For remote connections, the plugin should generate a client bundle without assuming access to the user workstation.

The bundle should be exposed as:

- downloadable file
- copyable snippet
- visible smoke test command

For multi-artifact clients, the plugin may offer a zip download named like:

- `livecanvas-forge-opencode-bundle.zip`
- `livecanvas-forge-cursor-bundle.zip`

## Verification Contract

The standard smoke test should use `get_snapshot`.

Success criteria:

- plugin reachable
- token accepted
- snapshot returns stack metadata

Optional follow-up checks:

- `get_inventory`
- `run_lc_command` with `site_audit` in preview mode

The wizard must surface the failing reason directly, not as a generic error.

## Interaction With Existing Systems

### Context Builder

`LCFA_Context_Builder` already produces per-client bootstrap payloads.

This should become the source for the normalized bundle builder, but the builder must also correct environment values that are too runtime-specific for the current machine context.

Example:

- local bootstrap should prefer the real local workspace root instead of a container-only path when the plugin can detect it reliably

### Admin

`LCFA_Admin` should stop rendering `Connections` as one flat settings form.

It should render:

- top hero
- wizard or status
- remote companion card
- advanced settings section

### Settings

`LCFA_Settings` should continue to store existing values, but add onboarding metadata and bundle status.

## Implementation Slices

### Slice 1: Reframe the page

- move `Connect your coding agent` to the top
- add page state detection: `not_connected`, `ready`, `needs_attention`
- move low-level settings into `Advanced settings`

### Slice 2: Bundle generation

- create normalized connection bundle builder
- support bundle generation for all clients
- support local and remote variants

### Slice 3: Local install flow

- implement confirmed workspace writes for supported client artifacts
- add overwrite protection and backup option

### Slice 4: Remote bundle flow

- implement downloadable single-file or zip bundles
- add copy actions for all supported clients

### Slice 5: Verification

- persist smoke test results
- show readiness badge and recovery messaging

## Risks

### False local-path assumptions

The plugin may know the WordPress runtime path but not the user-facing workspace path. This must be handled carefully, especially in Local or containerized environments.

### Client config drift

Some coding agents may change config formats over time. The bundle generator should isolate per-client serialization from the rest of the wizard logic.

### Too much automation too early

The plugin should not attempt machine-wide installs or edits outside the project workspace in this milestone.

## Recommendation

Build the new `Connections` flow as a capability-aware wizard backed by a normalized connection bundle.

This gives the plugin:

- one onboarding UX for all clients
- safe local automation
- realistic remote support
- a much more plug-and-play first experience

without inventing fake automation the server cannot actually perform.
