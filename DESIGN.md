---
version: alpha
name: BestPlay Editorial Workspace
description: An operational workspace with ink chrome, a quiet gray canvas, and BestPlay pink used only as accent.
colors:
    background: '#ffffff'
    foreground: '#171717'
    canvas: '#f4f4f5'
    surface: '#ffffff'
    surface-soft: '#f4f4f5'
    surface-active: '#f1f1f1'
    border: '#d4d4d8'
    border-strong: '#d4d4d8'
    muted: '#52525b'
    subtle: '#a1a1aa'
    primary: '#eb0459'
    primary-strong: '#b80643'
    primary-foreground: '#ffffff'
    success: '#008f5f'
    warning: '#f59e0b'
    destructive: '#e60023'
    info: '#3654d4'
    inflow: '#008f5f'
    outflow: '#e60023'
    sidebar: '#171717'
    header: '#171717'
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
    header: 68px
    sidebar: 264px
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
        backgroundColor: '{colors.sidebar}'
        textColor: '#f5f5f5'
        rounded: '{rounded.sm}'
        height: 40px
---

## Overview

BestPlay is an operational workspace for musical, commercial, financial, and administrative work. The authenticated shell uses ink chrome (header and sidebar), a quiet gray canvas, and white cards. Saturated brand pink is reserved for actions, focus, and priority. Active navigation uses a soft rose fill, never a vertical rail or a washed magenta slab.

## Colors

Use the canvas for page structure, white cards for data, and ink chrome for persistent navigation. Reserve primary pink for actions, focus, and priority. Active sidebar and header items use the soft rose accent surface. Status and cash-flow colors communicate meaning and never replace a text label, icon, or sign. Inflow is green, outflow is red, pending action is amber, overdue is destructive, and informational states use a contained blue.

| Role           | Light     | Dark      |
| -------------- | --------- | --------- |
| Background     | `#ffffff` | `#101010` |
| Canvas         | `#f4f4f5` | `#101010` |
| Surface        | `#ffffff` | `#171717` |
| Soft surface   | `#f4f4f5` | `#202020` |
| Active surface | `#f1f1f1` | `#282828` |
| Border         | `#d4d4d8` | `#303030` |
| Foreground     | `#171717` | `#f5f5f5` |
| Muted          | `#52525b` | `#a3a3a3` |
| Primary        | `#eb0459` | `#ff2b73` |
| Success        | `#008f5f` | `#31c48d` |
| Warning        | `#f59e0b` | `#fbbf24` |
| Destructive    | `#e60023` | `#ff4d5f` |
| Info           | `#3654d4` | `#7c8cff` |
| Inflow         | `#008f5f` | `#31c48d` |
| Outflow        | `#e60023` | `#ff4d5f` |
| Sidebar        | `#171717` | `#202020` |
| Header         | `#171717` | `#171717` |
| Sidebar muted  | `#a1a1aa` | `#a1a1aa` |
| Sidebar accent | `#52283a` | `#52283a` |

## Typography

Use Geist Variable across navigation, content, controls, and data. Titles use compact line heights and negative tracking; body text stays neutral and readable. Use the system monospace stack only for commands, identifiers, and technical values.

## Layout

The persistent desktop shell uses a 68px topbar and a 264px contextual sidebar that collapses to an icon rail. Page content uses a 4px spacing foundation, 24px mobile gutters, and 32px desktop gutters. Group related controls with spacing before adding separators. Keep forms narrow, data surfaces fluid, and dashboards bounded by a readable maximum width.

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
- Don't use pink for large passive surfaces, washed nav backgrounds, or as the only status signal.
- Don't introduce page-local spacing, radius, typography, or shadow systems.
