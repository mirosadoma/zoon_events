# Missing Backend API Requirements (brief §18 deliverable)

Running register of screens whose redesign needs a backend endpoint that does not yet
exist. No new backend business module or business rule is created to fill a gap; the
screen ships a documented placeholder/empty state until the owning module exposes the
projection. Confirm each during implementation — many may already be covered by an
existing prop.

For each gap: page, required endpoint, method, request, response shape, priority,
temporary frontend behavior.

| ID | Page | Endpoint (proposed) | Method | Request | Response shape | Priority | Temporary behavior |
|---|---|---|---|---|---|---|---|
| GAP-A | Topbar notifications | `/api/v1/tenant/notifications` | GET | tenant context, `?unread` | `{ data: [{id, type, title, body, read, created_at}] }` | Low | Empty dropdown "No notifications yet"; bell without badge |
| GAP-B | Global search (`SearchCommand`) | `/api/v1/tenant/search` | GET | `?q=` | `{ data: [{type, id, label, href}] }` | Low | Client-side navigation to known routes only; "Search coming soon" empty state |
| GAP-C | System Settings page | `/api/v1/tenant/system-settings` (or reuse configuration read) | GET | tenant context | tenant/system config projection | Medium | Reuse existing tenant-settings/configuration read; placeholder for any unavailable field |
| GAP-D | Event report — first-scan success rate | (per `EventReportViewModel`) | — | — | metric field | Low | Card labelled "not available yet" (inherited GAP-6 from 006) |

Notes:
- Notifications (GAP-A) and global search (GAP-B) are **optional** shell features
  (brief marks them "if supported"). Building a notifications/search backend is a new
  capability and therefore **out of scope** for this redesign phase — hence placeholders
  and this register, not new modules.
- Re-verify GAP-C against the existing tenant-settings/configuration endpoints before
  filing; likely satisfiable by reusing them.
- Update this file (and mirror the summary into `spec.md` Missing Backend API
  Requirements) as gaps are confirmed or closed.

## Demo / TailAdmin leftovers audit (T004)

- **Removed / avoided**: TailAdmin sample pages are not shipped; shell uses Zonetec tokens (`resources/css/app.css`) and `Zonetec Foundation` branding.
- **Intentional placeholders**: `NotificationDropdown` (GAP-A), partial `SearchCommand` empty state when API unavailable (GAP-B); real search wired via `/dashboard/search` when backend returns results.
- **Demo seed data**: `database/seeders/DemoAccounts.php` + `FoundationSeeder` — dev/staging only; documented in `docs/demo-users-ar.md`.
- **No action**: `public/landing/*.png` are product marketing assets, not TailAdmin kit leftovers.
