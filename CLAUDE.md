<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Stack

- php 8.3 | laravel/framework v13 | inertiajs/inertia-laravel v3 | livewire/livewire v4
- laravel/fortify v1 | laravel/sanctum v4 | laravel/wayfinder v0 | laravel/pulse v1
- laravel/boost v2 | laravel/mcp v0 | laravel/pail v1 | laravel/pint v1
- laravel/sail v1 | laravel/telescope v5 | larastan/larastan v3 | phpunit/phpunit v12
- @inertiajs/react v3 | react v19 | tailwindcss v4 | eslint v9 | prettier v3

## Skills

Activate skills in `**/skills/**` before working in their domain. Do not wait until stuck.

- Laravel backend → Laravel skill
- Inertia pages, React components, hooks, forms → Inertia/React skill
- UI, layout, Tailwind → frontend design skill
- Tests → testing skill

## Internal Documentation (Obsidian)

Path: `D:\laragon\www\ProV2\best_play\.docs\.best_play.obs`
Consult before working on: existing docs in obsidian

**When documentation exists:**
1. Read docs first, understand architecture before editing.
2. Follow documented flow unless user asks to change it.
3. Don't create parallel implementations.
4. Reuse existing patterns, names, folders, routes, hooks, jobs, tests, commands.
5. If docs and code conflict, explain the difference before changing anything.
6. If no docs exist, inspect nearby code and follow project conventions.

Do not create or edit documentation files unless explicitly asked. If code change makes docs outdated, mention which file needs updating.

## Conventions

- Follow existing code conventions. Check sibling files for structure, naming, and approach.
- Use descriptive, intent-expressing names (e.g., `isRegisteredForDiscounts`, not `discount()`).
- Check for existing components, hooks, services, actions, requests, resources, traits, jobs, and commands before writing new ones.
- Don't duplicate logic, create new base folders, change dependencies, or introduce new architectural patterns without approval.
- Avoid over-engineering. Implement the simplest maintainable solution.

## Verification & Debugging

- Don't create temporary scripts when tests cover the functionality.
- Prefer Artisan commands, tests, factories, and seeders over custom debugging files.
- Don't leave temporary debugging files in the project.

## Frontend

If a frontend change isn't reflected, ask user to run `npm run build`, `npm run dev`, or `composer run dev`.

## Replies

- Be concise. Focus on what matters. Avoid explaining obvious details.
- Prefer complete, directly usable code examples.
- When changing code, explain what changed and why.
- If something can't be verified, say so. Specify exact terminal location for commands.

=== boost rules ===

# Laravel Boost

## Tools

Prefer Boost tools over manual alternatives:

- `database-query` — read-only DB queries (instead of raw tinker SQL)
- `database-schema` — inspect tables before writing migrations, models, queries, resources, or validation
- `get-absolute-url` — resolve correct URL before sharing any project URL with the user
- `browser-logs` — read recent browser errors/exceptions (ignore old entries)

## search-docs

Always run `search-docs` before making code changes. Pass a `packages` array when relevant. Use multiple broad, topic-based queries. Package names are already shared — don't include them in queries.

Good: `rate limiting`, `routing`, `"infinite scroll"`
Bad: `Laravel 13 rate limiting`, `Inertia v3 forms`

Syntax: words = AND (auto-stemmed) | `"phrase"` = exact | multiple queries = OR.

## Artisan

Run commands directly via CLI. Use `php artisan list` to discover commands. Use `--help` to check parameters. Use `--no-interaction` for non-interactive execution.

Useful commands:
```bash
php artisan route:list --except-vendor
php artisan config:show app.name
php artisan migrate:status
```

Read `.env` directly for environment variables. Read `config/` for config files.

## Tinker

Use for debugging in application context. Don't create models via Tinker without approval. Always use single quotes to prevent shell expansion:

```bash
php artisan tinker --execute 'User::where("active", true)->count();'
```

=== php rules ===

# PHP

- Always use curly braces for control structures.
- Use PHP 8 constructor property promotion, explicit return types, and type hints for all parameters.
- Use nullable types explicitly (`?string`).
- Use TitleCase for Enum keys.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.
- Prefer early returns when they improve clarity.
- Avoid hidden side effects in getter-sounding methods.
- Keep methods focused on one responsibility.
- Use Laravel collections only when they improve clarity.

=== deployments rules ===

# Deployment

Prefer: Laravel Cloud, Laravel Forge, Envoyer, Supervisor-managed queues, Scheduler cron, proper `.env`, production-safe caching, queue workers, storage permissions, Nginx/PHP-FPM when relevant.

