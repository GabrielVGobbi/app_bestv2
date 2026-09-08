---
name: tailwindcss-development
description: Implement Tailwind CSS 4 utilities and theme changes in BestPlay's existing UI.
license: MIT
metadata:
  author: laravel
---

# Tailwind CSS 4

Use existing utilities, component variants, and theme tokens from `resources/css/app.css`. Follow `DESIGN.md` for visual changes; keep existing theme and responsive behavior.

- Configuration is CSS-first. Reuse the existing `@import "tailwindcss"` and theme setup rather than introducing a v3 configuration.
- Use v4 utilities: `shrink-*`, `grow-*`, `text-ellipsis`, and color opacity modifiers such as `bg-black/50`.
- Choose gap, padding, and margins according to the existing layout; avoid blanket spacing rewrites.
- Reuse component variants and `cn`/class utilities rather than accumulating conflicting classes.
- Preserve focus, disabled, error, and reduced-motion states where applicable. Match the current light/dark theme support rather than adding a new theme.
- Use the repository's Prettier configuration for utility ordering.

For unfamiliar utilities or configuration behavior, use Boost `search-docs` with `tailwindcss` and the specific topic. Check the rendered result at the affected breakpoints; a class-name change alone does not prove layout correctness.
