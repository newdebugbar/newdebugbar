# Set up the MCP server

New Debug Bar gives coding agents clear data from saved request profiles. It uses a local [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) server.

Your coding tool starts the server when it needs it. Do not run `mcp:start` in a separate terminal.

## Before you start

- Install New Debug Bar in your Laravel app.
- Make sure the app uses the `local` environment.
- For manual setup, find the full path to the app's `artisan` file.

The examples below use `/absolute/path/to/your-app/artisan`. Replace it with your real path.

## Codex plugin

The optional Codex plugin is the easiest setup. It adds a New Debug Bar skill and starts the MCP server from the Laravel app you have open.

Add this repository as a plugin source, then install the plugin:

```bash
codex plugin marketplace add newdebugbar/newdebugbar
codex plugin add newdebugbar@newdebugbar
```

Open the Laravel app's root folder in Codex and start a new task. You do not need to publish the New Debug Bar config file.

The plugin is optional. Remove it at any time with:

```bash
codex plugin remove newdebugbar@newdebugbar
```

## Manual Codex setup

Run this command once:

```bash
codex mcp add my-app-debug-bar -- php /absolute/path/to/your-app/artisan mcp:start newdebugbar
```

Use a name that tells you which app it belongs to. Check the setup with:

```bash
codex mcp list
```

## Claude Code

Run this command from your project:

```bash
claude mcp add --scope local newdebugbar -- php /absolute/path/to/your-app/artisan mcp:start newdebugbar
```

Check the setup with:

```bash
claude mcp list
```

## Cursor

Create `.cursor/mcp.json` in your project:

```json
{
  "mcpServers": {
    "newdebugbar": {
      "command": "php",
      "args": [
        "/absolute/path/to/your-app/artisan",
        "mcp:start",
        "newdebugbar"
      ]
    }
  }
}
```

## VS Code

Create `.vscode/mcp.json` in your project:

```json
{
  "servers": {
    "newdebugbar": {
      "type": "stdio",
      "command": "php",
      "args": [
        "/absolute/path/to/your-app/artisan",
        "mcp:start",
        "newdebugbar"
      ]
    }
  }
}
```

## Other MCP clients

Add a local `stdio` server with this command and these arguments:

```json
{
  "command": "php",
  "args": [
    "/absolute/path/to/your-app/artisan",
    "mcp:start",
    "newdebugbar"
  ]
}
```

Each client uses a different place or file for this setting. Look for its local MCP or `stdio` server setup.

## Check the connection

Your coding tool should show these five tools:

- `list-debug-profiles`
- `get-debug-profile-section`
- `get-debug-profile-data`
- `inspect-debug-queries`
- `get-debug-findings`

Then visit a page in your Laravel app and ask your agent:

> Inspect the New Debug Bar profile for the page I just visited. Tell me what happened, what looks wrong, and what I should inspect next.

When the agent can read the page's response headers, `X-NewDebugBar-Profile` points it to the exact profile. Otherwise, it can find the profile from the recent request list. The newest profile is not always the page you meant because background requests may run after it.

Agents should start with findings and one small section. When they need detail that a focused tool leaves out, `get-debug-profile-data` can read every retained profile value. It starts at `/sections` and returns paths the agent can follow into objects, lists, and exact values. Lists are paged and large strings are split into bounded chunks, so the agent never needs the full profile in one response.

For complete Eloquent evidence, follow `/sections/models/payload/model_groups`. The path includes logical write folding, record identifiers, capture-redacted changed attributes, sources, timings, exact-source query correlation, and guidance while the focused Models response stays concise.

## Capture, redaction, and access

Every retained profile value can be read through `get-debug-profile-data`, including mail bodies and attachment data. A focused tool may leave those values out to keep its response small; that does not make them private from the agent.

Recognized credentials are replaced with `[redacted]` during capture and checked again before every profile write. Background correlation records use the same redaction rules. This is not a promise that arbitrary secrets inside SQL values, logs, HTML, mail text, or files can be detected. Normal local diagnostics stay enabled by default.

To change capture rules, publish the configuration once:

```bash
php artisan vendor:publish --tag=newdebugbar-config
```

In `config/newdebugbar.php`, `redact` adds sensitive field names or dotted paths:

