# New Debug Bar design language

## Product character

New Debug Bar is a quiet diagnostic tool inside someone else's application. It should feel precise and at home on the page until something needs attention. The interface is not a dashboard of decorations. It is a work surface for understanding one request.

## Content purpose and hierarchy

Give each piece of content one clear job:

1. **Identity:** the request, operation, model, message, or other selected item. Group its primary label and supporting identity closely.
2. **Outcome and facts:** the result and the few measurements needed to judge it. Show comparable values on stable tracks and avoid repeating nearby identity.
3. **Evidence:** the retained body, values, records, changes, or source that lets the developer investigate. Let substantial content use the detail pane's width.
4. **Interpretation:** a finding or explanation only when evidence needs context or a conditional next check.

Use the order that fits the data. An HTTP response benefits from result facts before its body; a log needs its message before its context; Requests has a semantic lifecycle. Shared design language does not require identical tabs or a timeline in every section.

An identity header may include host, level, or another fact that helps identify the item. Do not force useful context into a tab solely because of its field name. Put one primary source location beside the evidence it explains and disclose supporting frames. When a stack is the primary evidence, show it directly. A dedicated Source tab is a choice for dense source evidence, not a universal requirement.

## Tailwind defaults and shared ownership

- Start with the standard Tailwind scale, using the package prefix: `ndb:gap-2`, `ndb:gap-3`, `ndb:gap-4`, and `ndb:gap-6` for spacing; `ndb:px-4` and `ndb:py-3` for common content insets; `ndb:text-sm` for body and controls, `ndb:text-xs` for secondary metadata, and `ndb:text-base` for detail headings. Adjust within the normal scale for density and responsive behavior.
- Use one-pixel dividers, neutral surfaces, and the existing shared corner and control treatments. Avoid adding a box around every evidence group.
- Keep code in a full-width evidence block when it is the main content. Use label/value rows for short comparable facts, not as a narrow column around a large payload.
- Change shared typography, padding, dividers, tabs, and controls in their owning components. Remove conflicting consumer overrides during the migration instead of adding a second treatment.
- Keep section-specific layout and content choices private. Extract a stable rule when independent sections actually share it; do not add props for imagined future uses.
- Treat mockups as hierarchy and interaction guidance. Use arbitrary values only for a concrete constraint the normal scale cannot express, such as a measured column track or anchored geometry.

## Truth before presentation

- Show only facts present in the retained profile.
- Distinguish “not captured” from “empty,” “failed,” and “not applicable.”
- Do not infer a database write, model hydration source, notification delivery, HTTP cause, or application source from adjacent data.
- Keep evidence bounded. Use retained application frames, payloads, or groups, and disclose only the depth needed for the current inspection.
- A short preview must lead to the full retained value. Keep capture-limit notices distinct from collapsed evidence: changing the view cannot recover data omitted from the profile.
- Do not show multiple findings or rows for the same logical cause or operation.

## What to remove

Delete UI when it is any of the following:

- A badge, count, label, or metadata row already visible nearby.
- A heading that merely repeats a tab or table title.
- A prose explanation of an obvious field such as Source.
- Framework internals from the primary view when they do not help the developer choose a next step.
- Raw JSON that duplicates a clearer structured view.
- An ornamental icon, container, or hover background that competes with a simple clickable label.
- A button for an action the whole row or card can safely perform.

Do not delete unique evidence merely to make a view sparse. Move it to an appropriate disclosure, detail tab, or evidence block, and preserve bounded MCP access to retained profile values.

## Layout grammar

### Multiple items

Use an edge-to-edge list/detail workspace. The list owns its controls and list scrolling. The detail pane owns detail scrolling. On mobile, show either the list or the selected detail, never a squeezed desktop split.

### One item

Use a full-width focused reading view. Do not keep an empty list column for structural consistency.

### Empty selection

Do not select the first item merely to fill space. When selection is not a deliberate default, center a short instruction on both axes in the detail pane.

### Height and scrolling

- Make the complete parent height chain support the intended section height.
- Give each view one clear vertical scroll owner.
- Avoid nested full-height scroll regions.
- Verify that headers and controls stay reachable at short desktop heights.
- For a long ordered stream, retain bounded server pages and load the next page automatically when its scroll owner nears the end. Keep loading, failure, retry, and completion feedback in the flow; do not make developers press a repeated “Load more” action.

### Alignment

