# Architecture Overview


## Request Flow
The monitoring process begins with an entry routing, where requests are accepted
through one of three main channels:

- **HTTP Requests**

  Incoming monitoring calls via the configured endpoint are handled by the
  `MonitoringMiddleware`. The endpoint is configurable through extension
  settings.

- **Command-Line Interface (CLI)**

  Monitoring checks can be triggered manually, programmatically during
  post-deployment procedures, or through cron jobs using the built-in CLI
  command: `typo3 monitoring:run`

- **Backend Module**
  Checks can be initiated from within the TYPO3 backend interface.

All routes lead into the **Authorization Layer**, which ensures only authorized
requests are processed. This layer supports multiple strategies, including
token-based HMAC validation and TYPO3 backend user authentication.

Once authorized, the request is passed to the **Provider Execution Layer**.
Here, the extension dynamically loads and executes monitoring providers. These
providers perform the actual health checks and return structured results.

The **Result** is then formatted and passed to the appropriate output channel,
depending on how the check was initiated:

- **JSON Response**: Returned to HTTP clients as a JSON payload.
- **Command-Line Output**: Displayed directly in the terminal for CLI users.
- **Backend Dashboard**: Presented via the TYPO3 backend module.

## Component Summary

- [`MonitoringMiddleware`](middleware.md) handling HTTP requests and orchestrates the
entire monitoring workflow.
- [`Authorization Layer`](authorization.md) applying configured authorizers to validate and
secure access.
- [`Provider System`](providers.md) executing custom or built-in monitoring logic through
discoverable providers.
- Result Handling aggregating provider outputs and formats the response
accordingly.

```
      ┌────────────────────────────────────────────────────────────┐
      │                       ENTRY ROUTING                        │
      ├───────────────┬───────────────────────┬────────────────────┤
      │ HTTP Request  │                       │   Backend Module   │
      │      ↓        │ Comand-Line Interface │         ↓          │
      │  Middleware   │                       │     Controller     │
      └───────────────┴───────────────────────┴────────────────────┘
                                   ↓
                    ┌────────────────────────────────┐
                    │       Authorization Layer      │
                    │                                │
                    │         (extendable)           │
                    └────────────────────────────────┘
                                   ↓
                    ┌────────────────────────────────┐
                    │      Provider Execution        │
                    │  (invokes the actual checks)   │
                    │                                │
                    │         (extendable)           │
                    └────────────────────────────────┘
                                   ↓
                    ┌────────────────────────────────┐
                    │       Execution Result         │
                    │                                │
                    │  (built-in, optional caching)  │
                    └────────────────────────────────┘
                                   ↓
      ┌────────────────────────────────────────────────────────────┐
      │                         OUTPUT                             │
      ├───────────────┬────────────────────────┬───────────────────┤
      │ JSON Response │ Command-Line Interface │ Backend Dashboard │
      └───────────────┴────────────────────────┴───────────────────┘
```

This architecture aims to allow TYPO3 administrators and DevOps teams to monitor
their instances and their critical components effectively while supporting
extensibility. Whether you're using the API, CLI, or the TYPO3 backend,
the monitoring extension ensures consistent behavior across all entry points.
