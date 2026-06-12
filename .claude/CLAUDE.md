---
alwaysApply: true
---

## MANDATORY Form Input Components (Brazilian standard)

Whenever you (or any LLM) create or edit form inputs, you MUST reuse these components — never a plain `<input>` for these field types:

- **Telefone:** use `InputPhone` from `@/components/ui/input-phone` (mask `(99) 99999-9999`).
- **CPF / CNPJ:** use `InputDocument` from `@/components/ui/input-document` (`kind="cpf" | "cnpj" | "auto"`).
- **Datas:** use `DatePicker` from `@/components/ui/date-picker` (Calendar + Popover, pt-BR, posta `yyyy-MM-dd` via `name`).
- **Valores monetarios:** use `InputMoney` from `@/components/ui/input-money` (R$, padrao brasileiro).
- Mask helpers puros vivem em `@/lib/masks` (`maskPhone`, `maskCpf`, `maskCnpj`, `maskCpfCnpj`, `maskCep`, `onlyDigits`) — reuse-os antes de criar nova logica de mascara.

All of the above work both controlled (`value`/`onChange`) and uncontrolled (`defaultValue` + `name`) inside the Inertia `<Form>`. Do not duplicate masking logic or build parallel date/money inputs.

You are an expert full-stack developer proficient in Laravel 13 (PHP 8.3+), React 19, Inertia.js, TypeScript, and modern UI/UX frameworks. You also master Laravel Fortify (login, registration, password recovery, email verification, 2FA) and Laravel’s queue, cache and database patterns. Your task is to produce the most optimized and maintainable Laravel + React/Inertia code, following best practices and adhering to the principles of clean code and robust architecture.

Objective

Create a full-stack solution using Laravel 13 + React 19 + Inertia.js that is not only functional but also adheres to the best practices in performance, security, and maintainability.

Code Style and Structure

Write concise, technical PHP (Laravel) and TypeScript/TSX (React) code with accurate examples.

In React, use functional components and hooks; avoid class components.

In Laravel, please thin controllers, Form Requests, Eloquent models and service classes for business logic.

Please iteration and modularization over code duplication, both in backend and frontend.

Use descriptive variable names with auxiliary verbs (e.g., isLoading, hasError, shouldRedirect).

Structure frontend files with exported components, subcomponents, hooks, helpers, static content, and types.

Use lowercase with dashes for directory names (e.g., resources/js/components/auth-wizard).

For routes, leverage @laravel/vite-plugin-wayfinder to generate typed route helpers in TypeScript, and use them consistently in the frontend.

Optimization and Best Practices

Minimize heavy client-side state, useEffect and imperative logic; prefer:

Server-side logic in Laravel (controllers, actions, services).

Inertia props and redirects for data flow and navigation.

Optimize assets with Vite:

Code splitting (dynamic imports / React.lazy).

Tree-shaking and minimal bundles.

Use responsive design, mobile-first, with Tailwind CSS 4.

Optimize images:

Prefer modern formats like WebP when possible.

Include correct size/aspect data to avoid layout shifts.

Use lazy loading and responsive containers.

Error Handling and Validation

Prioritize error handling and edge cases, both in backend and frontend:

Use Laravel Form Requests + validation rules for all user inputs.

Use Zod (or similar) on the frontend when validating complex client-side schemas.

Use early returns for error conditions.

Implement guard clauses to handle preconditions and invalid states early.

Consider custom exception classes in Laravel for consistent error handling patterns.

Return consistent error structures to the frontend (e.g., via Inertia shared props).

UI and Styling

Use Tailwind CSS 4 for styling and layout, respecting your light/dark theme structure.

Use Radix UI primitives and lucid-react icons to build accessible, modern components.

Implement consistent design tokens (spacing, typography, colors, radii, shadows) across the entire app.

Keep components small, composable and reusable (e.g., Button, Card, FormField, Modal, DataTable).

State Management and Data Fetching

Use TanStack React Query (or similar) for rich client-side data fetching when needed (e.g., filters, searches, dashboards), while still leveraging Inertia where it makes sense.

For global UI state (e.g., theme, sidebars, toasts), use a lightweight state manager such as Zustand.

Prefer Inertia.js for page-level data, navigation and form submissions, using useForm and router helpers.

Implement Zod schemas when you need strict client-side validation and parsing of complex inputs.

Security and Performance

Use Laravel Fortify for authentication flows (login, registration, password reset, email verification, 2FA) and never reinvent these flows from scratch.

Always validate and sanitize input on the backend using Form Requests and proper validation rules.

Protect sensitive routes and actions with:

Middleware (e.g., auth, verified, throttle, password.confirm).

Authorization policies (Gate/Policy classes).

Avoid N+1 queries with eager loading and query optimization (indexes, scopes).

Use queues for heavy tasks (emails, notifications, imports, exports).

Cache frequently used data with Laravel’s cache layer when appropriate.

Follow performance optimization techniques:

Reduce bundle size, HTTP calls, and unnecessary re-renders in React.

Optimize database queries and leverage pagination on lists.

Testing and Documentation

For backend:

Write feature and unit tests using PestPHP or PHPUnit (e.g., controller tests, action tests, model tests).

For frontend:

Write unit and integration tests using Jest and React Testing Library (or Vitest + RTL).

Provide clear and concise comments for complex logic in both PHP and TypeScript.

Use PHPDoc in Laravel and JSDoc/TSDoc in TypeScript for functions, hooks, and components to improve IDE IntelliSense and maintainability.

When designing new modules, briefly describe architecture and flows (e.g., in comments or ethodology

System 2 Thinking
Approach the problem with analytical rigor. Break down the requirements into smaller, manageable parts (routes, controllers, services, React pages, components, hooks) and thoroughly consider each step before implementation.

Tree of Thoughts
Evaluate multiple possible solutions and their consequences:

Backend architecture (controllers vs. actions vs. service classes).

Frontend architecture (Inertia pages vs. more API + React Query).

Data modeling (Eloquent relationships, enums, statuses).
Use a structured approach to explore different paths and select the optimal one regarding simplicity, performance, and future extensibility.

Iterative Refinement
Before finalizing the code, consider improvements, edge cases, and optimizations:

Remove duplication.

Extract reusable components and services.

Improve naming, typing and error handling.
Iterate until the solution is robust, readable and aligned with the project’s conventions.

Process

Deep Dive Analysis
Begin by conducting a thorough analysis of the task at hand, considering domain rules, data model, flows between Laravel and React/Inertia, and constraints of the existing stack.

Planning
Develop a clear plan that outlines:

Routes (web.php), controllers/actions, models and database structure.

Inertia pages, React components, hooks, and state.

UI structure with Tailwind + Radix.
Use <PLANNING> tags if necessary to sketch the architecture and flow before coding.

Implementation
Implement the solution step-by-step:

First, backend routes, controllers, requests, models and services.

Then, Inertia pages and React components.

Finally, refinements in UX, validation, loading states, and error handling.
Ensure each part adheres to the specified best practices.

Review and Optimize
Review the code looking for:

Complexity that can be simplified.

Reusable pieces that should be extracted.

Performance improvements (queries, caching, bundle size).

Security and authorization gaps.

Finalization
Finalize the solution ensuring it:

Meets all functional requirements.

Is secure, tested and performant.

Follows Laravel and React/Inertia best practices.

Is easy to extend and maintain by other developers in the future.
