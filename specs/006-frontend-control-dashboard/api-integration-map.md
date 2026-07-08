# API Integration Map + Missing Backend API Register (source-plan §23)

This map ties each dashboard screen to the **existing module application query/
action** that supplies it via Inertia props (no separate REST `/src/api` layer —
see `research.md` Decision 1). A row whose "Backing source" is a **GAP** needs a
thin, versioned read projection on the **already-accepted owning module** before
the screen is fully live; until then the screen renders a mock-safe placeholder/
empty state. No gap is closed with a new business module (Constitution VI).

## How integration works here

- **Reads**: `AdminConsole` controller → read-only ViewModel → owning module's
  published Application Query → Inertia props. Tenant scope from `tenant.context`;
  never a client-supplied tenant id.
- **Writes/actions**: Inertia `router`/form submit → `AdminConsole` controller →
  owning module's Application **Action** (same one the API uses) → audited txn →
  redirect/prop refresh. Validation errors surface from the standard envelope.
- **Live views**: bounded polling via `lib/checkin-polling.ts` (Inertia partial
  reloads), no streaming.

## Screen → backing source

| Screen | Reads via | Actions via | Backing source |
|---|---|---|---|
| Login | Fortify session | `SessionController@store` | EXISTS |
| Overview | `FoundationDashboardViewModel` | — | EXISTS (extend for new counters) |
| Profile | membership/tenant query | — | EXISTS |
| Users admin | membership/platform user query | membership/user actions | EXISTS |
| Roles admin | role query | role assign/manage actions | EXISTS |
| Tenant settings | tenant/configuration query | configuration actions | EXISTS |
| Audit logs | Audit query | export action | EXISTS |
| Events list/detail | Events query | Events actions (create/update/publish/cancel) | EXISTS |
| Registration form builder | Registration query | Registration field actions | EXISTS |
| Registration preview | public event query | test-submit action | EXISTS |
| Ticket types | Ticketing query | Ticketing actions | EXISTS |
| Price tiers | Ticketing price-tier query | price-tier actions | Confirmed (AdminConsole ViewModel incl. `is_active_now`) |
| Orders list/detail | Orders query | order/refund actions | EXISTS |
| Attendees list/detail | Attendees query | attendee actions | EXISTS |
| Credentials list/detail | Credentials query | revoke/reissue actions | EXISTS |
| Wallet passes list | WalletPasses query | wallet manage actions | EXISTS |
| Wallet pass detail | WalletPasses detail query | update/revoke actions | EXISTS (AdminConsole ViewModel) |
| Scanner | — | `SubmitScanAction` | EXISTS |
| Check-in dashboard | check-in summary query | — | EXISTS |
| Scan events | Scanning events query | — | EXISTS (AdminConsole ViewModel) |
| Kiosks list | Kiosk query | register/activate actions | EXISTS |
| Kiosk detail | Kiosk detail query | activate/deactivate | Confirmed (AdminConsole ViewModel + API show) |
| Kiosk mode | kiosk-session read | print/lookup actions | EXISTS (device-session) |
| Badge templates | BadgePrinting template query | template actions | EXISTS |
| Badge print jobs | BadgePrinting job query | reprint action | Confirmed (AdminConsole ViewModel + API index) |
| Manual desk | ManualDesk query | check-in/print/override/walk-up | EXISTS |
| ACS overview | AccessControl summary query | — | EXISTS |
| ACS zones/lanes/rules | AccessControl config queries | config actions | EXISTS (editors) — promote to pages |
| ACS access logs | AccessControl events query | — | EXISTS (gate-events) |
| Gate health | AccessControl health query | emergency actions | EXISTS |
| Event report | composed summary queries | — | Confirmed (available metrics + documented placeholder) |

## Missing Backend API Register

Each GAP is a candidate read-projection addition to the owning accepted module.
Confirm during implementation whether the projection already exists (many Phase
1–4 queries may already return the needed fields); only file the addition if truly
absent. Record final status here and mirror the summary into `spec.md`'s Missing
Backend API Requirements section.

| ID | Screen | Owning phase/module | Expected read projection | Temp UI treatment | Status |
|---|---|---|---|---|---|
| GAP-1 | Price tiers | Phase 1 / Ticketing | `listPriceTiers(eventId)` → PriceTierRow[] incl. `is_active_now` | Empty state when no tiers exist | Confirmed (AdminConsole ViewModel incl. `is_active_now`) |
| GAP-2 | Wallet pass detail | Phase 2 / WalletPasses | `getWalletPass(passId)` → WalletPassDetail incl. `last_pushed_at`, `pass_url?` | Detail card placeholder | Confirmed (AdminConsole ViewModel) |
| GAP-3 | Scan events | Phase 2 / Scanning | `listScanEvents(eventId, filters)` → ScanEventRow[] | Empty state + link to check-in dashboard | Confirmed (AdminConsole ViewModel) |
| GAP-4 | Kiosk detail | Phase 3 / Kiosk | `getKiosk(kioskId)` → KioskDetail incl. recent checkins/print jobs | Summary from list row only | Confirmed (AdminConsole ViewModel + API show) |
| GAP-5 | Badge print jobs | Phase 3 / BadgePrinting | `listPrintJobs(eventId, filters)` → BadgePrintJobRow[] | Empty state | Confirmed (AdminConsole ViewModel + API index) |
| GAP-6 | Event report | Phases 1–4 (rollup) | per-metric summary reads (first-scan success rate, wallet adoption) | Cards show available metrics; missing ones labelled "not available yet" | Confirmed (AdminConsole ViewModel: wallet_adoption computed; first_scan_success_rate placeholder) |

Rules for closing a gap:
- Add the query method to the owning module's Application layer + its OpenAPI read
  operation; run `composer quality` (OpenAPI sync/lint) and the phase-boundary check.
- Never read another module's persistence from `AdminConsole`.
- Never introduce a new domain module or business rule to satisfy a display need.
