# Component system

This file documents implemented reusable components and the boundary between shared patterns and section-specific modules. Verify the current Blade API before using a prop or slot; a design proposal does not establish a supported API.

A Blade file does not become shared merely because it lives in `resources/views/components`. Reuse and a stable product-level rule make it shared.

## Ownership boundary

Use these ownership levels:

1. **Shared primitives** own one visual or interaction rule, such as a field, badge, source link, or code block.
2. **Shared inspector patterns** own recurring composition and behavior across independent sections, such as a detail header, fact grid, or list-detail workspace.
3. **Private section modules** own section-specific labels, filters, rows, tabs, data normalization, and evidence. They belong to one named product area.

A shared component may depend only on another shared component. A private module may compose shared components. One section's private module must not become another section's dependency; extract the shared visual rule instead.

A private file may still live in `resources/views/components`; its location does not make it shared. Keep its API tied to one product area until a real cross-section rule is worth extracting.

## When a component is shared

Make a component public only when at least one of these is true:

- Two independent product owners reuse the same visual or interaction rule.
- It is a foundational control or layout pattern that the product deliberately standardizes.

It must also satisfy all of these:

- Its API describes product semantics rather than one section's incidental markup.
- It does not expose section-specific state names.
- Its important behavior or markup has focused test coverage through a real consumer or a bounded fixture.
- It is the single canonical treatment for its role.

Similar section layouts do not justify a large component with many conditional props. Share stable geometry through slots and keep domain-specific content private.

Shared owners also own their default Tailwind typography, spacing, and divider treatment. Do not override those defaults in every consumer to create a new visual language. Change the owner, check its real consumers, and remove obsolete local overrides together.

## Shared primitives

| Component | Use |
| --- | --- |
| `code-block` | Syntax-highlighted code or retained code-like data. Pass the real language; never use it for an ordinary path or label. Owns the shared `ndb:p-4`, `ndb:text-sm`, `ndb:leading-6`, and `ndb:rounded-lg` code surface. Soft wrapping is on by default and preserves content; use `:wrap="false"` when keeping preformatted lines is useful. |
| `empty-state` | Calm no-results or section-empty message. Use `description` only for useful next context, `centered` for a full workspace empty state, and `success` only for a genuinely positive result. |
| `filter-tab` | One option inside `filter-tabs`. Express selection with `aria-selected` or `aria-pressed`; do not use it alone. |
| `icon` | Package-owned SVG at an explicit supported size. Prefer text when an icon would be ambiguous. |
| `icon-button` | Accessible icon-only action. Always provide an accessible name. |
| `inspector-action` | Quiet labeled action beside the evidence it affects. Its shared default supplies the treatment; there are no visual variants. |
| `inspector-operation-badge` | Neutral HTTP method, cache-operation, or Redis-command badge with shared width presets. Use `compact` for short query classifications and `wide` for longer operations. `outlined` is an available treatment, not a requirement for detail headers; Requests keeps its borderless method badge. |
| `inspector-source-link` | Underlined application-source action with no ornamental icon, padding, or hover fill. Pass `copy` when activation should copy the displayed location; keep that interaction inside the shared component. |
| `inspector-sort-heading` | Clickable heading for a meaningful sortable column in a table-like list. Pass explicit active and direction expressions, keep its fixed indicator slot, and make the parent own the sort cycle and deliberate default order. Do not pair it with a duplicate sort dropdown. |
| `search-field` | Shared labeled search input with the icon fixed on the left and balanced inset spacing. Do not add a right-icon variant. |
| `select-field` | Native select with stable field geometry. Use for one list-filter dimension rather than a segmented strip. |

## Shared inspector patterns

