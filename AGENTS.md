# New Debug Bar

## Scope and compatibility

- Build for Laravel only. Do not add support for other PHP frameworks.
- Match the minimum PHP and Laravel versions supported by Livewire 4.
- Use `NewDebugBar` or `newdebugbar` as one word in machine-facing names. Use “New Debug Bar” in text written for people.

## Local checks before pushing

- Format changed files before verification, then check their formatting. Use Pint for PHP and Blade, and the project's Prettier tooling for JavaScript, CSS, JSON, and Markdown where applicable. Do not reformat unrelated files.
- Choose the smallest complete set of checks for the changed behavior and its consumers. Start with relevant test files or filters. Run broader affected suites for dependency, shared bootstrap, test/build configuration, or cross-cutting changes, or when a focused failure points to wider impact.
- For internal backend changes, run the relevant PHP tests. Include MCP parity tests when profile data or diagnostic contracts change. Run JavaScript or browser tests only when the change also affects their inputs or behavior; a PHP change to rendered markup, UI state, or shared response data can require them.
- For JavaScript, Blade, or CSS changes, run the relevant JavaScript, browser, and PHP rendering tests as applicable. Rebuild assets when their source changes, and perform the rendered checks required below for affected interfaces.
- Run multi-file Pest selections in parallel with `--parallel --processes=4`. A focused single-file run may stay serial when worker overhead provides no benefit. Use the native Node test runner's concurrency for JavaScript tests.
- Do not overlap separate Pest invocations in the same checkout: they share browser-server state. Parallel workers within one invocation are supported. Independent JavaScript checks may run alongside PHP checks after formatting and dependency installation finish.
- For documentation-only changes, check formatting and affected links or examples; do not run application suites. Run `composer validate --strict` when Composer files change and `composer audit` when PHP dependencies change.
- Before pushing, ensure the relevant checks passed against the final changes, commit any rebuilt assets, and run `git diff --check`. When assets were rebuilt, confirm `git diff --exit-code HEAD -- dist` passes. Rerun affected checks after further code changes. Fix relevant failures locally; do not push unverified changes and use GitHub Actions as their first check.

## Product behavior

- Make the full local debugging experience work immediately. Do not hide useful diagnostics behind opt-in flags or masked defaults only because the captured data may be sensitive.
- Add a config value only when developers have a real, repeated reason to change the behavior. Every value must have a distinct runtime effect and a clear reason for its default. Otherwise, use one fixed product behavior and remove the setting, branches, and tests.
- Use a protective default only when the normal behavior could change external state, break the host app, or create unbounded work or storage. Local diagnostic visibility by itself is not a reason to disable a feature.
- Treat the local MCP server as a main product feature. Explain that coding agents can read exact debug data instead of guessing from a web page.

## Documentation

- Keep the public README short. Explain why the package exists and how to start using it.
- Keep client-specific MCP setup in `docs/mcp.md`. Link to it from the README.
- Keep test reports, support tables, and long setup notes out of the README.

## MCP parity

- Treat the local MCP server as a full diagnostic interface, not a smaller copy of the browser inspector. Every captured, analyzed, grouped, or displayed profile field must be reachable through a bounded MCP request.
- Keep focused tools concise, and expose deeper or newly shaped evidence through the generic profile-data tool instead of dropping it from MCP.
- When a collector, analyzer, presenter, finding, or inspector changes profile data, update the MCP contract, tool guidance, and parity tests in the same change.
- Never report a profile as missing when loading or presenting it failed. Return a visible tool error so agents can distinguish expired data from broken processing.
- Preserve capture-time redaction and MCP response limits while keeping every retained profile value requestable.

## Interface priorities

- Keep the bar visually quiet until something needs attention. It should feel at home on the page while a developer works.
- Help developers answer: What happened? What is wrong? Why? Where? What should I check next?
- Show the request, errors, query count, and duration first.
- Preserve useful diagnostics, but simplify dense views through hierarchy and progressive disclosure instead of removing information.
- Keep primary views focused. Move framework internals, raw data, hashes, and supporting evidence into deeper detail views.
- Reuse established components and interaction patterns, while letting each section's data model determine its content and controls.
- Use Laravel's documented terms for Laravel concepts. Before adding or changing a framework-facing label, verify it against the official documentation for the supported Laravel versions; do not replace framework terms with invented synonyms.
- Use the shared inspector explanation component only when developers need help interpreting or acting on a table or evidence group. Its title must state the concrete question the content answers. Its description must explain only domain-specific or ambiguous information and give a conditional next check without assuming the condition is a problem. Do not explain self-evident labels or fields such as Source, or repeat the tab name or table heading.
- Use monospaced type only for actual code and numeric values in tables. Keep paths, sources, drivers, connections, table names, keys, labels, and prose in the interface typeface.
- Give stateful controls deliberate defaults. Do not make developers configure a view before it becomes useful.
- Prefer explicit labels over ambiguous symbols. Selection, loading, and content changes must not shift surrounding layout.
- Give each view one clear vertical scroll owner, and make the full parent height chain support it.
- Adapt the interaction structure to the viewport instead of squeezing desktop layouts onto smaller screens.
- A finding should explain the problem, why it matters, where it came from, and what to check next.
- Do not show multiple findings or records for the same logical cause or operation.
- Verify interface changes with realistic populated profiles across short and tall desktop sizes, mobile sizes, light and dark themes, varied content lengths, multiple records, failures, and refresh/reopen flows.

## Host-page isolation

- Treat the host page as an untrusted global namespace.
- Namespace every package-owned browser identifier: use `data-ndb-*` attributes, `ndb-*` semantic classes, `ndb:` Tailwind utilities, `--ndb-*` CSS variables, and identifiers beginning with `newdebugbar` for IDs, events, and storage keys.
- Never place generic state hooks such as `data-theme`, `data-mode`, or `data-state` on injected package elements.
- Scope authored CSS selectors beneath `#newdebugbar` or behind an `ndb`-prefixed class. Namespace package-defined global identifiers such as keyframes.
- Theme selectors must depend only on the package root's `data-ndb-theme` state. Host attributes and classes must never activate a New Debug Bar theme.
- When adding a browser identifier or global CSS rule, extend the hostile-host browser test with a realistic conflicting selector.