=== tests rules ===

# Tests

Every code change must be tested. Write or update a test, then run it.

```bash
php artisan test --compact                                          # all tests
php artisan test --compact tests/Feature/FooTest.php               # specific file
php artisan test --compact --filter=test_name                      # specific test
```

Run the minimum number of tests needed. Don't claim something works unless verified.

Don't remove tests or test files without approval. Tests are part of the application.

=== phpunit/core rules ===

# PHPUnit

All tests must be PHPUnit classes. If you see a Pest test, convert it to PHPUnit.

```bash
php artisan make:test --phpunit {Name}    # feature test
php artisan make:test --phpunit --unit {Name}  # unit test
```

Tests must cover: happy paths, failure paths, edge cases, authorization, validation, ownership checks.

Run the affected test after every update. When tests pass, ask user if they want the full suite.

=== inertia-laravel/core rules ===

# Inertia

- Components live in `resources/js/pages` (unless `vite.config.js` differs).
- Use `Inertia::render()` for server-side routing.
- Always use `search-docs` for version-specific examples.
- Activate `inertia-react-development` for client-side patterns.

## Inertia v3 changes

- `useHttp` hook for standalone HTTP requests
- Optimistic updates with automatic rollback
- `useLayoutProps` for layout props
- Instant visits, deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data
- Simplified SSR via `@inertiajs/vite` — no separate Node.js server needed
- Axios removed by default — use built-in XHR client or install Axios if project already uses it
- `Inertia::lazy()` / `LazyProp` removed → use `Inertia::optional()`
- `router.cancel()` → `router.cancelAll()`
- Events: `invalid` → `httpException`, `exception` → `networkError`
- `future` config namespace removed (all v2 options now always enabled)
- Prop types (`optional`, `defer`, `merge`) work inside nested arrays with dot-notation paths

Use animated skeleton/empty state when using deferred props.

=== laravel/core rules ===

# Laravel Way

Use `php artisan make:` for all new files (migrations, controllers, models, requests, resources, jobs, notifications, commands, policies, tests).

## Routing

Use named routes. Group by domain or feature. Use Route::resource/apiResource for CRUD. Declare middleware at route level.

```php
Route::middleware('auth')->group(function () {
    Route::resource('users', UserController::class);
});
```

## Model Creation

Create useful factories and seeders when creating models. Ask about related files only when scope is ambiguous.

## APIs & Resources

- Use Eloquent API Resources, API versioning, Form Request validation, Policies.
- Follow existing project API convention if different.

## URL Generation

Prefer named routes and `route()`. Use Wayfinder-generated helpers on the frontend when available.

## Validation

Use Form Requests for non-trivial validation. Keep controllers thin. Use authorization inside Form Requests when appropriate.

## Controllers

Coordinate request/response only. Move business logic to Services, Actions, Jobs, Form Requests, Resources, or Policies.

## Jobs & Queues

Use queued jobs for: exports, imports, notifications, webhooks, API sync, large reports, file processing. Make jobs idempotent when possible. Persist status in DB for long-running processes.

## Storage

Don't expose private files via public storage. Use authenticated routes and `Storage::disk()->download()` for private downloads.

=== wayfinder/core rules ===

# Wayfinder

Import from `@/actions/` (controller-backed routes) or `@/routes/` (named routes). Prefer Wayfinder helpers over hardcoded URLs when helpers already exist.

=== pint/core rules ===

# Pint

Run after modifying PHP files:

```bash
vendor/bin/pint --dirty --format agent
```

=== inertia-react/core rules ===

# Inertia + React

## React

- Follow existing component structure. Reuse existing UI components first.
- Move repeated stateful behavior into hooks when the pattern already exists.
- Don't introduce new state management libraries without approval.
- Prefer explicit, reusable TypeScript types. Avoid `any`.
- Use existing API route helpers, Wayfinder helpers, or project API utilities.
- Keep forms aligned with the existing Inertia form approach.

## UI

- Follow existing Tailwind and design system patterns.
- Reuse existing layout wrappers, cards, tables, buttons, dialogs, dropdowns, badges, inputs, empty states.
- Don't redesign unrelated screens while fixing a feature.
- Keep spacing, density, radius, borders, and typography consistent with nearby pages.
- Use skeletons or existing loading patterns for loading states.
- Use existing confirmation patterns for destructive actions.
- Show clear progress/status for long-running actions.

</laravel-boost-guidelines>
