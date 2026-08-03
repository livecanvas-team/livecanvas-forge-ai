# From a Reference URL to an Editable LiveCanvas Website

LiveCanvas AI Bridge connects a coding agent to a real WordPress and LiveCanvas installation. The agent can inspect the site, propose changes, preview them, apply approved operations, and return audit IDs that can be used for rollback.

This tutorial documents a real beta workflow: starting from a reference URL and producing an original, editable one-page website with Codex, Picowind, WindPress, Tailwind CSS 4, DaisyUI, and LiveCanvas.

> **Beta status:** LiveCanvas AI Bridge 0.2 is suitable for staging tests and supervised development workflows. It is not yet production guaranteed. Use a staging site, verify the Site ID before every write session, review previews, and keep rollback available.

## What AI Bridge Does

AI Bridge is not a page generator running inside a chat box. It is the execution layer between a coding agent and WordPress.

```text
Reference URL + creative brief
              |
              v
Coding agent analysis and generation
              |
              v
LiveCanvas AI Bridge read / preview / apply tools
              |
              v
WordPress + LiveCanvas + Picowind/WindPress
```

The coding agent handles reasoning, visual analysis, copy direction, and code generation. AI Bridge provides site identity, LiveCanvas context, structured writes, framework-aware validation, build operations, audit records, and rollback.

The public plugin does not crawl or clone arbitrary websites by itself. The source URL is analyzed by the coding agent's browser or visual tools. AI Bridge receives the original implementation that should be created on the connected WordPress site.

## The Tested Example

For the beta test, a hospitality website was used as a structural and visual reference. The final site did not reuse its brand, copy, or media.

The agent created **Casa Luminara**, a fictional private retreat above Lake Orta, with:

- an original design system;
- five original visual assets;
- a separate LiveCanvas header and footer;
- an eight-section one-page homepage;
- Tailwind CSS 4 and DaisyUI components;
- a compiled WindPress cache;
- responsive desktop and mobile behavior.

The result remained editable in LiveCanvas and did not require placing the header or footer inside the homepage content.

## Before You Start

You need:

- WordPress 6.8 or 7.0 on a staging site;
- an active and licensed LiveCanvas installation;
- LiveCanvas AI Bridge installed and activated;
- one coding-agent project dedicated to this WordPress site;
- Picowind and WindPress for the Tailwind CSS and DaisyUI workflow;
- Node.js for secure pairing fallback, local builds, and visual checks.

Keep one coding-agent project connected to one WordPress site. Never continue when the URL or Site ID returned by the handoff differs from the intended target.

## 1. Connect the Coding Agent

Open **LiveCanvas > AI Bridge > Connections** and generate the setup instructions for Codex. Add the project-scoped configuration, restart Codex, and begin with a read-only handoff.

Use this prompt:

```text
Call get_connection_handoff with {"limit":5}.
Make no changes.
Report the WordPress URL, Site ID fingerprint, framework, granted scopes,
write exposure, and the safest preview-first workflow.
```

On local or private sites, approve the matching secure pairing code in WordPress. Continue only when the handoff identifies the correct site.

## 2. Audit the Empty Site

Before generating anything, ask the agent to inspect the real stack.

```text
Inspect this WordPress site without changing it.
Read the snapshot, theme context, LiveCanvas inventory, ability diagnostics,
recent runs, and build-runtime status.
Confirm whether Picowind, WindPress, Tailwind CSS 4, and DaisyUI are ready.
List blockers before proposing any write.
```

This prevents the agent from generating Bootstrap markup on a Picowind site, assuming that WindPress is ready when it is not, or writing to the wrong LiveCanvas target.

## 3. Analyze the Reference URL

The reference should provide composition, hierarchy, rhythm, and interaction ideas. It should not be treated as a source of reusable brand assets or copyrighted copy.

```text
Analyze https://example.com as a visual and structural reference only.
Do not copy its brand name, copy, logos, photographs, or proprietary assets.

Extract:
- page hierarchy and section order;
- spacing and layout rhythm;
- typography roles;
- color relationships;
- recurring components and interactions;
- desktop-to-mobile behavior.

Then propose an original company, original copy direction, original media brief,
and a one-page information architecture for the connected WordPress site.
Do not write anything yet.
```

Review this proposal before asking for implementation. This is the point where the user can change the business, tone, audience, or page structure without creating cleanup work in WordPress.

## 4. Create the Design System

Ask for stack-native tokens instead of isolated inline styles.

```text
Create an original Picowind design system for the approved concept.
Use Tailwind CSS 4 and DaisyUI conventions.

Define semantic colors, typography roles, spacing rhythm, container widths,
button styles, card treatment, border radius, shadows, and responsive rules.
Run preview-design-system first and explain the proposed tokens.
Apply the design system only after the preview passes validation.
```

For Picostrap projects, the same workflow should target Bootstrap Customizer and Sass variables, followed by a previewed Sass compile. Do not mix the Picostrap and Picowind build paths.

## 5. Prepare Original Media

Image generation is supplied by the coding agent or a configured image provider, not by WordPress itself.

```text
Create a media plan for the approved page structure.
For every asset, define its purpose, aspect ratio, visual direction, alt text,
and the section that will use it.

Generate or select only original and licensed assets.
Upload them through the AI Bridge media tools and return a media manifest
with attachment IDs, final URLs, dimensions, and alt text.
Do not place images into the page until the manifest is complete.
```

