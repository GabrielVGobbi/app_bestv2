---
version: alpha
name: BestPlay Editorial Workspace
description: A calm operational workspace that combines Alpaca's editorial clarity with BestPlay's musical and commercial identity.
colors:
    background: '#ffffff'
    foreground: '#242424'
    surface: '#ffffff'
    surface-soft: '#f7f7f7'
    surface-active: '#f1f1f1'
    border: '#e8e8e8'
    border-strong: '#dedede'
    muted: '#686868'
    subtle: '#8c8c8c'
    primary: '#eb0459'
    primary-strong: '#b80643'
    primary-foreground: '#ffffff'
    success: '#008f5f'
    warning: '#f59e0b'
    destructive: '#e60023'
typography:
    sans:
        fontFamily: Geist Variable
    body:
        fontFamily: Geist Variable
        fontSize: 14px
        fontWeight: 400
        lineHeight: 1.5
        letterSpacing: -0.01em
    title:
        fontFamily: Geist Variable
        fontSize: 32px
        fontWeight: 600
        lineHeight: 1.1
        letterSpacing: -0.035em
    section-title:
        fontFamily: Geist Variable
        fontSize: 24px
        fontWeight: 600
        lineHeight: 1.2
        letterSpacing: -0.03em
    label:
        fontFamily: Geist Variable
        fontSize: 12px
        fontWeight: 500
        lineHeight: 1.4
        letterSpacing: 0.01em
rounded:
    xs: 6px
    sm: 8px
    md: 12px
    lg: 14px
    xl: 16px
    full: 9999px
spacing:
    xs: 4px
    sm: 8px
    md: 12px
    lg: 16px
    xl: 24px
    2xl: 32px
    3xl: 48px
    4xl: 64px
    header: 60px
    sidebar: 250px
components:
    button-primary:
        backgroundColor: '{colors.primary-strong}'
        textColor: '{colors.primary-foreground}'
        rounded: '{rounded.sm}'
        height: 36px
    input:
        backgroundColor: '{colors.surface}'
        textColor: '{colors.foreground}'
        rounded: '{rounded.sm}'
        height: 40px
    card:
        backgroundColor: '{colors.surface}'
        textColor: '{colors.foreground}'
        rounded: '{rounded.md}'
    navigation-item:
        backgroundColor: '{colors.surface-active}'
        textColor: '{colors.foreground}'
        rounded: '{rounded.sm}'
        height: 36px
---

## Overview

BestPlay is an operational workspace for musical, commercial, financial, and administrative work. Its interface is editorial and restrained: clear typography, quiet neutral surfaces, compact navigation, and deliberate use of the BestPlay pink accent make complex daily work feel ordered without removing the brand's energy.

## Colors

Use neutral surfaces for structure and reserve primary pink for actions, focus, selection, and priority. Status colors communicate meaning and never replace a text label or icon.

| Role           | Light     | Dark      |
| -------------- | --------- | --------- |
| Background     | `#ffffff` | `#101010` |
| Surface        | `#ffffff` | `#171717` |
| Soft surface   | `#f7f7f7` | `#202020` |
| Active surface | `#f1f1f1` | `#282828` |
| Border         | `#e8e8e8` | `#303030` |
| Foreground     | `#242424` | `#f5f5f5` |
| Muted          | `#686868` | `#a3a3a3` |
| Primary        | `#eb0459` | `#ff2b73` |

## Typography

Use Geist Variable across navigation, content, controls, and data. Titles use compact line heights and negative tracking; body text stays neutral and readable. Use the system monospace stack only for commands, identifiers, and technical values.

## Layout

The persistent desktop shell uses a 60px topbar and a 250px contextual sidebar that collapses to an icon rail. Page content uses a 4px spacing foundation, 24px mobile gutters, and 32px desktop gutters. Group related controls with spacing before adding separators. Keep forms narrow, data surfaces fluid, and dashboards bounded by a readable maximum width.

At compact widths, preserve the topbar and move the sidebar into a focus-managed drawer. Components adapt to their available container; critical actions remain in the normal flow or stable chrome and never depend on horizontal scrolling.

## Elevation & Depth

Create hierarchy with tonal layers and borders. Standard cards use no visible shadow; floating search, menus, drawers, and dialogs may use a soft shadow. Avoid stacking borders, rings, and strong shadows on the same surface.

## Shapes

Navigation and compact controls use 8px corners, cards and data surfaces use 12–14px, and dialogs use 16px or more. Reserve full pills for statuses, avatars, and isolated compact actions.

## Components

Primary buttons carry the pink accent; secondary controls use quiet neutral surfaces. Inputs always retain a visible border and focus ring. Cards use padding and alignment to communicate grouping. Tables keep headers calm, numeric data tabular, and row actions discoverable by keyboard. Loading skeletons mirror final geometry, while empty and error states provide a direct next action.

## Do's and Don'ts

- Do use one dominant action per region and keep secondary actions visually quiet.
- Do preserve visible keyboard focus, semantic labels, reduced motion, and WCAG AA contrast.
- Do let long names wrap or truncate deliberately without hiding critical actions.
- Don't use decorative gradients as primary page backgrounds.
- Don't use pink for large passive surfaces or as the only status signal.
- Don't introduce page-local spacing, radius, typography, or shadow systems.
