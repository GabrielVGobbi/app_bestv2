---
name: inertia-react-development
description: Implement Inertia 3 React pages, forms, navigation, and server-prop data flows.
license: MIT
metadata:
  author: laravel
---

# Inertia + React

Pages live in `resources/js/pages`; use the affected page's layout, shared types, components, hooks, and form approach. Keep server routing on `Inertia::render()` and reuse existing Wayfinder helpers.

## Select the relevant workflow

- **Forms:** Follow the feature's `<Form>` or `useForm` usage. Keep field errors, processing state, and successful submission behavior. For Brazilian fields, use the components listed in [frontend-design](../frontend-design/SKILL.md).
- **Navigation:** Use Inertia `Link` or `router` for page visits; normal links remain appropriate for external destinations and downloads. Use named route helpers rather than copying URLs.
- **Standalone requests:** Follow an existing project API utility or query hook. Consider `useHttp` for JSON requests that do not perform page visits; do not migrate unrelated data fetching.
- **Deferred or paginated data:** Handle unloaded props and use the existing skeleton/empty state. Infinite scroll requires corresponding server support; do not apply it to every list.
- **Optimistic updates, polling, or instant visits:** Use only when the requested interaction benefits. Account for failed requests, rollback, and lifecycle cleanup.
- **Persistent layouts:** Follow existing layout-prop usage and verify the installed API before introducing a new pattern.

For version-sensitive work, query Boost `search-docs` with `inertiajs/inertia-laravel` and `@inertiajs/react`, selecting the topic above. Examples from older releases may not apply: v3 uses `router.cancelAll()`, `httpException`/`networkError` events, and `Inertia::optional()` instead of removed lazy props. Do not add Axios or a separate SSR server by assumption.

Verify the changed interaction and relevant TypeScript/build checks. Preserve React 19 and the project's React Compiler configuration; add manual memoization only for a demonstrated need.