- Keep list rows on stable tracks across different content lengths.
- Vertically center badges, outcomes, and numbers against the primary label.
- Use tabular numbers for values developers compare.
- Measure important column and control alignment in the browser; target a difference of no more than one pixel.
- Avoid gaps and borders that create unused gutters. Edge-to-edge workspaces use a top divider when a full card frame adds no value.

## Typography

- Use the interface typeface for paths, source locations, drivers, connections, table names, keys, labels, and prose.
- Use monospaced type only for actual code and numeric values inside tables.
- A class or callable such as `App\Models\User->notify` is code; a file location such as `app/Actions/Trips/RefreshTripWorkspace.php:150` is a source label.
- Syntax-highlight every real code snippet, including SQL, PHP callables, JSON, headers, and retained code-like payloads.
- Keep body and control text comfortably readable. Avoid 10px text for meaningful content.
- Use a restrained heading and keep supporting identity close to it. Titles identify their content; the question-title treatment below applies specifically to `inspector-explanation`.
- Do not use bullet-dot characters as inline separators.

## Color and emphasis

- Neutral is the default.
- Use red, amber, or another semantic color only when the state needs attention.
- Do not use blue monospaced text as a generic source treatment.
- Make links obvious with a simple underline when a filled hover treatment would add noise.
- Preserve hidden accessible meaning when a visible label is redundant.
- Use one prominent selected state. Avoid stacking a selected row, badge, card tint, and callout for the same fact.

## Controls

- Put the search field first, with its search icon on the left and balanced inset spacing.
- Put a compact filter dropdown on the right of search when the list needs one dimension of filtering.
- Keep filtering in dropdowns. In a table-like list, put useful sorting on the comparable column heading instead of adding a separate sort dropdown.
- Use the shared sortable heading, start with the most useful direction, then toggle the other direction and return to the section's deliberate default order. Keep a fixed indicator slot and change the active heading color so neither the label nor its column moves when the indicator appears or disappears.
- Do not add sorting to a chronology or semantic sequence whose order already carries meaning.
- Use a segmented control for a small, mutually exclusive set of detail views.
- Center detail tabs in their container. Keep them left-aligned only when another control in the same row makes centered placement misleading, as in Mail.
- Prefer explicit labels and stable widths. At a 390px viewport, an icon-only tab is acceptable only when the full set cannot fit, every icon has an accurate accessible label, and the meaning is familiar in context.
- “All” is often useful, but it is not a universal default. Choose the state that suppresses noise and answers the common question for that section.
- Keep active non-favorite sections in alphabetical navigation order, including Requests. A section's default-open behavior must not change its navigation position.

## Headers and facts

- A selected-item header groups identity and useful identifying context. Keep supporting diagnostic facts in one compact group near the evidence they describe.
- Give the detail header title slot one structural root. Wrap a multi-line identity so the shared header grid does not lay its lines out as separate columns or implicit rows.
- Pair operation or HTTP method badges with the key or URL on the same line.
- Use the shared operation badge in both list and detail headers. Preserve Requests' deliberate borderless method treatment.
- Keep fact groups compact, aligned, and free of duplicate header content.
- Use a table when several records share the same fields. Put totals in a clearly labeled `Count` row or column when they help comparison.

## Explanations

Use `inspector-explanation` only when developers need help interpreting or acting on a table or evidence group.

Its title must state the concrete question answered. Its description should:

- Explain only ambiguous or domain-specific meaning.
- Tell the developer what condition deserves attention.
- Offer the next check only under that condition.
- Stay neutral when the condition may be expected.

Do not explain Source, repeat a heading, or narrate ordinary table columns. A new developer deserves useful context, not more text.

## Actions

- Put copy, preview, open, or explain actions beside the exact evidence they affect.
- Make a whole Mail list item clickable instead of adding a redundant “View email” button.
- Provide visible success or failure feedback after clipboard and async actions.
- Remove actions that do not change what the developer can learn or do.

## Section-specific defaults

