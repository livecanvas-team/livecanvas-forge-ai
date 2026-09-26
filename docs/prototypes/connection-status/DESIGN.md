---
name: LiveCanvas AI Bridge connection prototype
description: Minimal prompt-first refinement of the inherited Control Room Checklist.
colors:
  bg: "#12131c"
  surface: "#1a1c29"
  raised: "#222536"
  border: "#3b3f57"
  strong-border: "#585d78"
  text: "#f6f7fd"
  muted: "#b9bdd1"
  quiet: "#a3a9c0"
  cyan: "#2cc5d8"
  green: "#43d98a"
  amber: "#f2c45b"
  red: "#ff8f97"
typography:
  headline:
    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
    fontSize: "1.25rem"
    fontWeight: 650
    lineHeight: 1.3
  title:
    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
    fontSize: "1rem"
    fontWeight: 650
    lineHeight: 1.3
  body:
    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
    fontSize: "0.8125rem"
    lineHeight: 1.5
rounded:
  control: "8px"
components:
  button-primary:
    backgroundColor: "{colors.cyan}"
    textColor: "{colors.bg}"
    rounded: "{rounded.control}"
    padding: "10px 16px"
  textarea:
    backgroundColor: "{colors.bg}"
    textColor: "{colors.text}"
    rounded: "{rounded.control}"
    padding: "16px"
---

# Design System: Connection status prototype

The local surface inherits **Control Room Checklist** from the root design. This record covers only the standalone prototype.

The user requested less text and an immediately visible startup prompt with a clipboard button. The first view therefore contains site and agent context, a named status light, the prompt and one destination sentence. Diagnostics and simulation controls use collapsed disclosures. The progress strip, duplicate headings and repeated explanations have been removed.

The 824px maximum workspace has 32px side gutters. A flat bordered container holds the flow; there are no shadows, gradients or nested cards. At 640px, context stacks and region padding becomes 20px. System type remains compact: the product title is 1.25rem (1.125rem on mobile), status is 1rem, and prompt text is .9375rem. The code-match field alone uses monospace.

**Named state rule:** pair the colored light with a short status title. Verification evidence remains available in Details & help. Color alone never communicates status.

The prompt label and cyan copy button share a row. The clipboard symbol is a geometric SVG and appears only on copy actions. The readonly textarea remains selectable and vertically resizable. Copy failure selects its complete contents and exposes a short keyboard shortcut; success says “Copied!”. Full Access permissions and code matching remain visible at approval.

The native agent selector shows the selected agent's 24px logo. Existing repository SVGs retain their proportions and colors. Claude Code and Claude Desktop share the Claude mark, distinguished by their text labels. The decorative image is hidden from assistive technology because the native select already names the agent.

Controls have 44px minimum height, an 8px radius and a visible cyan focus outline. Text selection, caret and textarea scrollbar use the existing palette. Background transitions last 180ms and respect reduced motion.

These are simulated states and sample prompts. The root design system, production plugin and real connections are unchanged. The original long-form prototype review is historical; current evidence is in the review directory's `minimal/` subfolder.
