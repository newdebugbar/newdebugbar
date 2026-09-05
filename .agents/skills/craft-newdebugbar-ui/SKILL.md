---
name: craft-newdebugbar-ui
description: Design, build, refactor, or review the New Debug Bar interface in this repository. Use for inspector sections, debug-bar chrome, shared Blade components, responsive behavior, visual hierarchy, and diagnostic copy.
---

# Craft New Debug Bar UI

Create calm, truthful Laravel debugging interfaces that help a developer understand the captured request and choose what to inspect next.

## Before changing the interface

Read the repository `AGENTS.md`, then inspect the current populated section in the built-in browser. Use the benchmark route in [verification](references/verification.md) when available. Inspect the retained data and its presenter before deciding what the interface can claim. When the user refers to a prior decision, check the relevant task instead of reconstructing it.

Read the references that apply:

- [Design language](references/design-language.md): hierarchy, Tailwind defaults, diagnostic meaning, and established section decisions.
- [Components](references/components.md): existing APIs, ownership, and migration rules when choosing or changing reusable markup.
- [Verification](references/verification.md): browser matrix, affected states, and focused code checks before delivery.

## Apply the shared language

Lead with identity and outcome, follow with compact useful facts, and reveal deeper evidence where it answers a real question. Remove repeated information before adding new containers or copy. Preserve unique diagnostics and their meaning.

Let each section's data determine its structure. Share typography, spacing, controls, and evidence treatments through their existing owners. A repeated visual rule belongs in a shared component; section labels, tab choices, and data decisions stay private. Use normal Tailwind scales instead of matching arbitrary mockup measurements.

Do not turn a mockup into an API contract. Document only implemented component behavior, migrate its intended consumers, and remove superseded treatments in the same change.

## Verify and report

Follow the verification reference and the repository's full interface matrix. Check every affected real consumer, then make a final visual and usefulness pass on the current implementation. Report what was observed, which checks passed, and any remaining gaps; source inspection alone does not prove rendered behavior.
