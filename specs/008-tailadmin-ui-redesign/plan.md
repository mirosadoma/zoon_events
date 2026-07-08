# Implementation Plan: TailAdmin Dashboard UI Redesign

**Branch**: `008-tailadmin-ui-redesign` | **Date**: 2026-07-08 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/008-tailadmin-ui-redesign/spec.md`

**Product Phase**: Frontend UI Redesign Phase (design-system standardization over
completed Foundation + Phases 1–4). No new backend phase; Phase 5 out of scope.

**Deployment Modes**: SaaS and on-premise

## Summary

Restyle and standardize the existing Zonetec Inertia/React dashboard onto one
professional, enterprise-grade design system (TailAdmin as the visual reference),
**in place**, without changing backend business logic, rebuilding completed phases,
or starting Phase 5. The work evolves the existing design tokens
(`resources/css/app.css`) and the existing shared component families (built in
feature 006 — `DashboardLayout`, `StatusBadge`, `DataTable`, forms, modals, loaders,
feedback states) to the TailAdmin visual language, adds the few missing shared pieces
(notification dropdown, global search, richer stat/metric cards, table row action
menu, collapsible sidebar groups), and re-skins every completed-phase page onto that
system.

Critically, the redesign is **not cosmetic-only**: per FR-011/SC-005 (and to close the
gaps found in the 006 review) every redesigned action control must invoke the existing
`/api/v1/**` backend and reflect the real success/failure — no local-state "fake
success". No new `/src/api` REST client is introduced; the existing Inertia prop path
plus the established `fetch('/api/v1/...')` action pattern (with `Idempotency-Key` and
tenant context) is reused. RBAC visibility keys map to the **real permission catalog**
(`docs/standards/permission-catalog.md`), not the illustrative names in the brief.
Backend domain modules, contracts, and controls are untouched except where a genuinely
missing read/write projection is documented under Missing Backend API Requirements and
the surface ships a placeholder.

## Technical Context

**Language/Version**: TypeScript 5.9 (React 19) on the existing Laravel 13 Inertia
surface; no language change.

**Primary Dependencies**: `@inertiajs/react` 3.6, React 19, Tailwind CSS 4
(CSS-config in `resources/css/app.css`, logical properties, `.dark` variant),
`lucide-react` icons, `react-i18next`, `axios`/`fetch` for actions. Reuses existing
hooks (`useLocale`, `useTheme`, `useToast`), libs (`navigation`, `tenant-navigation`,
`formatters`, `formatMoney`, `checkin-polling`, `acs-polling`), and locales. No new
runtime dependency and no TailAdmin package/licensed asset is added — TailAdmin is a
visual reference only.

**Storage**: None. Presentation-only phase; all data flows through existing Inertia
props and existing REST endpoints. No table, migration, or persisted frontend state.

**Testing**: Vitest + React Testing Library + `@testing-library/jest-dom` +
`axe-core` for component/page/flow tests and Arabic-RTL/responsive checks; existing
`npm run lint` (ESLint `--max-warnings=0`), `npm run typecheck` (`tsc --noEmit`),
`vite build`, and `composer quality` (docs check, phase-boundary check) gates.

**Target Platform**: Same-origin Laravel web deployment (native Windows or Linux; no
Docker) serving the Inertia React dashboard for SaaS and on-premise; desktop-first
admin, tablet-friendly operations, mobile-usable scanner/kiosk-mode/manual-desk.

**Project Type**: Web application — frontend redesign/consolidation layer over the
accepted Phase 0–4 backends and the feature-006 dashboard, extending only
`resources/js`, `resources/css`, and thin `AdminConsole` presentation where a page
needs an existing prop it does not yet receive.

**Performance Goals**: Returning operator reaches the redesigned overview in under 30 s
(SC-009); list/detail pages render skeletons immediately and hydrate on Inertia prop
load; scanner remains fast/legible; no new streaming/polling infrastructure beyond the
existing bounded pollers.

**Constraints**: No backend business-logic change; no completed-phase rebuild; no
Phase 5 feature; no Docker; no parallel/forked component set (restyle in place); no new
`/src/api` client layer; redesigned actions must reflect real backend results (never
cosmetic-only); RBAC visibility is UX-only with the server authoritative; every surface
Arabic/English + RTL/LTR and axe-clean; tenant scope from server context only.

**Scale/Scope**: Existing Phase 0–4 target (1,000 tenants, up to 100,000 attendees/
event). ~35 pages restyled across 7 user-story slices, one shell, ~10 shared component
families evolved, ~5 new shared components, the real permission→page map, and the
action-wiring pass for the currently-cosmetic controls.

## Constitution Check

*GATE: PASS before research; PASS after design.*

| Gate | Design evidence | Status |
|---|---|---|
| API-first | Consumes existing versioned contracts; adds no bypass path. Redesigned actions call the same `/api/v1/**` operations the backend already exposes; any missing projection is logged in `api-integration-map.md`/`missing-api-requirements.md`, not invented in the UI. | PASS |
| Tenant isolation | Every page renders Inertia props resolved from server tenant context; no client-supplied tenant id; tenant-mismatched responses treated as errors. Restyle changes markup, not scoping. | PASS |
| RBAC and auditability | Navigation/actions gate on the real permission catalog via the shared `can` map/`PermissionGate`; server middleware stays authoritative. Wiring cosmetic actions to real endpoints *restores* audit fidelity (CR-003) — the UI no longer reports success without a backend call. | PASS |
| Credential security | UI shows lifecycle/status and submits scan payloads for server validation; never signs/mints/stores secrets. Rejected results render clearly. Unchanged by restyle. | PASS |
| Deployment parity | Same-origin Inertia surface identical in SaaS/on-premise; no cloud-only dependency; unavailable data degrades to error/empty states. No Docker. | PASS |
| GCC/KSA and PDPL | UI shows only backend-returned, tenant/permission-scoped data; persists no personal data beyond session/cache; identity status shown as a badge only (no Phase 5 build). | PASS |
| White-label/localization | Shell and public-style surfaces honor tenant branding; all content Arabic/English via `react-i18next` with logical-RTL Tailwind and locale-aware formatting; branding configuration-driven, no forks. | PASS |
| Modularity/adapters | Only `resources/js`, `resources/css`, and thin `AdminConsole` presentation change; no domain module internals touched; no hardware/vendor adapter added. | PASS |
| Automated tests | Component (nav/`PermissionGate`/`StatusBadge`/`DataTable` states/submit-loader/modals), page render, and critical flow tests incl. real action-call assertions, plus Arabic/RTL and responsive checks per CR-009/`test-plan.md`. | PASS |
| Phased delivery | Frontend redesign over accepted Phase 0–4; adds no product capability; keeps Phase 5 surfaces absent (phase-boundary check); must not weaken existing contracts/controls. | PASS |

No constitution exception required. The only backend touches are thin, documented
presentation props for an existing capability (recorded in `api-integration-map.md`),
never a new business module or rule change.

## Research Decisions

Detailed in [research.md](research.md):

1. **Restyle in place, no fork.** Evolve `resources/css/app.css` tokens and the
   existing 006 shared components to the TailAdmin visual language; do not create a
   parallel component set or a second layout.
2. **Inertia, not a new `/src/api` SPA client.** Reuse Inertia props for reads and the
   established `fetch('/api/v1/...')` pattern (with `Idempotency-Key`/tenant context)
   for actions; the brief's `/src/api/*.ts` layer is not adopted (parity + single auth
   path).
3. **Real permission catalog governs.** Map the brief's illustrative keys
   (`events.create`, `credentials.revoke`, `scan.perform`, `acs.manage`, …) to the
   executable catalog (`event.manage`, `credential.revoke`, `checkin.scan.submit`,
   `acs.configure`, …); full map in `rbac-ui-map.md`.
4. **Action wiring is in scope.** Redesigned action controls must call existing
   endpoints and reflect real results (FR-011/SC-005), closing the 006 cosmetic-only
   gaps (credential revoke/reissue, event publish/cancel, event/ticket/tier create).
5. **TailAdmin as reference only.** No TailAdmin package, licensed asset, external
   font, or CDN; tokens/components are Zonetec-owned. Demo content removed.
6. **Additive shared components.** Add `NotificationDropdown`, global `SearchCommand`,
   `StatCard`/`MetricCard` variants, table `ActionDropdown`, collapsible
   `SidebarSection` — additive to the existing library, documented in
   `component-map.md`/`design-system.md`.
7. **Light-first, theme-ready.** Keep the existing light/dark token system; a theme
   toggle is surfaced only where already supported.
8. **Localization/RTL/a11y as acceptance gates.** Every restyled surface keeps
   Arabic/English strings, logical-property layout, visible focus, and axe-clean markup.

## Architecture and Ownership

### Frontend (`resources/js`, `resources/css`) — the bulk of the work

- **Design tokens**: extend the CSS custom properties + component utility classes in
  `resources/css/app.css` to the TailAdmin scale (color/radius/shadow/typography/
  spacing), preserving `.dark` and logical-RTL behavior.
- **Shell**: evolve `layouts/DashboardLayout.tsx` and `components/layout/*`
  (`Sidebar`, `Topbar`, `Breadcrumbs`, `PageHeader`, `PageContent`) to the TailAdmin
  layout; add grouped/collapsible `SidebarSection`, `NotificationDropdown`,
  `SearchCommand`, `UserMenu`.
- **Shared components**: restyle `components/{tables,forms,status,modals,loaders,
  feedback,cards}` to the new language; add the additive pieces from Decision 6.
- **Pages**: restyle every page in FR-008 and wire currently-cosmetic actions to the
  existing endpoints; keep routes, props, and permissions.

### Backend (`AdminConsole`, thin, only if a prop is missing)

Where a redesigned page needs an existing datum the current controller/ViewModel does
not yet pass (e.g., a metric already computed elsewhere, an identity-status badge value
the backend already returns), add the thin prop via the existing ViewModel — no new
domain module, query into another module's persistence, or business rule. Genuine gaps
go to `missing-api-requirements.md`.

### Consumed unchanged

All Phase 0–4 domain modules and their `/api/v1/**` action/read endpoints, the real
permission catalog, tenant context, audit, and localization.

## Project Structure

### Documentation (this feature)

```text
specs/008-tailadmin-ui-redesign/
├── spec.md
├── plan.md
├── research.md
├── data-model.md              # design-system artifacts (tokens, component inventory, status map, nav manifest)
├── quickstart.md
├── contracts/
│   └── ui-contract.md         # route → real-permission → states → action endpoint
├── frontend-routes.md         # brief §18 deliverable
├── component-map.md           # brief §18 deliverable (EXISTS / RESTYLE / NEW)
├── design-system.md           # brief §18 deliverable (tokens + component specs)
├── api-integration-map.md     # brief §18 deliverable (screen → existing endpoint / gap)
├── rbac-ui-map.md             # brief §18 deliverable (illustrative → real permission keys)
├── test-plan.md               # brief §18 deliverable
├── missing-api-requirements.md# brief §18 deliverable (running gap register)
├── checklists/requirements.md
└── tasks.md                   # created later by /speckit.tasks
```

### Source Code (repository root)

```text
resources/css/app.css                         # design tokens evolved to TailAdmin scale
resources/js/
├── layouts/DashboardLayout.tsx               # restyled shell
├── components/
│   ├── layout/{Sidebar,SidebarSection,Topbar,UserMenu,NotificationDropdown,SearchCommand,Breadcrumbs,PageHeader,PageContent}.tsx
│   ├── cards/{StatCard,MetricCard,InfoCard,...}.tsx    # new/expanded
│   ├── tables/{DataTable,TableToolbar,ActionDropdown,Pagination,...}.tsx
│   ├── forms/**  status/StatusBadge.tsx  modals/**  loaders/**  feedback/**   # restyled
├── pages/**                                  # every FR-008 page restyled + actions wired
├── hooks/{useLocale,useTheme,useToast}       # reused
├── lib/{navigation,tenant-navigation,formatters,formatMoney,checkin-polling,acs-polling}
└── locales/{en,ar}.ts                        # any new UI strings
app/Modules/AdminConsole/**                   # thin prop additions only where needed
routes/web.php                                # unchanged routing; wiring is client→existing /api/v1
tests/ (frontend) resources/js/__tests__/**   # component/page/flow/RTL/responsive
docs/standards/dashboard-design-system.md     # updated with the new system
```

**Structure Decision**: Keep the single Laravel + Inertia/React deployment. Nearly all
work lands in `resources/js` + `resources/css`, with thin `AdminConsole` prop additions
only where a page needs an existing datum. No separate SPA, no `/src/api` client, no new
domain module, no backend business-rule change, no Docker.

## Cross-Cutting UX Strategy

- **States**: every list has skeleton/empty/error; every detail has card skeletons +
  error — reuse/extend `components/feedback` + `components/loaders`.
- **Forms/actions**: submit disables the control, prevents duplicate submits, shows a
  scoped loader, surfaces field-level validation from the response envelope, toasts
  success, and **calls the real endpoint**; sensitive actions route through
  `ConfirmModal`, reason-required through `ReasonModal`.
- **Status**: one `StatusBadge` family covering all module statuses (event/order/
  payment/credential/wallet/scan/kiosk/badge/ACS) with consistent tokens + AR/EN labels.
- **Localization/RTL**: `react-i18next` strings, logical Tailwind properties,
  `useLocale`/`useTheme`, locale-aware formatting.
- **Responsive**: sidebar → drawer, tables scroll/convert to cards, forms stack, no
  horizontal page scroll.

## Testing and Documentation Gates

- **Component**: sidebar/nav visibility per `can`; `PermissionGate` hide; `StatusBadge`
  per status; `DataTable` rows/empty/loading/error; `SubmitButtonWithLoader`
  disable/duplicate-guard; `ConfirmModal`/`ReasonModal`.
- **Page**: each restyled page renders with skeleton/empty/error and AR/RTL.
- **Flow**: login, navigate, create event, create ticket type, view attendee, revoke +
  reissue credential (assert the real endpoint is called, not just a callback), scan,
  manual desk, create ACS zone/lane/rule.
- **Quality**: ESLint `--max-warnings=0`, `tsc --noEmit`, Vite build, `composer
  quality` (docs check, phase-boundary check keeps Phase 5 absent).

Documentation deliverables: the seven brief §18 maps above, plus updates to
`docs/standards/dashboard-design-system.md`.

## Post-Design Constitution Re-check

Presentation-only changes: tenant scope server-resolved; RBAC mirrored from the
authoritative catalog and *strengthened* by removing cosmetic-only successes; no
credential secret exposure; Arabic/English + RTL and accessibility as gates;
SaaS/on-premise parity with no cloud-only dependency; Phase 5 kept absent by the
phase-boundary check. Backend touches limited to thin, documented presentation props.

**Result**: PASS. No complexity exception or governance waiver required.

## Complexity Tracking

No constitution violations or justified complexity exceptions.
