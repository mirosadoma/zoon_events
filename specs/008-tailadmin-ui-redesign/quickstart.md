# Quickstart: TailAdmin Dashboard UI Redesign

Validation scenarios proving the redesign is applied, consistent, permission-aware,
responsive, RTL-ready, and — critically — that redesigned actions call the real backend.
This is a run/validation guide; implementation detail lives in `tasks.md`.

## Prerequisites

- Phases 0–4 + feature 006 present; `composer install`, `npm install` done.
- A tenant with seeded events/orders/attendees/credentials and test users covering
  different permission sets (organizer, event staff, badge staff, ACS operator, admin).

## Setup

```powershell
npm install
npm run dev          # or: npm run build
php artisan serve    # existing app; no backend change
```

## Scenario 1 — Shell + design system (US1)

1. Sign in; confirm the TailAdmin-style shell: grouped collapsible sidebar (Main, Event
   Operations, On-site Operations, Access Control, Administration), topbar (search,
   notifications, tenant + role indicators, user menu), breadcrumbs, page header.
2. Sign in as a limited-permission user → confirm governed sidebar items/actions are
   hidden.
3. Resize to tablet then mobile → sidebar becomes a drawer, tables scroll/convert to
   cards, forms stack, no horizontal page scroll.
4. Switch to Arabic → layout mirrors to RTL; dates/numbers locale-aware.
5. Trigger a list's loading, empty, and error states → shared skeleton/empty/error render.

## Scenario 2 — Overview + events (US2)

1. Overview shows metric cards + recent events/orders/scans/audit tables (skeletons
   while loading).
2. Events list uses the shared table (search/filter/pagination/status badges/row menu).
3. Create an event → submit button shows spinner + disables; **confirm `POST
   /api/v1/tenant/events` is called** (network tab); on success the event appears.
4. Open event detail tabs; publish via confirm modal → **`POST .../publish` called**;
   status reflects the real result after reload.
5. Add a registration field, a ticket type, and a price tier → each calls its real
   endpoint and reflects the result; validation errors render below fields.

## Scenario 3 — Orders / attendees / credentials (US3)

1. Filter orders/attendees; open order/attendee/credential detail (consistent cards +
   audit timeline).
2. Revoke a credential with a required reason → **`POST .../credentials/{id}/revoke`
   called**; a forced failure leaves status unchanged and shows an error (no cosmetic
   success). Reissue → `.../reissue` called.

## Scenario 4 — Wallet / scanning / check-in (US4)

1. Scanner: submit valid + revoked codes → large accepted/rejected panels from the real
   `POST .../scans` response; button loader + duplicate-guard.
2. Check-in dashboard metric cards + latest scan events render; scan-events table filters.

## Scenario 5 — On-site + ACS + admin (US5–US7)

1. Kiosks list/detail + kiosk mode (fullscreen) render in the new language; badge print
   jobs reprint via reason modal → real endpoint; manual desk search → real lookup.
2. ACS overview cards; create zone/lane/rule → real `POST .../acs/...` endpoints; access
   logs + gate health render.
3. Users/roles/tenant-settings/audit/reports/profile use shared components + permission-
   aware controls; a missing-API surface shows a documented placeholder.

## Gates

```powershell
npm run lint
npm run typecheck
npm run test
npm run build
composer quality   # docs check + phase-boundary check (Phase 5 stays absent)
```

**Done when**: all five scenarios pass, redesigned actions call real endpoints and
reflect real results, no TailAdmin demo content/branding remains, Arabic/RTL and
responsive checks pass, and all gates are green.
