---
name: laravel-best-practices
description: Implement or review Laravel backend behavior and tests using this project's module conventions.
license: MIT
metadata:
  author: laravel
---

# Laravel backend

Follow the affected module in `app/Modules` and the repository's `AGENTS.md`. Existing conventions take precedence over generic alternatives in the references. Consult Boost `search-docs` when an API or behavior depends on the installed version.

Read only the references needed for the change; this index does not require a full audit or a subagent.

| Task | Reference |
| --- | --- |
| Queries and N+1 diagnosis | [Database performance](rules/db-performance.md), [advanced queries](rules/advanced-queries.md) |
| Models and relationships | [Eloquent](rules/eloquent.md) |
| Schema changes | [Migrations](rules/migrations.md) |
| Authorization and private data | [Security](rules/security.md) |
| Request validation | [Validation](rules/validation.md) |
| Routes and controllers | [Routing](rules/routing.md) |
| Behavior tests | [Testing](rules/testing.md) |
| Caching or configuration | [Caching](rules/caching.md), [configuration](rules/config.md) |
| Background jobs or schedules | [Queues](rules/queue-jobs.md), [scheduling](rules/scheduling.md) |
| External API calls | [HTTP client](rules/http-client.md) |
| Events, notifications, or email | [Events](rules/events-notifications.md), [mail](rules/mail.md) |
| Exception handling | [Errors](rules/error-handling.md) |
| Architectural decisions | [Architecture](rules/architecture.md) |
| Collection processing | [Collections](rules/collections.md) |
| Blade views | [Blade](rules/blade-views.md) |
| PHP naming and style | [Style](rules/style.md) |

Keep authorization and ownership checks at the existing boundaries. Move expensive work to the feature's queue flow when needed; preserve idempotency and private storage access. Do not add layers, indexes, caching, or abstractions solely to satisfy a generic example.
