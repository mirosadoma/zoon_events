# Test Plan (source-plan §23 deliverable)

Reuses the existing stack: **Vitest + React Testing Library** (unit/integration),
**Vitest jsdom + axe-core** (browser-simulated journey/a11y/RTL/responsive), and
**PHPUnit** (AdminConsole controller/ViewModel + `dashboard.permission` route
authorization). Wired into existing gates: npm `test`/`typecheck`/`lint`, and
`composer quality`. Every user-visible surface must pass Arabic/English + RTL and
axe checks (CR-007/CR-009).

## 1. Unit (Vitest/RTL) — `resources/js/__tests__/**`

- **Sidebar/nav visibility**: given a `can` map, `Sidebar` renders exactly the
  permitted items; hides `acs.configure`/`audit.view`/`event.manage`-gated entries
  when absent.
- **PermissionGate**: hides wrapped action when key missing; shows when present.
- **StatusBadge**: renders correct label/token for each status across event/order/
  payment/credential/wallet/scan/kiosk/badge/ACS-lane sets.
- **Form validation + loaders**: `SubmitButtonWithLoader` disables on submit,
  prevents duplicate submit, shows spinner; field errors render from envelope.
- **States**: `EmptyState`/`ErrorState`/`ForbiddenState` render for list/detail.
- **Localization/RTL**: labels resolve from `en`/`ar`; `formatMoney`/date formatting
  is locale-aware; direction flips via logical properties.

## 2. Integration (Vitest/RTL + Inertia prop mocks)

- Login flow (success + invalid credentials).
- Events list load (rows, filters, empty state).
- Create-event flow (draft save, validation).
- Ticket-type creation flow.
- Attendee-detail load (all sections populate).
- Credential revoke flow (ReasonModal required → status becomes revoked).
- Credential reissue flow (new credential linked to prior).
- QR scan accept + QR scan reject (large result, reason on reject, duplicate-submit
  guard).
- Manual-desk search flow.
- ACS rule creation flow.

## 3. Backend feature (PHPUnit) — `tests/Feature/AdminConsole/**`

- Each new controller returns correctly tenant/permission-scoped Inertia props.
- `dashboard.permission:<key>` returns 403 without the permission and 200 with it,
  for every new route (users, roles, tenant-settings, audit-logs, price-tiers,
  order/attendee/credential detail, wallet detail, scan-events, kiosk detail,
  kiosk mode, badge print jobs, ACS zones/lanes/rules/access-logs, reports).
- Zero cross-tenant props: a user in tenant A never receives tenant B records.

## 4. Browser-simulated journeys (Vitest jsdom + axe-core) — 12 critical journeys

Maps to spec User Story acceptance scenarios and source-plan §20.3:

1. Admin logs in and views dashboard overview.
2. Organizer creates an event.
3. Organizer configures a registration form field.
4. Organizer creates a ticket type.
5. Organizer views orders and attendees.
6. Authorized user revokes a credential (with reason).
7. Authorized user reissues a credential.
8. Staff scans a valid QR → accepted.
9. Staff scans a revoked QR → rejected with reason.
10. Badge staff prints a badge.
11. Manual desk searches an attendee.
12. ACS operator creates a zone, a lane, and a rule.

Each run also asserts: axe-clean, keyboard operability, Arabic/RTL rendering,
and tablet-width responsive layout without horizontal scroll.

## 5. Non-functional checks

- **Performance**: overview reachable < 30 s (SC-001); scan result < 3 s (SC-005);
  list/detail render skeletons immediately.
- **RBAC matrix**: for each permission key, a with/without test proving action
  visibility and server enforcement diverge only in UX, never in authorization.
- **Missing-API safety**: each `api-integration-map.md` GAP screen renders its
  placeholder/empty state without console errors when the projection is absent.

## 6. Gate wiring

- `npm run test`, `npm run typecheck`, `npm run lint` (max-warnings=0), `vite build`.
- `composer quality` (Pint, PHPUnit, OpenAPI sync/lint for any added read
  projection, docs check, phase-boundary check).
- CI blocks merge on any failure (Constitution VII).
