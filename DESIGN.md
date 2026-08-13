---
name: SAPI Smart POS
description: Multi-tenant POS and inventory management for Indonesian UMKM operators.
colors:
  primary: "oklch(0.58 0.128 162)"
  primary-foreground: "oklch(0.99 0.005 150)"
  brand: "oklch(0.72 0.15 162)"
  brand-foreground: "oklch(0.21 0.05 162)"
  success: "oklch(0.62 0.12 200)"
  success-foreground: "oklch(0.99 0.005 150)"
  warning: "oklch(0.78 0.14 75)"
  warning-foreground: "oklch(0.27 0.05 75)"
  destructive: "oklch(0.58 0.2 27)"
  destructive-foreground: "oklch(0.99 0.005 150)"
  background: "oklch(0.993 0.004 150)"
  foreground: "oklch(0.23 0.015 160)"
  card: "oklch(1 0 0)"
  card-foreground: "oklch(0.23 0.015 160)"
  muted: "oklch(0.962 0.007 150)"
  muted-foreground: "oklch(0.48 0.012 160)"
  secondary: "oklch(0.95 0.008 150)"
  secondary-foreground: "oklch(0.27 0.015 160)"
  border: "oklch(0.9 0.008 150)"
  ring: "oklch(0.58 0.128 162)"
typography:
  display:
    fontFamily: "Plus Jakarta Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.33
    letterSpacing: "normal"
  headline:
    fontFamily: "Plus Jakarta Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 700
    lineHeight: 1.4
  title:
    fontFamily: "Plus Jakarta Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 600
    lineHeight: 1.4
  body:
    fontFamily: "Plus Jakarta Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Plus Jakarta Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 500
    lineHeight: 1.33
    letterSpacing: "0.01em"
  mono:
    fontFamily: "JetBrains Mono, ui-monospace, monospace"
    fontSize: "0.8125rem"
    fontWeight: 400
    lineHeight: 1.6
rounded:
  sm: "6px"
  md: "8px"
  lg: "10px"
  xl: "14px"
spacing:
  xs: "8px"
  sm: "12px"
  md: "16px"
  lg: "20px"
  xl: "24px"
  2xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.primary-foreground}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
  button-primary-hover:
    backgroundColor: "oklch(0.51 0.12 162)"
    textColor: "{colors.primary-foreground}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
  button-pay:
    backgroundColor: "{colors.success}"
    textColor: "{colors.success-foreground}"
    rounded: "{rounded.md}"
    padding: "12px 16px"
  button-pay-hover:
    backgroundColor: "oklch(0.55 0.11 200)"
    textColor: "{colors.success-foreground}"
    rounded: "{rounded.md}"
    padding: "12px 16px"
  button-open-bill:
    backgroundColor: "{colors.warning}"
    textColor: "{colors.warning-foreground}"
    rounded: "{rounded.md}"
    padding: "12px 16px"
  button-secondary:
    backgroundColor: "{colors.secondary}"
    textColor: "{colors.secondary-foreground}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
  button-destructive:
    backgroundColor: "{colors.destructive}"
    textColor: "{colors.destructive-foreground}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
  category-pill-active:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.primary-foreground}"
    rounded: "9999px"
    padding: "6px 12px"
  category-pill-inactive:
    backgroundColor: "{colors.card}"
    textColor: "{colors.muted-foreground}"
    rounded: "9999px"
    padding: "6px 12px"
---

# Design System: SAPI Smart POS

## 1. Overview

**Creative North Star: "The Counter That Counts"**

SAPI is built for the counter, not the boardroom. The palette has shifted from a cool indigo to a lush teal-green (hue 162), and the neutrals now carry a matching green undertone throughout: backgrounds, borders, and muted surfaces are all tinted toward the primary hue. The result feels grounded and earthy rather than corporate, which fits an Indonesian warung context far better than the previous cool-gray system did.

