# Dashboard design system

Owner: Admin Console  
Last reviewed: 2026-07-08

The project-owned React/Tailwind system provides responsive navigation, logical RTL
properties, light/dark/system modes, visible focus, skip links, reduced motion, and
loading/empty/error/forbidden/conflict/queued states. No external runtime fonts, CDN, or
licensed template assets are required.

## Shared Shell

All authenticated dashboard pages use `DashboardLayout` with `PageHeader` and
`PageContent`. The layout owns the skip link, sidebar/topbar, route loader, toast
provider, and route error boundary. Public kiosk mode is the only intentionally
standalone operational surface.

`FoundationLayout` has been retired; platform placeholder sections now render
through the same dashboard shell as tenant pages.

## Shared States And Badges

Use `components/feedback` for loading, empty, error, forbidden, conflict, and
queued states. List/detail pages should show a purpose-specific `EmptyState`
rather than raw paragraphs.

Use `StatusBadge` for lifecycle and result labels such as `active`, `inactive`,
`published`, `pending`, `printed`, `accepted`, `rejected`, `healthy`, `degraded`,
and `offline`. Do not hand-roll color-only status pills in pages.

Forms use the shared input components, `SubmitButtonWithLoader`, and
`ConfirmModal`/`ReasonModal` for destructive or audited actions. Reason-required
actions include credential revocation, badge reprint, manual override, and ACS
emergency controls.

## Permission Matrix

Navigation visibility is filtered by the shared `can` map, but server
authorization remains authoritative.

| Page | Permission |
|---|---|
| `/profile` | authenticated |
| `/admin/users` | `membership.view`; actions require `membership.manage` |
| `/admin/roles` | `role.view`; permission updates require `role.manage` |
| `/admin/tenant-settings` | `tenant.view` |
| `/admin/audit-logs` | `audit.view` |
| `/tenant/events` and event detail | `event.view` |
| Event create/edit | `event.manage` |
| Registration builder/preview | `registration.manage` |
| Ticket types and price tiers | `ticketing.manage` |
| Orders | `order.view` |
| Attendees | `attendee.view` |
| Credentials | `credential.view`; revoke/reissue require `credential.revoke`/`credential.reissue` |
| Wallet passes | `wallet.pass.view` |
| Scanner | `checkin.scan.submit` |
| Check-in dashboard and scan events | `checkin.dashboard.view` |
| Kiosks | `kiosk.manage` / `kiosk.health.view` |
| Badge templates and print jobs | `badge.template.manage` / `badge.print` |
| Manual desk and walk-up | `checkin.desk.perform` / `attendee.walkup.register` |
| ACS overview/logs/health | `acs.events.view` / `acs.health.view` |
| ACS zones/lanes/rules | `acs.configure` |
| ACS emergency controls | `acs.emergency.manage` |
| Event reports | Per-metric domain view permissions; currently routed through `event.view` |

## Accessibility, RTL, And Responsive Rules

Every new or migrated page must render in English and Arabic, preserve logical RTL
layout, keep focus indicators visible, and pass the axe checks in
`phase6-accessibility-browser.test.tsx`.

Scanner, kiosk mode, and manual desk surfaces must remain usable at mobile widths
without horizontal page scrolling. Keep controls wrapping (`flex-wrap`) and
content constrained with `max-w-*`, `w-full`, or responsive grids.

## Deployment Parity

The dashboard has no cloud-only runtime dependency. Adapter or route failures
must render through `ErrorState` or the `DashboardLayout` route error boundary
with a clear recovery path instead of leaking raw exceptions or partial data.