- Inspector: do not expose a top-level Overview section unless user demand proves it useful. Open Requests by default, while retaining captured overview/runtime evidence for summaries, MCP, and other non-UI consumers. This does not affect section-specific detail tabs named Overview.
- Requests: preserve the Received, Matched, and Responded lifecycle trace and collapsed Request details disclosure. Use quiet duotone icons; align each node, heading, and first evidence line on desktop, and stack the evidence below its heading on smaller screens. Keep the method badge borderless, omit a zero-byte request size, and show the response content type, size, and total request duration under Responded. Keep query counts and Timeline navigation in the existing toolbar and sidebar. It is an intentional exception to the shared inspector-workspace presentation; keep the global section shell, height chain, and host isolation around it without replacing its internal flow with tabs or list/detail controls.
- HTTP Client: list filter uses a dropdown and the detail opens on Response. Group the method and URL as identity, with host as supporting context when useful, then show response outcome and useful response facts before the body. Keep Request evidence available. Place the primary application source with the evidence it explains and disclose deeper retained source only as needed; do not require a separate Source tab.
- Cache: list operation filter uses a dropdown; operation badge and key share one header line. Keep useful operation facts, a full-width captured value, and a compact source with supporting frames in one reading view.
- Redis: keep command facts and key evidence in one selected-command reading view. Put the shared operation badge and primary key on one compact header line. Do not split sparse Overview and Keys content into tabs; place the copy action beside the keys and explain protected identifiers only when they are shown.
- Logs: use the shared list/detail workspace. Make the compact log row the selection control and keep context, timing, related exceptions, and source in the persistent detail pane instead of an anchored popover.
- Mail: the entire list item opens the message; detail tabs may remain left-aligned when sharing a control row. Do not capture or expose raw message headers without a concrete debugging use that is not already covered by the structured message facts or downloadable EML.
- Notifications: show actual channel outcomes; do not add a redundant “needs attention” badge to the detail panel.
- Models: no default selection; Records is the default selected-model tab; model list keeps a table header and a search field. Keep records, writes, and their source evidence distinct, without requiring a separate tab for each. Show only captured changes; do not invent original values, full-record snapshots, or a causal link to nearby queries. Do not duplicate table data in the header.
- Queries: show the runnable SQL with retained binding values already inserted. Do not split a query and its bindings into separate tabs; explain only when capture limits leave placeholders unresolved. Keep Source in Overview beneath the full query and omit it when no application source was retained. Opening EXPLAIN runs it automatically and reuses the first result or failure without adding a retry action. Keep the list filter dropdown, show the database driver as unlabeled secondary text above the runtime, and sort runtime through the shared Time heading rather than a sort dropdown. Format durations adaptively as seconds, milliseconds, or microseconds so sub-millisecond work never reads as `0.x ms`. Use an amber row tint for repeated queries and a red row tint for slow queries, with slow taking priority; keep those states out of secondary badges.
- Queries EXPLAIN failures: use no more than two short sentences: the safe cause, then the next step. If no safe cause is available, show only the next safe check. Never expose raw exception text, SQL bindings, database paths, or connection details.
- Authorization: use Laravel's User, Ability, Arguments, Gate callback, Policy method, and Authorization response terms. Keep one merged decision-and-source flow unless distinct evidence becomes dense enough to justify tabs. Ability labels such as `view` and `update` use the interface typeface; actual policy classes and callables use code type.
- Exceptions: treat the exception class and message as one grouped identity. Keep the source action separate so the header has one clear reading axis.
- Validation: keep failures in a full-width grouped view rather than drilling into one field at a time. On desktop use Field, Message, and Failed rules columns, giving Message the widest track; preserve that reading order when rows stack on mobile.
- Views: use one reading view for the selected render, with compact render facts, template source, composer evidence, and lazily loaded passed data. Omit composer evidence when none was captured. Keep render selection in the header when a group has multiple renders; do not add tabs around one continuous inspection.
- Livewire: Activity is chronological. Preserve its connected timeline spine and status dots inside the shared list/detail workspace so interactions read as a sequence instead of unrelated rows.
- Events: an Application default can be better than All when framework events dominate.

### Notification outcome language

- `partial` means that some captured channel attempts failed while others did not. State the concrete split, such as “1 of 2 channels failed,” instead of the vague “Needs attention” in detail evidence.
- A `sent` channel result means the application handed the notification to that channel successfully. It does not prove that a person ultimately received, opened, or acted on it.
- Keep the overall list status short. Put channel-specific outcomes and failure messages in Delivery.
- Successful channel rows stay neutral. Emphasize only the failed outcome and its captured failure evidence.

## Performance

- Do not render every retained payload, stack, editor, or detail panel on initial load.
- Render the active group, selected item, and active tab.
- Treat large DOM output as a product defect even when it eventually renders.

## Host isolation

- Use one `#newdebugbar` root per document.
- Prefix semantic classes with `ndb-` and Tailwind utilities with `ndb:`.
- Use `data-ndb-*` for state and behavior hooks.
- Use `newdebugbar*` for IDs, events, and storage keys.
- Scope authored CSS beneath `#newdebugbar` or an `ndb-` class.
- Drive themes only from the package root's `data-ndb-theme`.
