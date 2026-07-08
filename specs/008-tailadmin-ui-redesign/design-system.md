# Design System (brief §18 deliverable)

TailAdmin-referenced, Zonetec-owned design system layered on the existing
Tailwind 4 CSS-config in `resources/css/app.css`. No TailAdmin package, licensed asset,
external font, or CDN. Light-first, dark-ready, RTL-first (logical properties).

## Tokens (CSS custom properties in `app.css`)

- **Surfaces**: `--surface` (page), `--surface-elevated` (cards), `--border`.
- **Ink**: `--ink` (primary text), `--muted` (secondary).
- **Brand/accent/focus**: `--brand`, `--accent`, `--focus-ring` (Zonetec brand).
- **Status palette** (new, centralized): success / warning / danger / info / neutral /
  emphasis — each with light + dark values feeding `StatusBadge` and alerts.
- **Radius**: control (`~lg`), card (`~xl`); consistent across tables/modals.
- **Shadow**: subtle card + table elevation; heavier for popovers/modals.
- **Typography**: Instrument Sans; defined size/weight scale for h1–h4, body, caption.
- **Spacing**: consistent page padding, card padding, table cell rhythm, form gap.

Preserve: `.dark` variant, `:focus-visible` ring, `.skip-link`, reduced-motion block,
logical (start/end) properties for RTL.

## Component anatomy (TailAdmin-referenced)

- **Shell**: fixed grouped sidebar (collapsible sections, icons, active highlight,
  mobile drawer) + topbar (sidebar toggle, global search, notifications, tenant + role
  indicators, user menu, optional language/theme toggle) + breadcrumbs + page header +
  content.
- **Cards**: rounded, soft border, subtle shadow; `StatCard`/`MetricCard` with icon +
  label + value + delta/description + optional status.
- **Tables**: rounded container, soft row borders, toolbar (search + filters),
  sortable headers where the API supports it, status-badge cells, row `ActionDropdown`,
  pagination footer; skeleton + empty + error states.
- **Forms**: label + required indicator + control + inline `ValidationError` below;
  disabled/loading states; `SubmitButtonWithLoader` (spinner, disabled, duplicate-guard).
- **Badges**: small/medium; status→variant per data-model §4; AR/EN labels.
- **Modals**: `ConfirmModal` (destructive/audited), `ReasonModal` (reason required),
  `DetailsModal` (read-only detail); focus-trapped, ESC/overlay close, RTL-aware.
- **Loaders/states**: global route loader (brand icon), page/table/card/form skeletons,
  purpose-specific empty states, clear error states (load failed / forbidden / not found
  / server / network / validation).

## Responsive breakpoints

- Desktop (admin): full sidebar + multi-column.
- Tablet (event ops): condensed sidebar, 1–2 column, scrollable tables.
- Mobile (scanner/kiosk/manual-desk): sidebar drawer, stacked forms, tables scroll or
  convert to cards; no horizontal page scroll.

## Accessibility & localization

Visible focus, keyboard operability, reduced-motion, axe-clean markup; every string via
`react-i18next` (`locales/{en,ar}.ts`); RTL via logical properties; locale-aware dates/
numbers/currencies (`useLocale`, `formatters`, `formatMoney`).
