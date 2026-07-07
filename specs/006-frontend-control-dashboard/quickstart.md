# Quickstart: Frontend Control Dashboard

Validation/run guide for the Phase 6 consolidation dashboard. Implementation
detail lives in `tasks.md`; contracts and data shapes are in `contracts/` and
`data-model.md`.

## Prerequisites

- PHP 8.3, Node 20+, MySQL 8.4 (existing project requirements).
- Accepted Phase 0–4 backend present and migrated (`php artisan migrate`).
- Dependencies installed: `composer install`, `npm install`.
- A tenant with seeded roles/permissions (`php artisan db:seed`) and at least one
  published event with attendees/credentials for end-to-end checks.

## Run

```bash
composer run dev        # concurrently runs `php artisan serve` + `npm run dev` (Vite)
```

Open the app, sign in at `/login` with a seeded workforce user. The overview loads
at `/`.

## Validate by user story (each independently testable)

- **US1 Sign-in + RBAC shell**: sign in; confirm sidebar shows only permitted items;
  overview metrics render with skeletons first. Sign in as a user missing
  `event.manage` and confirm the create-event action is hidden. Unauthenticated
  visit to a tenant route redirects to `/login`.
- **US2 Events/registration/ticketing**: create an event (draft), open its tabbed
  detail, add a registration field, create a ticket type and a price tier; confirm
  each appears with correct status and publish is gated by `event.publish`.
- **US3 Orders/attendees/credentials**: filter orders and open an order; open an
  attendee; revoke a credential (reason required) then reissue it — both gated and
  confirmed via modal.
- **US4 Wallet/scan/check-in**: open wallet passes; on `/tenant/events/{id}/scanner`
  submit a valid code (accepted) and a revoked code (rejected + reason); confirm the
  check-in dashboard counters and scan-events list update.
- **US5 Kiosk/badge/manual-desk**: register a kiosk; open kiosk detail; on manual
  desk search an attendee and perform a reprint (reason required); confirm a badge
  print job appears.
- **US6 ACS**: as `acs.configure`, create a zone, a lane in it, and a rule; confirm
  access-logs and gate-health render; emergency control appears only for
  `acs.emergency.manage`.
- **US7 Admin/reports/audit**: as an admin, list/filter users and toggle activation;
  filter audit logs; open an event report and confirm available metrics render (any
  missing metric shows a documented placeholder, not an error).

## Automated checks (must pass)

```bash
npm run lint          # eslint --max-warnings=0
npm run typecheck     # tsc --noEmit
npm run test          # vitest unit/integration
npx playwright test   # browser/E2E + axe (12 journeys, Arabic/RTL, responsive)
composer quality      # Pint, PHPUnit, OpenAPI sync/lint, docs & phase-boundary checks
```

## Expected outcomes

- All 12 E2E journeys (`test-plan.md` §4) pass, including Arabic/RTL and tablet
  layout.
- Every list page shows loading/empty/error/forbidden states; every form shows a
  scoped submit loader and prevents duplicate submits.
- No cross-tenant props; no credential/secret material in props or HTML.
- Any `api-integration-map.md` GAP screen renders its placeholder cleanly and the
  gap is recorded there and in `spec.md`'s Missing Backend API Requirements.
