# UI verification

## Start with the real product

Use the built-in browser first. If it cannot reach or control the local app reliably, use Google Chrome through Computer Use. Do not use Safari. If neither browser is available, report rendered visual verification as blocked instead of treating source inspection as proof.

For the benchmark, prove that Composer resolves this repository before treating the page as current package output. Use the populated Kyoto request when available:

`http://newdebugbar-benchmark.test/trips/kyoto-autumn`

For a shared component, inspect every affected real consumer. Use a focused rendering test or bounded fixture only when isolated behavior is otherwise difficult to prove; the populated inspector remains the visual source of truth.

## Required viewport and theme matrix

For every affected real consumer, check a tall desktop viewport, a short desktop viewport, and a 390px mobile viewport in light and dark themes. This is the repository's baseline, including shared component changes. Add an intermediate tablet width when responsive layout changes there.

Map the remaining checks to the behavior the change touches. A shared typography change calls for wrapping, density, and overflow checks across its consumers; a tab or source-disclosure change also calls for keyboard, selection, and retained-evidence checks. Do not turn this into an unrelated test expansion, or use it to omit the baseline matrix.

## Required data states

Use realistic populated profiles and cover the states touched by the change:

- No selection.
- One and multiple records.
- Long paths, keys, URLs, class names, subjects, and messages.
- Empty filtered results.
- Failure, slow, warning, and successful states when the section supports them.
- Missing optional data.
- Retained raw or code evidence, including multiline strings, meaningful whitespace, accented text, and non-Latin characters.
- Refresh, close and reopen, and profile replacement.

Do not use only a purpose-built tiny fixture when the real profile reveals height, performance, or density problems.

## Visual checks

- The requested section uses the intended full height.
- Each visible pane has one clear vertical scroll owner. In a desktop split workspace the list and detail may each scroll; avoid nested competing scroll areas within either pane.
- No page-level or workspace-level horizontal overflow appears.
- Desktop list/detail columns do not squeeze onto mobile.
- Mobile Back navigation restores the list and selection correctly.
- Headers, tabs, fields, and rows keep stable dimensions while state changes.
- Badges and compared values share stable tracks.
- Search icon size and inset match the shared field.
- Segmented controls look like one control and preserve the deliberate alignment.
- Title and description have a close but readable gap.
- Source links are obvious without ornamental icons or hover cards. Additional frames remain reachable through the intended detail or disclosure without crowding the primary evidence.
- Code is syntax highlighted; non-code uses the interface typeface.
- Semantic color is reserved for states that need attention.
- Focus rings are visible and not clipped.

Measure requested alignment in the browser. Screenshots are not enough for a one-pixel geometry claim.

## Interaction checks

- Keyboard Tab reaches every interactive control in a sensible order.
- Enter and Space activate buttons and segmented options as expected.
- Escape closes menus and transient surfaces when supported; verify focus returns to the triggering control after dismissal.
- Search, filter, selection, tabs, copy, explain, preview, and Back actions work.
- Loading, success, failure, and disabled feedback is visible without layout shift.
- Selection persists only where the product intends it to.
- Browser console has no new errors or warnings.

## Isolation and performance

- Extend hostile-host coverage with a realistic conflicting selector when adding a browser identifier or global CSS rule, as required by `AGENTS.md`.
- Keep one `#newdebugbar` root per document.
- Confirm host styles cannot activate package themes or target generic package hooks.
- Inspect DOM size for large profiles. Render only the active group, selected item, and active tab.
- Verify that opening one heavy detail does not eagerly render all retained evidence.
- When evidence moves into a disclosure or tab, confirm the retained values stay reachable. If the profile's captured, analyzed, or presented data changes, update the MCP contract, guidance, and parity coverage in the same change.

## Code checks

Run the smallest complete set that covers the change:

- Focused Pest feature or architecture tests.
- Focused browser test for rendered behavior.
- JavaScript tests when state logic changed.
- `composer lint` for PHP and Blade formatting.
- `npm run build` when Tailwind classes or JavaScript changed.
- `git diff --check` before committing.

Keep focused results separate from unrelated inherited failures.

## Final review pass

After tests pass, make one fresh pass as if reviewing someone else's work:

1. Compare desktop, mobile, light, and dark views side by side.
2. Inspect the finest gaps, divider placement, row alignment, wrapping, and control padding.
3. Read every visible label and description. Remove duplicates and obvious explanations.
4. Ask whether every visible fact helps a developer decide what happened or what to inspect.
5. Verify the current implementation, not an earlier screenshot.
