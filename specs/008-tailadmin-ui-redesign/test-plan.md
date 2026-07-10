# Test Plan (brief §18 deliverable)

Vitest + React Testing Library + `@testing-library/jest-dom` + `axe-core`, in
`resources/js/__tests__/**`. Every restyled surface keeps its existing tests green and
adds the checks below. Backend is untouched, so no new PHPUnit domain tests are required
beyond confirming any thin AdminConsole prop addition returns tenant-scoped data.

## Component tests

- Sidebar renders the correct grouped items per the `can` map; hidden without permission.
- `SidebarSection` collapses/expands; active route highlighted.
- `PermissionGate` hides unauthorized actions.
- `StatusBadge` renders correct variant + AR/EN label for every status in data-model §4.
- `DataTable` renders rows, loading skeleton, empty state, and error state; toolbar
  search/filter/pagination behave; `ActionDropdown` opens.
- `SubmitButtonWithLoader` disables during submit and prevents duplicate clicks.
- `ConfirmModal` confirms/cancels; `ReasonModal` blocks confirm until a reason is given.
- `NotificationDropdown` / `SearchCommand` render populated and empty/placeholder states.

## Page render tests (each restyled page)

- Renders with skeleton → populated; shows empty and error states on cue.
- Renders in Arabic/RTL without layout breakage; axe: zero serious/critical violations.
- No horizontal page scroll at tablet/mobile widths for scanner/kiosk/manual-desk.

## Flow tests (assert the REAL endpoint is called — not just a callback)

- Login → dashboard.
- Navigate dashboard via sidebar (permission-filtered).
- Create event → `POST /api/v1/tenant/events` called with body + `Idempotency-Key`;
  success toast; failure keeps form + shows validation.
- Publish event → `POST .../{id}/publish` called; UI reflects real status after reload.
- Create ticket type → `POST .../ticket-types` called.
- View attendee detail renders.
- Revoke credential (reason) → `POST .../credentials/{cid}/revoke` called with reason;
  failure does NOT flip status. Reissue → `.../reissue` called.
- Scan QR → `POST .../scans` called; accepted/rejected panel from response.
- Manual desk search → `.../desk/lookups` called.
- Create ACS zone/lane/rule → `POST .../acs/{zones|lanes|rules}` called.

## Regression / cross-cutting

- Existing 006 tests still pass after restyle (props/permissions unchanged).
- Responsive checks (sidebar drawer, table scroll/card conversion, form stacking).
- Arabic/RTL sweep across new/restyled pages.
- Quality gates: `npm run lint` (`--max-warnings=0`), `npm run typecheck`, `npm run test`,
  `vite build`, `composer quality` (docs check + phase-boundary check keeps Phase 5 absent).

## Done when

All component/page/flow tests pass (including real-endpoint assertions), axe/RTL/
responsive checks pass, no demo content remains, and the quality gates are green.
