# LiveCanvas Forge AI Connections Linear Wizard Design

Date: 2026-04-13
Status: Proposed
Scope: `livecanvas-forge-ai` plugin only

## Goal

Reshape the `Connections` tab into a true linear wizard.

The user should never have to scan multiple equal-weight actions and guess which one matters. At any moment the page should answer one question clearly:

- what do I need to do now?

The outcome of the redesign is:

1. one active step at a time
2. one primary CTA per step
3. explicit alerts that explain the current action, why it matters, and what happens next
4. technical details moved into contextual or secondary UI instead of dominating the page

## Product Context

The `Connections` tab is the first real product surface for connecting a coding agent to WordPress and LiveCanvas.

If the user cannot understand how to connect `OpenCode`, `Codex`, `Cursor`, or `Claude Code`, the rest of the plugin becomes irrelevant. The runtime can be technically correct and still feel broken if onboarding is ambiguous.

This redesign is specifically for users who are not already familiar with MCP, transport settings, runtime filesystem differences, or the difference between:

- `agent -> plugin`
- `plugin -> remote WordPress companion`

## Problem

The current wizard still behaves too much like a technical control panel:

- the numbered steps are static labels, not real stateful steps
- multiple actions compete visually at the same time
- the bundle details appear before the user is ready for them
- the primary next action is not obvious
- local runtime limitations such as browser-inaccessible host paths are explained, but still too late in the flow

The result is cognitive overload. The user can see the pieces, but not the path.

## Non-Goals

- redesign the full Forge AI admin
- replace the current bundle builder or MCP transport model
- implement a modal or multi-page onboarding flow
- remove advanced settings for expert users
- solve remote client installation beyond bundle generation and copy/download flows

## Design Principles

### One decision at a time

Only the current step should ask for input. Everything else should either be complete or visibly locked.

### One primary action at a time

Each step exposes a single recommended CTA. Secondary actions must be visually subordinate.

### Explain now, not after failure

The wizard should use alerts before the user acts, not only after something breaks.

### State should be visible

Step cards must clearly distinguish:

- `Done`
- `Active`
- `Locked`

The current step must be visually illuminated and include a short action label that tells the user what to do.

### Technical detail is contextual

Commands, env vars, bundle files, and smoke test details are useful, but they should be shown when the user reaches the relevant step or in a secondary summary panel.

## Interaction Model

The `Connections` tab should render in this order:

1. `Connect your coding agent` hero
2. `What to do now` alert
3. linear stepper
4. active step card
5. compact technical summary
6. remote companion section
7. advanced settings

The page should no longer behave like a dashboard where the user can try any action in any order.

## Step Model

The wizard owns an explicit `current_step` state.

Allowed values:

- `choose_client`
- `choose_mode`
- `confirm_details`
- `generate_bundle`
- `smoke_test`
- `ready`

The current step is derived from saved onboarding state plus verification state. It should not depend on whether all page sections happen to be rendered at once.

### Step 1: Choose your coding agent

Prompt:

- `Which coding agent are you connecting?`

Inputs:

- `Codex`
- `OpenCode`
- `Cursor`
- `Claude Code`
- `Generic MCP client`

Primary CTA:

- `Continue`

Completion:

- save `preferred_client`
- advance to `choose_mode`

### Step 2: Choose where the agent will connect

Prompt:

- `Is this local or remote?`

Inputs:

- `This local site`
- `Remote site`

Primary CTA:

- `Continue`

Completion:

- save `connection_mode`
- advance to `confirm_details`

### Step 3: Confirm connection details

Prompt:

- `Are these connection details correct?`

Display:

- `REST base`
- `MCP token`
- `workspace_root` when mode is `local`
- a one-line explanation of what the client will use

Primary CTA:

- `Confirm details`

Alert rules:

- local: explain that `workspace_root` must be the real host path on the user machine
- remote: explain that the bundle will be downloaded and not written by the remote server

Completion:

- persist the current connection inputs
- generate the normalized bundle payload
- advance to `generate_bundle`

### Step 4: Generate or install the client bundle

