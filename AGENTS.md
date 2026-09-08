# BestPlay — project instructions

## Completion and scope

Carry the requested task through implementation, appropriate verification, and fixes for failures caused by the change. Finish when the requested behavior is complete and verified, or explain a concrete blocker. A first implementation is not a review checkpoint unless the user asks for one.

Proceed with relevant local reads, reversible edits, and checks without asking for confirmation at each step. Ask only when a missing decision materially affects the result or an action exceeds existing authorization, such as an unrequested dependency/architecture change, destructive data operation, publication, deployment, or external message. Preserve unrelated work already in the working tree.

## Project context

- Laravel 13, PHP 8.3+, Inertia 3, React 19, TypeScript, Tailwind CSS 4, Vite. Use the installed versions in the lockfiles for exact APIs.
- Backend features live in `app/Modules/<Feature>`; shared application code also lives in `app/`. Follow the affected module's structure, routes, services, and tests.
- Inertia pages live in `resources/js/pages`. Reuse existing layouts, UI components, types, hooks, and route helpers.
- Check relevant neighboring code. Use `.docs/project` for product/architecture context and `.docs/implementations` for feature contracts and historical plans; consult only the relevant document and distinguish planned work from implemented behavior. `.docs/best_play.obs` is the documentation navigation vault. Read `DESIGN.md` for visual changes. If relevant documentation conflicts with code, explain the difference before changing behavior.
- Do not create or edit product documentation unless requested. Mention documentation made outdated by code changes.

## Skills and references

Use a skill when its specialized guidance helps the actual task, not merely because a keyword appears. Load only the relevant references. Canonical project skills live in `.agents/skills`; Claude and Cursor entrypoints refer there.

- Laravel behavior and backend tests: [laravel-best-practices](.agents/skills/laravel-best-practices/SKILL.md).
- Inertia pages, forms, navigation, and data flow: [inertia-react-development](.agents/skills/inertia-react-development/SKILL.md).
- Visual design: [frontend-design](.agents/skills/frontend-design/SKILL.md); Tailwind implementation: [tailwindcss-development](.agents/skills/tailwindcss-development/SKILL.md).
- Fortify authentication: [fortify-development](.agents/skills/fortify-development/SKILL.md).
- Generated TypeScript routes: [wayfinder-development](.agents/skills/wayfinder-development/SKILL.md).
- Pulse monitoring: [pulse-development](.agents/skills/pulse-development/SKILL.md).

## Implementation constraints

- Reuse the established feature flow rather than adding a parallel implementation or a new architectural layer. Preserve authorization, tenant/ownership checks, validation, and private-file access.
- Keep controllers focused on request/response coordination; use existing services/actions, Form Requests, policies, and API Resources where the feature uses them.
- Use named routes and existing Wayfinder helpers from `@/actions` or `@/routes`. Do not edit generated route files by hand.
- Follow PHP type hints, explicit return types, control-structure braces, and existing enum naming. Follow the repository's Pint, ESLint, and Prettier configuration for formatting.
- Keep TypeScript types explicit; avoid `any`. Preserve the feature's existing Inertia form and state-management approach.
- Use `php artisan make:*` for Laravel classes when the generator supports the required file; match the module's namespace and location. Check command help for unfamiliar options and use `--no-interaction` where supported.

## Tools and verification

- Use Boost `search-docs` for version-sensitive APIs or uncertain framework behavior, with relevant packages and topic queries. A wording or formatting edit does not require a documentation search.
- Use Boost `database-schema` before changing schema-dependent behavior when the schema is not already known; `database-query` for read-only database inspection; `browser-logs` for recent browser failures; `get-absolute-url` before sharing a project URL.
- Run the smallest meaningful checks for the affected behavior. Add or update PHPUnit tests for behavior changes when existing coverage does not establish the result. Formatting, prose, and agent instructions need their relevant syntax/link checks, not artificial application tests.
- `phpunit.xml` targets MySQL database `laravel_testing`. Before database tests, verify the effective connection targets a disposable test database; do not infer isolation from the command name or assume all external integrations are faked.
- Once that test environment is confirmed, run affected tests, fix failures introduced by the task, and rerun them without repeated approval: `php artisan test --compact tests/Feature/RelevantTest.php`. Broaden to the full suite only when impact or unresolved failures justify it.
- After PHP edits, run `vendor/bin/pint --dirty --format agent`; inspect its diff so unrelated user work is preserved.
- For frontend changes, use the relevant existing checks: `npm run types:check`, `npm run lint:check`, and `npm run build`, plus browser verification when the visible behavior matters. Fix stale assets with the existing build/dev workflow.
- Report what changed, what was verified, and any remaining limitation concisely in Portuguese. Do not claim unrun checks passed.
