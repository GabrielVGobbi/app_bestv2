---
name: frontend-design
description: Design or refine BestPlay interfaces while preserving the existing visual identity and reusable components.
license: Complete terms in LICENSE.txt
---

# BestPlay interface design

Use [DESIGN.md](../../../DESIGN.md) and the affected page's actual components/tokens for visual decisions. BestPlay uses Geist, ink navigation, a quiet gray canvas, white surfaces, and pink accents. Explain relevant differences between the design document and current code before changing the design.

For an existing screen, preserve its density, typography, spacing, borders, radius, and interaction conventions. A localized fix does not call for a new visual direction. For an explicitly requested redesign, choose a direction that serves the workflow and implement the requested scope.

Reuse layouts, cards, tables, buttons, dialogs, dropdowns, badges, inputs, and empty/loading states in `resources/js/components`. Preserve keyboard access, visible focus, labels, responsive behavior, and the feature's destructive-action confirmation. Use restrained motion with reduced-motion support when motion helps the interaction.

## Brazilian form fields

Reuse these components instead of duplicating masks or substituting plain inputs for these field types:

| Field | Component / import |
| --- | --- |
| Phone | `InputPhone` from `@/components/ui/input-phone` |
| CPF/CNPJ | `InputDocument` from `@/components/ui/input-document`, with the appropriate `kind` |
| Date | `DatePicker` from `@/components/ui/date-picker` |
| Money | `InputMoney` from `@/components/ui/input-money` |

Check their props and existing usage for controlled/uncontrolled forms and submitted values. Reuse `@/lib/masks` for existing phone, document, postal-code, and digit helpers. Preserve pt-BR display and backend date/money formats.

For utility or theme changes, consult [tailwindcss-development](../tailwindcss-development/SKILL.md). For submission or navigation behavior, consult [inertia-react-development](../inertia-react-development/SKILL.md). Inspect the affected UI at relevant sizes and exercise its changed interaction before claiming it is verified.
