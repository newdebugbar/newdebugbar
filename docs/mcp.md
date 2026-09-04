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

For retained view data, follow `/sections/views/payload/items/{index}/data`. Renderable views and lazy component methods appear as class labels, such as `[Illuminate\View\InvokableComponentVariable]`. Capturing them does not run or render them. Ordinary string values and already rendered slots keep their captured content, subject to redaction and size limits.

For complete Eloquent evidence, follow `/sections/models/payload/model_groups`. The path includes logical write folding, record identifiers, capture-redacted changed attributes, sources, timings, exact-source query correlation, and guidance while the focused Models response stays concise.

## Fix common problems

- **The server is missing:** Make sure the package is installed, the app uses the `local` environment, and `NEWDEBUGBAR_ENABLED` is not `false`.
- **The command cannot find PHP:** Replace `php` with the full path to your PHP program.
- **The wrong app opens:** Check that the path points to that app's `artisan` file.
- **No profiles appear:** Visit a page in the app first, then try again.
- **The client only runs online:** This server needs a local client that can start a command on your computer.