Prompt:

- `How do you want to continue?`

Primary CTA depends on context:

- local + browser runtime can write to workspace: `Write config in workspace`
- local + browser runtime cannot write to workspace: `Download client bundle`
- remote: `Download client bundle`

Secondary actions:

- `Copy command`
- `Download bundle` when it is not primary

Alert rules:

- local writable: explain that Forge AI will write only the client artifact in the current workspace
- local non-writable: explain that the browser runtime cannot write to the host workspace and the next step must happen in the coding agent
- remote: explain that the server can only produce the bundle, not write to the user machine

Required next-action copy examples:

- `Open this project in OpenCode now`
- `Start the MCP bridge once`
- `Come back here and run the smoke test`

Completion:

- mark bundle generation/install as completed
- advance to `smoke_test`

### Step 5: Run the smoke test

Prompt:

- `Ready to verify the connection?`

Primary CTA:

- `Run smoke test`

Result:

- success -> state becomes `ready`
- failure -> state becomes `needs_attention` and the wizard reopens this step with a blocking alert

Stored outputs:

- client
- mode
- verification status
- verification timestamp
- last error summary when relevant

## Stepper UI

The stepper remains visible across the flow, but only one step is active.

Each step card shows:

- number
- short title
- short helper label
- state badge

Example helper labels:

- `Pick the client`
- `Choose local or remote`
- `Confirm the details`
- `Generate the config`
- `Verify the connection`

Visual states:

- `Active`: illuminated card, stronger border, visible helper text, accent glow
- `Done`: muted success state with check indicator
- `Locked`: reduced opacity and no interactive affordance

The active step should be easy to identify without reading the full page.

## Alert Model

Each active step must show one dominant alert block.

Alert types:

- `Now`: what the user must do in this step
- `Why`: why this step exists or why a constraint applies
- `Next`: what happens after the primary CTA
- `Blocked`: what must be fixed before continuing

The wizard may show one combined multi-line alert block as long as the content still covers `Now`, `Why`, and `Next`.

Example for local OpenCode when browser writes are unavailable:

- `Now: Download the OpenCode bundle`
- `Why: This browser runtime cannot write into your Mac workspace directly`
- `Next: Open the project in OpenCode, let the MCP bridge start once, then return here for the smoke test`

## Technical Summary

Technical details should move below the active step card and use lower visual priority.

The summary should stay compact until the user reaches `generate_bundle`.

Content:

- generated file paths
- server command
- environment variables
- smoke test command

Behavior:

- collapsed or partially hidden before step 4
- fully expanded during and after step 4

## Ready State

When the smoke test passes, the wizard becomes a compact status card.

It should show:

- `Ready`
- selected client
- local or remote mode
- last verified time
- actions: `Reconfigure`, `Regenerate bundle`, `Run checks`

If the connection later breaks, the page reopens the wizard at the failing step instead of resetting the whole flow.

## Error Handling

The wizard should convert technical conditions into action-oriented language.

Examples:

- not `Workspace unavailable`
- but `This browser runtime cannot write to the selected workspace. Download the bundle and complete the next step in your coding agent.`

- not `Smoke test failed`
- but `Forge AI could not verify the connection. Check the MCP token and restart the client bridge, then run the smoke test again.`

## Testing Strategy

Add or update tests for:

- current step derivation
- active vs done vs locked step rendering
- primary CTA selection by mode and workspace write capability
- alert copy for local writable, local non-writable, and remote flows
- bundle details hidden before step 4 and shown during step 4
- ready state rendering after successful verification

Manual verification should cover:

1. local OpenCode flow with browser write unavailable
2. local writable workspace flow
3. remote flow
4. smoke test failure reopening the correct step

## Recommended Implementation Order

1. introduce `current_step` derivation and render helpers
2. convert the existing step list into a stateful stepper with badges and helper labels
3. replace the multi-action wizard body with a single active step card
4. move bundle details into a compact technical summary section
5. update ready-state card to match the same mental model
6. refine copy and styling for alert prominence and CTA hierarchy
