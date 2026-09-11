---
name: use-newdebugbar
description: Install, set up, and use New Debug Bar in a Laravel app. Use when Codex needs to add the package, connect its local MCP server, inspect a browser request or saved profile, compare request performance, investigate findings or queries, or explain what happened, what is wrong, why, where, and what to inspect next.
---

# Use New Debug Bar

Use the package's local MCP tools directly to read exact, saved Laravel request data. Do not start another Codex process or agent just to call the MCP server. Keep tool responses small, keep the answer short, and lead with the useful result.

## Set up the package

1. Confirm the current folder is a Laravel app by finding `artisan` and `composer.json`.
2. Run `composer show newdebugbar/newdebugbar` before changing anything.
3. If the package is missing, explain that it is a development-only dependency and ask before running `composer require --dev newdebugbar/newdebugbar`.
4. Do not publish the config file by default. The package works without it. Run `php artisan vendor:publish --tag=newdebugbar-config` only when the user asks to change a setting.
5. Confirm the app uses the `local` environment and New Debug Bar is enabled.
6. Start a fresh Codex task after installing the plugin, or when the MCP server tried to start before the package was installed. A task's tool list does not refresh after plugin installation.
7. Retry the first MCP call once if it fails while the local server starts. If it fails again, confirm `artisan`, the package, the environment, and the enabled setting before restarting the task.
8. When the user asks to avoid a local package, check `composer.json` and `composer.lock` for a `path` repository and confirm the package under `vendor` is not a symlink. A GitHub ZIP URL is normal for a package installed through Packagist.

## Inspect a request

1. Make the request in the browser when the user wants live proof.
2. Use the `X-NewDebugBar-Profile` response header when it is available.
3. Otherwise, list recent profiles and match the method, path, status, request kind, and time. Do not trust the newest item when background requests may have run.
4. Set small limits instead of accepting maximums. Start with 10 profile summaries, 10 findings, and 5 items from an inspector or query search. Increase a limit or continue from a cursor only when the answer needs more evidence.
5. Read findings first, then use `get-debug-profile-inspector` with the `inspector` parameter for the smallest useful inspector. Inspect query details only when the request points to a database problem. Prefer `slow` or `repeated`, sort by duration, and return 5 items. Do not request every query by default.
6. When a focused response omits needed detail, use `get-debug-profile-data` with the same exact profile ID. Start at `/inspectors`, use a small limit, and follow the returned JSON Pointer paths until the needed object, list, or exact value is reached. Continue from the returned cursor for later items or string chunks.
7. For Models, follow `/inspectors/models/payload/model_groups`. It exposes complete folded write operations, record identifiers, capture-redacted changed attributes, source and compiled-Blade evidence, timings, exact-source query correlation, and state-specific guidance. The focused Models response intentionally stays small.
8. Inspect no more than three profiles deeply unless the user asks for a broader review.
9. Separate confirmed facts from guesses.

## Compare page performance

1. Use the same browser, account, and session for every page.
2. Load each page twice and record the exact profile, path, request kind, status, and duration. Rank the second load so startup work does not decide the result.
3. Do not cap the number of pages. When the review is larger than the profile retention limit, work in retention-safe batches. Save each page's second-load summary, inspect the useful profiles before they can be removed, then continue with the next batch.
4. Compare all saved profile summaries first. Read findings and small inspectors only for the slowest or most useful second-load profiles.
5. Treat local debug timings as relative evidence. Collector work affects the total, so confirm an important performance claim without the profiler before calling it an application benchmark.

## Interpret the evidence

- Treat a finding as a lead, not a verdict. Repeated chunk or pagination queries can look like an N+1 problem.
- Read pagination and truncation fields. A collector limit does not mean application data is missing.
- Compare query time with total request time. If queries are a small share, inspect the timeline, models, events, and views before blaming the database.
- Check the request outcome before calling a denied permission a failure. A denied check on a successful `200` page may only hide an optional control.

## Explain the result

Answer these questions in order:

1. What happened?
2. What is wrong?
3. Why did it happen?
4. Where should the developer look?
5. What should they inspect or try next?

Prefer a useful summary over a dump of raw profile data. Mention the exact profile, request, and status so the developer knows which request the answer describes. The MCP tools only read saved profiles and preserve capture-time redaction; do not claim they changed the app.
