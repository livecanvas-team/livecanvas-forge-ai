# LiveCanvas AI Bridge Design System

## Product Scene

AI Bridge is used inside WordPress by a developer or technical site owner who is moving between WordPress and a coding agent. The interface must behave like a quiet control room: it shows the current target, the next required action, and the safety state without competing for attention.

## North Star

**Control Room Checklist**

Every operational screen answers three questions in this order:

1. Which WordPress site and coding agent am I controlling?
2. What is the single next action?
3. Where are diagnostics and recovery tools if that action fails?

## Visual Language

- Use a restrained dark neutral canvas suitable for prolonged technical work.
- Cyan identifies the current selection, navigation state, and safe primary action.
- Green means verified or complete.
- Amber means attention is required before continuing.
- Pink is reserved for write access, destructive actions, and high-risk warnings.
- Use solid surfaces. Do not use decorative radial gradients, glow effects, or floating section cards.
- Use borders or compact background changes for hierarchy; avoid wide soft shadows.

## Tokens

```css
--lcfa-v2-bg: #12131c;
--lcfa-v2-surface: #1a1c29;
--lcfa-v2-surface-raised: #222536;
--lcfa-v2-border: #3b3f57;
--lcfa-v2-border-strong: #585d78;
--lcfa-v2-text: #f6f7fd;
--lcfa-v2-muted: #b9bdd1;
--lcfa-v2-quiet: #9298b1;
--lcfa-v2-cyan: #2cc5d8;
--lcfa-v2-green: #43d98a;
--lcfa-v2-amber: #f2c45b;
--lcfa-v2-pink: #ec3e8d;
--lcfa-v2-radius: 8px;
```

Spacing uses a 4px base: `4, 8, 12, 16, 24, 32, 48`.

## Typography

- Use the WordPress/system UI font stack.
- Body copy is 14-16px with a maximum line length of 70 characters.
- Page titles are 24-30px. Panel headings are 16-20px.
- Letter spacing is always `0`.
- Use sentence case. Avoid uppercase labels except compact status identifiers.

## Layout

- The page header is a compact full-width band, not a marketing hero.
- Primary navigation contains only frequent workflows.
- Setup uses no more than three visible stages.
- Connections shows one current action. Technical checks, raw config, sessions, and repair tools live in disclosures.
- Cards are used only for a current task, repeated theme item, or framed tool. Do not nest cards.
- Radius is 8px for panels and inputs. Pills are reserved for short status chips.

## Navigation

Primary:

- Connect
- Build Plan
- Theme Library

Setup appears as the first primary item only until onboarding is complete. AI Studio, Command Deck, completed setup settings, diagnostics, and repair tools are grouped under Advanced.

## Onboarding

- Auto-detect every value WordPress can know.
- Ask the user only to confirm framework, site type, coding agent, and write policy.
- Identify where every action happens: `In WordPress`, `In Codex`, or `Back in WordPress`.
- Show only the active task in full. Completed and future tasks stay compact.
- The activation milestone is a verified `get_connection_handoff` plus a passing smoke test.
- Never mark a connection ready immediately after writing config.

## Copy

- Start with an action verb: `Install MCP Adapter`, `Copy setup prompt`, `Run smoke test`.
- Replace internal terms with user outcomes in the primary flow.
- Put commands, environment variables, payloads, and raw checks under Advanced.
- Errors state what failed and the next corrective action.

## Accessibility

- Target WCAG 2.2 AA.
- Maintain 4.5:1 contrast for body and helper text.
- Never communicate status by color alone.
- All icon-only controls require accessible names and tooltips.
- Support keyboard navigation, visible focus, 44px primary targets, and reduced motion.
