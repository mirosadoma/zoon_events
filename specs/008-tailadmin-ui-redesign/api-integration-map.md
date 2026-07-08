# API Integration Map (brief §18 deliverable)

Each redesigned screen's reads (Inertia props) and its action controls → the **existing**
`/api/v1/**` endpoint the control must call. Actions use `fetch` with
`credentials:'include'`, `X-Tenant-ID`, and `Idempotency-Key` for writes; the UI reflects
the real result (FR-011). Endpoints marked ✅ already exist (Phases 1–4). Gaps go to
[missing-api-requirements.md](missing-api-requirements.md).

| Screen | Reads via | Action → endpoint | Exists? |
|---|---|---|---|
| Login | — | `POST /login` | ✅ |
| Overview | `FoundationDashboardViewModel` | — | ✅ |
| Events list/detail | Events ViewModels | — (view) | ✅ |
| Create/edit event | props | `POST /api/v1/tenant/events` · `PATCH .../{id}` | ✅ |
| Publish/cancel event | props | `POST .../{id}/publish` · `.../cancel` | ✅ |
| Registration builder | props | Registration field actions (`Registration/Routes/api.php`) | ✅ |
| Ticket types | props | `POST/PATCH .../{id}/ticket-types` | ✅ |
| Price tiers | props | `POST .../ticket-types/{ttid}/price-tiers` | ✅ |
| Orders list/detail | Orders ViewModels | — | ✅ |
| Attendees list/detail | Attendees ViewModels | credential/print/check-in actions below | ✅ |
| Credential revoke/reissue | props | `POST .../credentials/{cid}/revoke` · `.../reissue` | ✅ |
| Wallet passes | WalletPasses ViewModels | wallet manage (where permitted) | ✅ |
| Scanner | — | `POST .../{id}/scans` | ✅ |
| Check-in dashboard / scan events | CheckIn/Scan ViewModels | — (bounded polling) | ✅ |
| Kiosks list/detail | Kiosk ViewModels | register/activate | ✅ |
| Kiosk mode | device-session props | lookup/print | ✅ |
| Badge templates / print jobs | Badge ViewModels | print `.../badge-print-jobs` · reprint `.../{jid}/reprint` | ✅ |
| Manual desk | ManualDesk ViewModel | `.../desk/lookups` · `.../scans` · `.../walk-up-registrations` | ✅ |
| ACS zones/lanes/rules | ACS ViewModels | `POST .../acs/{zones|lanes|rules}` | ✅ |
| Access logs / gate health | ACS ViewModels | emergency actions | ✅ |
| Users / roles / tenant settings / audit | Admin ViewModels | user/role/config actions | ✅ |
| Reports | `EventReportViewModel` | — | ✅ (placeholders per GAP-6) |
| Notifications dropdown | — | notification feed | ⚠️ GAP — see missing-api |
| Global search | — | cross-entity search | ⚠️ GAP — see missing-api |

Rule: no screen reads another module's persistence from the client; no new backend
business module is created to satisfy a display need. Confirmed cosmetic-only controls
from the 006 review (credential revoke/reissue, event publish/cancel, event/ticket/tier
create) are wired here to the ✅ endpoints above.