Light mode is the primary surface. A warung owner at their counter mid-morning, warm indoor light overhead, the cash drawer just opened: the screen should feel clear and alive, not fatiguing. Dark mode tokens are defined for evening use or user preference; they use the same green-tinted hue family at low lightness, keeping the palette coherent across modes.

The token system is defined as CSS custom properties in `resources/css/app.css` and exposed to Tailwind via `@theme inline`. All color decisions should reference these tokens (via `bg-primary`, `text-foreground`, etc.), not hardcoded values. This is the single source of truth.

**Key Characteristics:**
- Teal-green primary (hue 162) with green-tinted neutrals across every surface — cohesive, earthy, not corporate
- Separate `--primary` (interactive, darker) and `--brand` (expressive, lighter) tokens for the same green family
- Four semantic roles: success (cyan-teal), warning (amber), destructive (red-orange), each locked to one meaning
- Plus Jakarta Sans as the single humanist sans; JetBrains Mono for transaction codes, numeric data, and technical strings
- Computed radius scale from a 10px base: 6px / 8px / 10px / 14px
- Light mode default; dark mode defined and valid for evening or preference use

## 2. Colors: The Field Palette

Green-tinted from surface to text. Every neutral is pulled toward hue 150–160, so the primary accent reads as family rather than intrusion.

Note: All values are OKLCH. Approximate sRGB equivalents are given in parentheses for visual reference; the OKLCH values in `:root` are normative.

