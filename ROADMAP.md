# Roadmap

## Considering

These are product gaps under consideration, not promised release dates or a delivery order.

### Requests and performance

- Add a searchable browser history for every retained page, API, command, test, and worker profile.
- Compare duration, queries, failures, and section activity between two profiles.
- Show failed SQL with its bindings, connection, exception, and application source.
- Explain where request time went across captured layers without counting the same work twice.
- Let application rules decide whether to capture one request after route and authentication context is known.

### Laravel ecosystem

- Add an Inertia inspector for components, props, partial or deferred data, and application source.
- Add a Laravel AI inspector for agents, providers, models, token use, tool calls, and failures.
- Add a Pennant inspector for evaluated feature flags and their values.

### Developer workflow

- Add a public API for application messages, timers, measurements, and manually reported exceptions.
- Let packages add bounded collector sections while preserving redaction and MCP access.
- Open captured source in local editors with path mapping for Docker and remote environments.
- Expose useful request timing through Server-Timing headers in browser developer tools.
