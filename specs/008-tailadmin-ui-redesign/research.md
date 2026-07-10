# Research: TailAdmin Dashboard UI Redesign

All Technical Context choices are resolved below. There are no unresolved
[NEEDS CLARIFICATION] items; this is a presentation redesign over an accepted stack.

## Decision 1 — Restyle in place; do not fork the component set

- **Decision**: Evolve the existing tokens in `resources/css/app.css` and the existing
  feature-006 shared components (`DashboardLayout`, `components/{layout,tables,forms,
  status,modals,loaders,feedback}`) to the TailAdmin visual language. No parallel
  component library, no second layout.
- **Rationale**: 006 already ships a single shell + component system and a design-system
  doc; a fork would create drift and violate "one system" (constitution white-label/
  localization + the brief's §15 acceptance). Restyling in place keeps every page's
  props/permissions intact.
- **Alternatives**: green-field TailAdmin app or a `tailadmin/` parallel set — rejected
  (duplication, double maintenance, parity risk).

## Decision 2 — Inertia + existing fetch actions, not a new `/src/api` REST client

- **Decision**: Reads stay Inertia props; actions reuse the established
  `fetch('/api/v1/...')` pattern with `credentials:'include'`, `X-Tenant-ID`, and
  `Idempotency-Key` (as in `pages/tenant/manual-desk/Desk.tsx`). The brief's
  `/src/api/*.ts` layer is **not** adopted.
- **Rationale**: matches 006 Decision 1 and avoids a second auth/tenant path; the
  same-origin session already carries auth. A parallel client would duplicate error/
  tenant handling.
- **Alternatives**: full `/src/api` typed client — rejected (redundant with Inertia +
  fetch; parity cost). A thin shared `apiFetch()` helper MAY be extracted to centralize
  headers/error mapping (FR-017) without becoming a full SPA client.

## Decision 3 — Real permission catalog governs UI visibility

- **Decision**: Map the brief's illustrative permission names to the executable catalog
  (`docs/standards/permission-catalog.md`). Full table in `rbac-ui-map.md`. Examples:
  `events.create/update`→`event.manage`, `events.publish`→`event.publish`,
  `credentials.revoke`→`credential.revoke`, `scan.perform`→`checkin.scan.submit`,
  `checkin.perform`→`checkin.desk.perform`, `acs.manage`→`acs.configure`,
  `users.manage`→`membership.manage`/`platform.user.manage`,
  `roles.manage`→`role.manage`, `tenant.manage`→`tenant.view`+`configuration.view`.
- **Rationale**: CI compares the seeder to the catalog doc; using illustrative names
  would break RBAC gating and the phase-boundary/docs checks.
- **Alternatives**: keep brief names — rejected (non-executable).

## Decision 4 — Action wiring is in scope (close cosmetic-only gaps)

- **Decision**: Redesigned action controls must call the existing endpoint and reflect
  the real result (FR-011/SC-005). This explicitly fixes the 006-review findings where
  credential revoke/reissue, event publish/cancel, and event/ticket/tier create only
  mutated local state or closed a modal.
- **Rationale**: the brief's §9.3 submit-loader list and §16.3 flow tests (create event,
  revoke credential, scan, create ACS zone/lane/rule) require working actions;
  constitution CR-003 forbids reporting an audited action complete without a backend
  call.
- **Alternatives**: cosmetic-only restyle — rejected (fails flow tests + CR-003, and
  re-buries the known gap). Endpoints already exist (e.g. `POST /api/v1/tenant/events/
  {id}/publish`, `.../credentials/{id}/revoke`), so wiring is frontend-only.

## Decision 5 — TailAdmin is a visual reference only

- **Decision**: No TailAdmin npm package, licensed asset, external font, or CDN.
  Tokens/components are Zonetec-owned; TailAdmin informs spacing, structure, and
  component anatomy. All demo content/branding removed.
- **Rationale**: constitution deployment parity (no cloud-only/licensed dependency,
  no external runtime fonts — already stated in the design-system doc) and the brief's
  §3 rules.
- **Alternatives**: vendoring TailAdmin templates — rejected (license/asset risk,
  demo-content bleed).

## Decision 6 — Additive shared components

- **Decision**: Add the pieces the brief needs that 006 lacks: `NotificationDropdown`,
  global `SearchCommand`, `StatCard`/`MetricCard` (richer than existing cards),
  table `ActionDropdown` (row action menu), collapsible `SidebarSection`, and
  `DetailsModal`. Everything else is a restyle of an existing component.
- **Rationale**: `component-map.md` marks each EXISTS/RESTYLE/NEW so scope is explicit.
- **Alternatives**: build all-new — rejected (wasteful; most already exist).

## Decision 7 — Light-first, theme-ready; notifications/search degrade gracefully

- **Decision**: Keep the existing light/dark token system; expose the theme toggle only
  where already supported. The topbar notification dropdown and global search surface
  existing backend data where available and otherwise render an empty/placeholder state,
  with the gap logged in `missing-api-requirements.md`.
- **Rationale**: avoids inventing a notifications/search backend (out of scope, no new
  business module); brief marks both as optional/where-supported.
- **Alternatives**: build a notifications feed backend — rejected (new capability,
  Phase-boundary/constitution violation).

## Decision 8 — Localization/RTL/accessibility remain acceptance gates

- **Decision**: Every restyled surface keeps Arabic/English strings in
  `locales/{en,ar}.ts`, logical-property layout, visible focus, reduced-motion support,
  and axe-clean markup; RTL and responsive checks are part of the test plan.
- **Rationale**: matches the existing accessibility bar and the brief §11.
- **Alternatives**: defer a11y/RTL to a later pass — rejected (regression risk;
  constitution gate).