This creates a stable asset map before the page HTML is generated and avoids temporary external image URLs inside LiveCanvas content.

## 6. Build the Global Header and Footer

Header and footer are LiveCanvas partials, not sections embedded in the homepage.

```text
Create a responsive LiveCanvas header and footer for the approved concept.
Use Picowind, Tailwind CSS 4, and DaisyUI-compatible markup.

Keep desktop and mobile navigation accessible.
Run preview-global-shell first.
After validation, apply the header and footer as separate LiveCanvas partials.
Return the target IDs and audit ID.
```

AI Bridge enables the matching LiveCanvas global-shell settings and normalizes redundant outer header or footer landmarks before storing the partial content.

## 7. Generate the Homepage

Generate the complete page as editable LiveCanvas HTML while keeping the approved structure and media manifest.

```text
Create the approved one-page homepage as editable LiveCanvas content.
Use the applied design system and uploaded media manifest.

Requirements:
- eight clearly separated sections;
- semantic and accessible HTML;
- responsive Tailwind CSS 4 utilities and DaisyUI components;
- no inline global header or footer;
- no copied source-site text or assets;
- page-specific CSS or JavaScript only when utilities are insufficient;
- SEO title, description, and noindex for this staging test.

Run preview-page-upsert first.
After the preview passes, create a draft, return the audit ID, and wait for review.
```

After review, publish the page and set it as the static WordPress homepage. Publishing should remain an explicit user decision.

## 8. Compile and Verify the Result

The HTML is not considered complete until the Tailwind classes are present in the compiled WindPress cache.

```text
Build the Picowind/WindPress CSS for the current homepage and global partials.
Verify Tailwind CSS 4 and DaisyUI processing, store the compiled cache,
flush AI Bridge and WordPress caches, and report the cache checksum.

Then run visual checks at 1440 x 1000 and 390 x 844.
Check horizontal overflow, broken images, header/footer count, navigation state,
text clipping, section spacing, and browser errors.
Do not mark the task complete while a blocking visual issue remains.
```

The Casa Luminara test produced a compiled cache from 3,421 candidates. Desktop and mobile checks reported no horizontal overflow, one global header, one global footer, working mobile navigation, and valid lazy-loaded images.

## 9. Refine with Targeted Patches

Do not rewrite an entire page to change one phrase, link, class, or attribute.

```text
Change only the exact target described below.
Use content-patch-preview first and require one unique match.
Show the diff before applying it.
After approval, use content-patch-apply and return the audit ID.
Do not rewrite the complete page.
```

If the result is not correct, restore it with the returned audit ID and verify the original content hash.

## Other Practical Beta Use Cases

### Design system only

Audit an existing site, derive coherent framework-native tokens from a logo or brand brief, preview the changes, compile them, and apply them without rebuilding the content.

### Header and footer redesign

Read the existing partials, propose a responsive navigation system, preview the global shell, and update only the selected header or footer variant.

### New landing pages

Create a draft LiveCanvas page from a campaign brief while preserving the current theme, design system, global partials, and media library.

### Safe maintenance and repair

Inspect page HTML, apply a unique targeted patch, review the diff, flush caches, run a visual check, and keep an exact rollback record.

### Dynamic templates and starter themes

Create LiveCanvas templates for posts or custom post types, or install a validated Picowind/Picostrap package from the Theme Library with deterministic starter data and import rollback.

## What the Beta Does Not Promise

- It is not a one-click pixel-perfect cloning service.
- It does not grant unsupervised production access to an agent.
- It cannot bypass hosting filesystem restrictions or missing build runtimes.
- Its richer WooCommerce, ACF, multilingual, and SEO combinations still require broader qualification.
- Visual analysis and image generation depend on the connected coding agent and its available tools or providers.

## Recommended Public Beta Positioning

Use this claim:

> LiveCanvas AI Bridge gives coding agents a structured way to inspect, build, preview, and safely update real LiveCanvas websites.

Avoid this claim:

> Clone any website perfectly with one prompt.

The strongest current product story is control. The user keeps LiveCanvas editability, framework-native output, explicit write scopes, site identity, previews, audit IDs, and rollback while the coding agent handles the larger implementation workflow.

## Visual Assets for the Public Article

The final launch article should include:

1. A hero composition showing the coding-agent prompt flowing into an editable LiveCanvas result.
2. A compact diagram of the agent, AI Bridge, WordPress, LiveCanvas, and Picowind/WindPress workflow.
3. A connection screenshot highlighting the verified URL, Site ID, scopes, and preview-first state.
4. A design-system board with color, type, buttons, cards, spacing, and responsive examples.
5. Desktop and mobile views of the completed beta case study, followed by one diff-and-rollback example.

The visual direction can follow LiveCanvas branding while giving AI Bridge its own recognizable system: dark technical surfaces, cyan structural signals, LiveCanvas pink for actions, clear code details, and real website output as the primary visual evidence.

## Public Beta Release Gate

Before publishing this tutorial as a launch article:

1. Package the staging fixes into a new beta release.
2. Repeat the workflow from a clean installation using the released ZIP and pinned MCP package.
3. Capture the final connection, design-system, desktop, mobile, diff, and rollback visuals.
4. Publish the known limitations and staging-first safety guidance beside the download.
5. Invite a limited group of developers to test Codex first, while labeling the remaining coding-agent clients as broader beta coverage.