| Component | Use |
| --- | --- |
| `filter-tabs` | Accessible tabs or segmented-control group. Give it a concrete label and place only `filter-tab` children inside it. |
| `inspector-definition-list` | Stack `inspector-definition-row` children with one divider system. |
| `inspector-definition-row` | One label/value pair. Pass `label` for static text or the optional `term` slot for Alpine-driven headings; use a danger tone only for an actual failed or harmful state. |
| `inspector-detail-back` | Mobile drill-in Back action. Use `persistent` only when the desktop flow truly needs it. |
| `inspector-detail-empty` | Center a short selection instruction in an unselected detail pane. |
| `inspector-detail-header` | Stable selected-item identity and optional actions. Use `grid` for fixed action placement and `wrap` for long identities. Give its title slot one root element; wrap class, message, or other multi-line identity content together. |
| `inspector-detail-pane` | Detail scroll owner and query container with mobile drill-in behavior. Supply real open state, references, labels, and close behavior. Keep responsive facts tied to this pane width. |
| `inspector-detail-tabs` | Detail segmented tabs. Center by default; align left only when adjacent controls make centering misleading. |
| `inspector-disclosure` | Native details for supporting retained evidence, closed by default. Pass `label`; optional `summary` and `count` named slots replace the label or add a count. `reset-on` accepts an Alpine expression for resetting on item or profile changes. The body renders only while open and is syntax highlighted on opening. |
| `inspector-evidence` | Required `value` slot with an optional label and compact `aside` slot. Choose the actual language. Uses `code-block` and forwards its `wrap` option, which defaults to true. |
| `inspector-explanation` | Friendly help for ambiguous evidence and a conditional next check. Use `heading` and `body` slots when Alpine supplies the wording; do not explain obvious labels. |
| `inspector-fact` | One compact fact inside `inspector-facts`, with a `label` prop and a `value` or default slot. It inherits the parent's layout. |
| `inspector-facts` | Facts that respond to pane width. The default `layout="grid"` supports one, two, or four columns; `layout="inline"` makes a compact wrapping fact row. Omit its border when the parent already supplies the divider. |
| `inspector-list-controls` | Optional list summary plus search and one or two trailing filters. Use `layout="compact"` inside a narrow split-pane list so search owns the first row and two filters share the second. Use the secondary filter only when two independent filters are necessary; do not rebuild either shared grid. |
| `inspector-list-panel` | List controls, the list scroll owner, and the filtered empty state. |
| `inspector-source-fact` | Plain source label/value row. Set `code` only when the value itself is code, not merely a file location. |
| `inspector-source-panel` | Source facts followed by a collapsed supporting application stack. Uses one column by default and supports two; accepts optional `title`, an `actions` slot, and a `reset-on` expression for its disclosure. A single primary location can use `inspector-source-link`; neither the full panel nor a separate Source tab is required for every item. |
| `inspector-stack` | Visible bounded call stack when the stack itself is primary evidence. Pass retained frames, an accurate empty label, and a specific title when showing something other than the application stack. Supporting stacks belong in the source panel or a disclosure. |
| `inspector-workspace` | Shared split, focused, or stream workspace. Use `stream` for a single full-width scrollable list, `top` framing for edge-to-edge sections, and a namespaced `detailId` in focus mode. Focused details also establish a query container for shared facts. |
| `popover-surface` | Shared elevated menu surface. Use `anchored` only with Alpine Anchor and choose direction and alignment deliberately. |
| `section-heading` | Restrained title and close description. Do not repeat the tab name or explain an obvious label. |

## Compound families

Some shared files have no useful standalone state. Treat them as compound families with the parent that gives them meaning:

- `filter-tabs` owns `filter-tab`.
- `inspector-definition-list` owns `inspector-definition-row`.
- `inspector-facts` owns `inspector-fact`.

Document each family together and test the child through the parent that gives it meaning.

## Private section modules

Requests, HTTP Client, Cache, Mail, Notifications, Models, Events, Authorization, Queries, Logs, Livewire, and toolbar chrome own product-specific modules. Their complete workspaces, row renderers, data panels, state coordinators, and tab definitions are integration surfaces, not design-system components. Requests owns `request-step` for lifecycle geometry, `request-trace-icon` for its duotone stage icons, and `request-middleware` for the retained middleware list inside the shared anchored popover.

Verify those modules in realistic populated product sections. A private module may use a small fixture in a focused test, but that does not make it a shared component.

Private modules should contain only domain decisions:

- labels and filters;
- list-column tracks and row content;
- tab order and deliberate defaults;
- captured evidence and empty-state wording;
- section state and actions.

They should reuse the shared field, badge, fact, source, code, explanation, and workspace grammar rather than reproduce its markup.

## State and composition

- Stateful shared patterns receive explicit expressions, references, labels, and actions from their parent. They do not create a second root store.
- `inspector-workspace` owns split/focus/stream geometry; `inspector-list-panel` owns split-list scrolling; `inspector-detail-pane` owns detail scrolling. In stream mode, the workspace body is the only desktop scroll owner.
- `inspector-detail-tabs` supplies the shared segmented container while the private section supplies tab labels, order, availability, and active state.
- `inspector-explanation` is appropriate only when captured evidence needs interpretation or a conditional next check. Its question-title rule does not apply to ordinary headers, identity, or evidence labels.
- Code and evidence components receive retained values. They do not infer a source, result, or problem from adjacent data.

## Adding or changing a component

In one change:

1. Decide whether the work belongs to a shared component or a private section module.
2. Reuse or edit the canonical shared component when its semantics match.
3. If a new shared component is warranted, add it to this reference and focused coverage.
4. If it is an inseparable child, document and test it with its compound family.
5. Keep private modules within one owning product area.
6. Migrate every intended consumer and delete the superseded implementation in the same vertical slice. Do not leave old and new treatments in parallel.
7. Update focused behavior tests for the affected consumers.
8. Inspect every affected real consumer at desktop and mobile widths in both themes.