```php
'redact' => [
    'request.query.patient',
    'mail.items.*.preview.subject',
],
```

Rules match a complete path or its suffix at any depth. `*` matches any characters, including dots. Names are normalized, so `patientId`, `patient-id`, and `patient_id` match the same name. Both logical paths such as `request.query.patient` and stored paths such as `sections.request.payload.query.patient` work. An empty list does not turn off the built-in credential rules.

Rules select fields, not every occurrence of the same text. Known query/input, mail, Livewire, and linked background copies keep their masks when presented. A whole-record mask retains a `redacted: true` marker; a whole collection becomes an empty list with an accompanying `*_redacted` flag, so other evidence remains readable.

Other capture and access settings are:

- `except`: request paths to skip, such as `['billing/*']`. The default is `[]`. This stops capture for matching HTTP requests; it is not an access rule for saved profiles or a filter for background work.
- `access`: an optional callable or invokable class receiving `Illuminate\Http\Request`. Only the boolean `true` allows access. The default `null` allows local HTTP access. The check runs when the bar loads or hydrates and before each mail preview or attachment read. It does not restrict the local MCP process, which uses the package's enabled and environment checks.
- `mail_preview.capture_eml`: set to `false` to skip raw MIME construction and storage. Normal HTML and text previews remain. The default is `true`.
- `mail_preview.capture_attachment_bodies`: set to `false` to avoid reading or retaining attachment bodies, including their copy inside EML. File metadata remains, but size is unavailable when the body was not read. The default is `true`.

For an access rule that works with Laravel's config cache, set `access` to your invokable class name rather than a closure. Keep `environments` limited to local development. Capture settings affect future writes, not files already saved. Restart the client's local MCP server after changing its app configuration.

### Missing mail content

Read `/sections/mail/payload/items/{index}/preview` to inspect retained mail content. A `null` `eml` or attachment `body_base64` means that download is unavailable.

- `eml_omitted_reason` is `capture_disabled` when raw MIME capture is off, `redacted_fields` when a redacted preview field required removing its raw MIME copy, or `profile_budget` when it did not fit the total profile limit.
- Each attachment's `body_omitted_reason` is `capture_disabled`, `attachment_budget`, `unreadable`, `redacted`, or `profile_budget`. A retained body has a `null` reason.
- HTML or text removed by the total profile limit has a `null` value and an `html_omitted_reason` or `text_omitted_reason` of `profile_budget`.
- `attachments_omitted` counts bodies not retained; `attachment_metadata_omitted` counts files whose metadata did not fit the item limit.

### Missing evidence from large profiles

Each saved profile has a fixed 10,000,000-byte ceiling, separate from the configurable MCP response limits. Inspect `/storage` with `get-debug-profile-data` for size-limit metadata when present:

- `max_bytes`, `original_bytes`, and `truncated` describe the size limit and whether it removed evidence.
- `omitted_value_count`, `omitted_item_count`, and `omitted_section_count` give totals.
- `omitted_values` lists value paths, `omitted_items` maps list paths to omitted counts, and `omitted_sections` lists sections whose payloads were omitted. These detail lists are bounded; a positive `omitted_paths_truncated` count means they do not list every omission.

Unless explicitly redacted, section `summary.retained_count` counts records still present after storage trimming. It counts `payload.items` for ordinary collectors, or components plus activity for Livewire, counting the `items` and `activity` aliases once. Query `transaction_retained_count` separately counts retained transaction events. Capture totals and aggregate timings stay unchanged. `dropped_count` and `transaction_dropped_count` still describe capture limits; `storage_omitted_items` separately counts records removed by the file limit. Removing only mail bodies does not reduce retained message counts.

Treat missing evidence as missing, not proof that the work did not happen. Paging or increasing the MCP response limit cannot recover data omitted before storage.

## Fix common problems

- **The server is missing:** Make sure the package is installed, the app uses the `local` environment, and `NEWDEBUGBAR_ENABLED` is not `false`.
- **The command cannot find PHP:** Replace `php` with the full path to your PHP program.
- **The wrong app opens:** Check that the path points to that app's `artisan` file.
- **No profiles appear:** Visit a page in the app first, then try again.
- **The client only runs online:** This server needs a local client that can start a command on your computer.
