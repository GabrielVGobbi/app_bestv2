---
name: wayfinder-development
description: Use or repair Wayfinder-generated TypeScript route helpers for Laravel links and forms.
license: MIT
metadata:
  author: laravel
---

# Wayfinder routes

Import existing named helpers from `@/actions` or `@/routes`. Inspect the generated helper's signature and the corresponding route/controller; modules may use different namespaces from generic documentation examples.

- Use the route object with compatible Inertia APIs, `.url()` when a string is needed, and `.form()` for supported form attributes.
- Preserve route binding keys, required parameters, HTTP methods, and query options.
- Fix Laravel routes or generation configuration rather than editing generated TypeScript.
- `vite.config.ts` already enables Wayfinder with `formVariants: true`. If generated helpers are stale after route changes and the dev/build pipeline has not regenerated them, run `php artisan wayfinder:generate --with-form --no-interaction`.
- Query Boost `search-docs` with `laravel/wayfinder` when binding, query, or method behavior is unclear.

Verify imports with TypeScript and exercise the affected link/form. Regeneration is not required for an unrelated frontend edit.