### Primary
- **Pandan** — `var(--primary)` oklch(0.58 0.128 162) (≈ #18956A): The main interactive accent. Navigation active states, primary action buttons in management views, input focus rings, price text in the POS grid. Named for the Indonesian herb: lush, medium-saturation teal-green with a cool lean.
- **Pandan Light** — `var(--brand)` oklch(0.72 0.15 162) (≈ #3DBA82): The expressive brand expression of the same green family. Slightly lighter and more saturated. Used for the SAPI wordmark, brand badges, and decorative accent moments where the darker primary would feel too heavy.
- **Pandan Light Foreground** — `var(--brand-foreground)` oklch(0.21 0.05 162): Dark green text on top of brand-tinted surfaces.
- **Pandan Foreground** — `var(--primary-foreground)` oklch(0.99 0.005 150): Near-white text on Pandan fill.

### Secondary (Semantic amplifiers)
- **Teal Signal** — `var(--success)` oklch(0.62 0.12 200) (≈ #0D9AB8): Completion and payment confirmation. The BAYAR button, paid status indicators, and success toasts. Hue 200 (cyan-teal) keeps it visually distinct from Pandan (hue 162) so the cashier can tell action-available from action-complete at a glance.
- **Kunyit** — `var(--warning)` oklch(0.78 0.14 75) (≈ #D9920A): Pending and open-bill states. Named for Indonesian turmeric: warm amber-yellow. The Open Bill button and all unpaid-transaction indicators.
- **Cabai** — `var(--destructive)` oklch(0.58 0.2 27) (≈ #D93B1A): Void, error, out-of-stock, and form validation failure. Named for Indonesian chili: vivid red-orange with high chroma. Appears only for states requiring immediate correction.

### Neutral
- **Background** — `var(--background)` oklch(0.993 0.004 150) (≈ #F8FBF9): Page background for owner dashboard and management views. Barely-there green tint at near-full lightness.
- **Card** — `var(--card)` oklch(1 0 0): Card and panel surfaces. Pure white at L=100% in light mode; in dark mode this shifts to oklch(0.21 0.013 160).
- **Muted** — `var(--muted)` oklch(0.962 0.007 150) (≈ #EFF5F1): Muted backgrounds, sidebar group labels, skeleton loaders.
- **Secondary Surface** — `var(--secondary)` oklch(0.95 0.008 150) (≈ #EBF2ED): Secondary button fill, chip inactive backgrounds, row hover states.
- **Border** — `var(--border)` oklch(0.9 0.008 150) (≈ #DDE8E2): All structural borders. Slightly green-tinted, softer than a neutral gray border.
- **Foreground** — `var(--foreground)` oklch(0.23 0.015 160) (≈ #283930): Primary text. Near-black with a green lean, never pure black.
- **Muted Foreground** — `var(--muted-foreground)` oklch(0.48 0.012 160): Secondary and helper text. Mid-range green-gray.

### Dark Mode
Dark mode tokens use the same hue family (150–160) at inverted lightness. Backgrounds drop to oklch(0.18 0.012 160), cards to oklch(0.21 0.013 160). The primary green lightens to oklch(0.64 0.13 162) to maintain contrast on dark surfaces. Apply via the `.dark` class on `<html>`.

### Named Rules
**The Single Hue Rule.** Every token in the palette shares the same green hue family (150–162), including the neutrals. New tokens introduced to the system must either land in this family or carry a clear semantic role (success/warning/destructive). Random hue additions break the coherence.

**The Semantic Lock Rule.** Teal Signal (success), Kunyit (warning), and Cabai (destructive) carry exactly one meaning each: complete, pending, and void/error. They are not available for decoration, variety, or brand expression.

**The Token Rule.** All color usage references CSS custom properties via Tailwind utilities (`bg-primary`, `text-foreground`, `border-border`). Hardcoded OKLCH or hex values in component files are prohibited except in `app.css` itself.

## 3. Typography

**Primary Font:** Plus Jakarta Sans (with ui-sans-serif, system-ui, sans-serif fallback)
**Mono Font:** JetBrains Mono (with ui-monospace, monospace fallback)

**Character:** Plus Jakarta Sans is a geometric humanist sans with friendly proportions and clear legibility at small sizes. It carries slightly more visual energy than Instrument Sans while remaining grounded — appropriate for a tool that needs to feel current without feeling playful. JetBrains Mono adds a precision layer: use it for transaction codes, receipt numbers, cash totals, stock quantities, and any string where digit ambiguity matters.

### Hierarchy
- **Display** (700 weight, 1.5rem/24px, 1.33 line-height): Page headings. One per view. "Dashboard", "Laporan Harian", "Transaksi".
- **Headline** (700 weight, 1.25rem/20px, 1.4 line-height): Section headings, SAPI brand mark in the sidebar.
- **Title** (600 weight, 0.9375rem/15px, 1.4 line-height): Card titles, group labels, modal headings.
- **Body** (400 weight, 0.875rem/14px, 1.5 line-height): Navigation items, form labels, transaction rows. The primary reading size.
- **Label** (500 weight, 0.75rem/12px, 1.33 line-height, 0.01em tracking): Badges, timestamps, filter pills, secondary metadata.
- **Mono** (400 weight, 0.8125rem/13px, 1.6 line-height): Transaction codes (e.g. TRX-20260525-001), receipt totals, numeric table cells, stock counts. Use wherever digit ambiguity matters.

### Named Rules
**The Mono Anchor Rule.** Transaction IDs, receipt numbers, and running totals in the cashier view use JetBrains Mono. It signals precision and differentiates machine-generated codes from human-entered text at a glance.

**The No Tiny Text Rule.** Nothing below 0.75rem (12px) in any functional context.

## 4. Elevation

Borders and tonal backgrounds do the structural work. Shadows add only interactive lift and modal depth. Every surface is flat at rest.

### Shadow Vocabulary
- **Ambient** (`0 1px 2px 0 rgba(0, 0, 0, 0.05)`): Cards and panels at rest. Navigation bars. The default resting elevation for any raised surface.
- **Lifted** (`0 4px 6px -1px rgba(0, 0, 0, 0.10), 0 2px 4px -2px rgba(0, 0, 0, 0.10)`): Product cards and interactive list items on hover. Signals interactivity, not permanent depth.
- **Floating** (`0 10px 15px -3px rgba(0, 0, 0, 0.10), 0 4px 6px -4px rgba(0, 0, 0, 0.10)`): Mobile sidebar overlay. Contextual panels over main content.
- **Modal** (`0 25px 50px -12px rgba(0, 0, 0, 0.25)`): All dialogs and modals. Reserved exclusively for the highest-interruption surfaces.

### Radius Scale
Base `--radius: 0.625rem` (10px). Derived values:
- **sm** (6px / `rounded-sm`): Small badges, tight chips, inline tags.
- **md** (8px / `rounded-md`): Buttons, inputs, navigation items.
- **lg** (10px / `rounded-lg`): Cards, panels, dropdowns.
- **xl** (14px / `rounded-xl`): Modals, large overlay surfaces, hero cards.

### Named Rules
**The Flat-at-Rest Rule.** No surface in its default state uses more than Ambient shadow. Lift responds to interaction (hover, drag, focus), not decoration.

## 5. Components

Components are confident and direct. Every button announces its purpose through color; every input responds immediately through the focus ring; the POS layout makes the next step obvious without explanation.

### Buttons
- **Shape:** 8px radius (rounded-md). Contained enough to feel reliable.
- **Primary (management views):** Pandan fill (`bg-primary`), primary-foreground text. Used for save, create, and confirm in owner views.
- **Pay (BAYAR):** Teal Signal fill (`bg-success`), success-foreground text. The dominant CTA in the POS cart. Uses `--success` (not `--primary`) so completion reads as a different signal from navigation.
- **Open Bill:** Kunyit fill (`bg-warning`), warning-foreground text. Paired with Pay at 1:2 width ratio.
- **Secondary:** Secondary surface fill (`bg-secondary`), secondary-foreground text. Cancel, dismiss, low-priority actions.
- **Destructive:** Cabai fill (`bg-destructive`), destructive-foreground text. Void, delete, irreversible actions only.
- **Hover:** Each button darkens by stepping down one OKLCH lightness unit (~0.06). No glow, no transform.
- **Focus:** `ring-2` using `--ring` (Pandan) with `ring-offset-2`. Consistent across all button variants.
- **Disabled:** `opacity-40 cursor-not-allowed`. No color shift.

### Chips (Category Filter Pills)
- **Active:** Pandan fill (`bg-primary`), primary-foreground text, pill radius (9999px).
- **Inactive:** Card fill (`bg-card`), muted-foreground text, border (`border-border`). Hover shifts border toward `--ring`.
- **Behavior:** Horizontal-scroll row, hidden scrollbar. Immediate filter on tap, no loading state.

### Cards / Containers
- **Corner style:** 10px radius (rounded-lg) for standard cards. 14px (rounded-xl) for modals and large surfaces.
- **Background:** `bg-card` with `border border-border`.
- **Shadow:** Ambient at rest. Lifted on hover for interactive cards.
- **Internal padding:** 20px for dashboard panels, 16px for compact list rows.
- **Nested cards:** Prohibited. Inner containers use `bg-muted` or `bg-secondary`, never a second bordered card inside an outer card.

### Inputs / Fields
- **Style:** `bg-card` or `bg-background` fill, `border-input` stroke (same value as `--border`), 8px radius.
- **Focus:** `ring-2 ring-ring` (Pandan). The ring is the only focus treatment; no border color shift needed beyond the ring.
- **Error:** `border-destructive ring-destructive`. Error message in Cabai (`text-destructive`) at body size, directly below the field.
- **Placeholder:** `text-muted-foreground`. Never carries required information.
- **Touch target:** Minimum `py-2.5` for at least 44px total tap height.

### Navigation
- **Owner Sidebar:** 256px fixed on desktop, slide-over on mobile with Floating shadow.
- **Active item:** `bg-primary/10` (Pandan at 10% opacity) background, `text-primary` text, 8px radius.
- **Default item:** `text-foreground/70`, transparent background. Hover: `bg-muted`.
- **Group labels:** `text-muted-foreground`, uppercase, 0.75rem, 600 weight. Collapsible.
- **POS Top Bar:** Sticky, `bg-card`, Ambient shadow. Brand mark in `text-primary`. Utility nav in `text-muted-foreground` with `hover:text-primary`.

### POS Product Card (Signature Component)
The most-tapped element in the system.

- **Shape:** `bg-card` fill, 2px `border-border` stroke, 10px radius.
- **Hover:** Border shifts to `--primary`, Lifted shadow appears.
- **Active press:** `scale(0.97)`. The only transform in the system.
- **Out of stock:** `opacity-60`, border fades to `--muted`, `cursor-not-allowed`. "Habis" badge in `bg-destructive/10 text-destructive`.
- **Price:** `text-primary`. The only Pandan-colored text in the card; the decision anchor for the cashier.

## 6. Loading States (Skeletons)

A page transition should show the *shape* of the page it is going to, not a thin bar at the top of the one it is leaving. The progress bar answers "something is happening"; a skeleton answers "here is what is coming, and where it will sit" — which is the difference between an app that feels slow and an app that feels busy.

Skeletons are not decoration bolted onto a slow page. They are the client half of a server decision: a section gets a skeleton **only** when its prop is deferred with `Inertia::defer()`, because a prop that already arrives in the first response has nothing to wait for.

### The Component Set
All skeletons live in `resources/js/Components/Skeleton/` and are built from one primitive. Import them directly; there is no barrel file.

- **`Skeleton.vue`** — the primitive: one `bg-muted` block with the pulse. Colour, radius scale and animation are defined here and nowhere else. Size comes from the caller's classes (`<Skeleton class="h-4 w-32" />`), never from a prop.
- **`SkeletonText.vue`** — a stack of line bars for a paragraph or a row's two or three lines. The closing line is short so the block reads as prose, not as a table.
- **`SkeletonPanel.vue`** — the card shell a deferred section loads inside: `bg-card`, border, xl radius, ambient shadow, plus a placeholder heading. `flush` moves the heading into its own bordered header for bodies that run edge to edge.
- **`SkeletonCard.vue`** — one card: `media` for the square-image product card, `icon` for the alert card. `borderWidth` copies the real card's stroke (the POS product card carries `border-2`).
- **`SkeletonGrid.vue`** — repeats a card across the same grid as the real content. The `columns` string is copied verbatim from the grid it replaces.
- **`SkeletonTable.vue`** — a real `<table>` of placeholder cells, so the browser distributes columns the way it will once rows arrive.
- **`SkeletonList.vue`** — the row list: leading tile, two lines, right-aligned value over meta.
- **`SkeletonChart.vue`** — bars at the chart's real height.

```vue
<Deferred data="recentTransactions">
    <template #fallback>
        <SkeletonPanel flush action label="Memuat transaksi terbaru…">
            <SkeletonList :rows="5" />
        </SkeletonPanel>
    </template>

    <!-- the real panel -->
</Deferred>
```

### Named Rules

**The Same-Space Rule.** A skeleton occupies the same box as the content it replaces. Copy the grid classes, the row padding, the chart height, the card's border width. A skeleton that is the wrong size trades one annoyance (waiting) for a worse one (the page moving under a finger already on its way down).

**The Deferred-Pair Rule.** Every skeleton pairs with an `Inertia::defer()` on the server, and every deferred prop gets a skeleton. Half of the pair is worse than neither: a deferred prop with no fallback flashes an empty panel, and a fallback with no deferred prop never renders.

**The First-Paint Rule.** What stays eager is the answer the screen exists to give, plus anything the user can act on while waiting: today's metrics on the dashboard, the three totals on the daily report, category chips and payment methods in the POS, the filter row on a transaction list. What gets deferred is everything that is only *read* — long lists, trend charts, alert aggregates, full catalogs.

**The One-Tone Rule.** Skeleton blocks are `bg-muted` and nothing else. No shimmer gradients, no sweeping highlight, no second grey. The pulse is Tailwind's `animate-pulse` (opacity only, never a layout property) and it is always paired with `motion-reduce:animate-none`.

**The Announced-Loading Rule.** Grey blocks say nothing to a screen reader, so every loading region carries `role="status"` with an Indonesian `sr-only` label ("Memuat transaksi terbaru…") and the blocks themselves are `aria-hidden`. Never leave a loading region silent — and never announce it twice: the **outermost** skeleton owns the label. `SkeletonPanel` always announces; the composed blocks announce only when they are given a `label`, which is why one nested inside a panel is passed none.

**The Progress Bar Rule.** The Inertia progress bar in `resources/js/app.js` stays until every page in the `[BL-037]` tracking table has its skeleton. It is the only signal the pages without one have; removing it first would take away the only feedback that exists rather than replacing it.

## 7. Do's and Don'ts

### Do:
- **Do** reference all colors via CSS custom properties and Tailwind utilities (`bg-primary`, `text-foreground`). Never hardcode OKLCH or hex values in component files.
- **Do** use `bg-success` exclusively for the BAYAR button and transaction-completion states.
- **Do** use `bg-warning` exclusively for open bill and pending states.
- **Do** use `bg-destructive` exclusively for void, error, and irreversible-action states.
- **Do** use JetBrains Mono for transaction codes, receipt numbers, and numeric data where digit precision matters.
- **Do** keep all touch targets at 44px minimum height on cashier-facing surfaces.
- **Do** use `bg-muted` or `bg-secondary` for inner container backgrounds rather than nesting a second bordered card.
- **Do** apply the `.dark` class to `<html>` for dark mode; all tokens shift automatically without component changes.
- **Do** keep `active:scale-[0.97]` on the POS product card. It is the only tactile feedback signal for a cashier tapping at speed.
- **Do** write copy in Indonesian: "Bayar", "Batal", "Simpan", "Hapus". Not translated from English defaults.
- **Do** build every loading state out of `resources/js/Components/Skeleton/`, and give the skeleton the same box as the content it replaces.
- **Do** pair each skeleton with an `Inertia::defer()` prop on the server, and each deferred prop with a skeleton.

### Don't:
- **Don't** use `border-left` or `border-right` greater than 1px as a colored accent stripe. Rewrite with `bg-primary/10` tint or a full border.
- **Don't** use gradient text (`background-clip: text` with a gradient). Every text token is a single solid OKLCH value.
- **Don't** use glassmorphism (backdrop-filter blur on translucent surfaces). Not appropriate for an operational tool.
- **Don't** hardcode color values in Vue components. The CSS token system exists precisely so palette changes in `app.css` propagate everywhere without component edits.
- **Don't** introduce new hues outside the green family (150–162) for neutral or decorative purposes. A "fresh" teal, a "vibrant" orange, or a "friendly" purple each fracture the single-hue discipline.
- **Don't** design in the generic enterprise SaaS template (Salesforce / HubSpot): cold blue-gray grids, overpadded empty states, dashboards that feel like training material.
- **Don't** use consumer fintech visual language (GoPay / OVO): gradient fills, playful rounded shapes, cheerful consumer-app energy.
- **Don't** reproduce legacy POS density (Moka POS circa 2018): cramped tables, sub-12px text, heavy shadows on every surface.
- **Don't** apply the Western startup cream aesthetic (Notion / Linear): off-white backgrounds, editorial typographic sidebars, visual language that reads as imported rather than native.
- **Don't** nest cards inside cards. A second border-plus-shadow surface inside an outer card doubles visual weight without adding information.
- **Don't** animate layout properties (width, height, padding, margin). Transition only opacity, transform, background-color, border-color, and box-shadow.
- **Don't** write a one-off `animate-pulse` block inside a page. If the existing skeleton components do not cover the shape, extend them — a second loading vocabulary is how the first one stops being a system.
- **Don't** dress a skeleton up: no shimmer sweep, no gradient, no spinner on top of it, and no second grey tone beside `bg-muted`.
- **Don't** use random widths or heights in a skeleton. A placeholder that reshuffles on every render draws the eye to itself instead of to the page that is loading.
