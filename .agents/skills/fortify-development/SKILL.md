---
name: fortify-development
description: Customize or debug Fortify login, registration, password recovery, verification, and two-factor flows.
license: MIT
metadata:
  author: laravel
---

# Fortify authentication

Extend the existing authentication flow. Inspect the relevant configuration in `config/fortify.php`, callbacks in `app/Providers/FortifyServiceProvider.php`, and actions in `app/Actions/Fortify` before replacing authentication behavior.

Use Boost `search-docs` with `laravel/fortify` for the affected feature. Discover actual registered routes through Artisan rather than assuming default endpoints.

- **Registration or credentials:** Reuse the existing action, validation rules, guard, username field, and configured login rate limiter.
- **Password reset or email verification:** Preserve the enabled feature, notification flow, named routes, and required middleware/model contracts.
- **Two-factor authentication:** Check the model trait, existing columns, confirmation requirements, challenge, and recovery-code handling. Publish or migrate only if the requested change needs missing schema.
- **Redirects or custom responses:** Use existing view callbacks or Fortify response contracts rather than parallel authentication controllers.
- **SPA/headless authentication:** Inspect existing Sanctum, session, CSRF, and response negotiation configuration. Do not turn off Fortify views or install packages merely because the UI uses React.

Keep authentication data private. Verify the affected success, rejection, throttling, and authorization paths using existing tests, with mail and external effects appropriately faked.
