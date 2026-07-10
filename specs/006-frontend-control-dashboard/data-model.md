# Phase 1 Data Model: Frontend Control Dashboard

This phase introduces **no new persisted entities, tables, or migrations**. It
defines the **read-only view models (Inertia prop shapes)** the dashboard renders,
each projected by an `AdminConsole` ViewModel from an existing module's published
query. Field lists are display projections, not schemas; the authoritative data
definitions live in each phase's own `data-model.md`
(`specs/001..005/data-model.md`). Types are declared in
`resources/js/types/{phase1,phase2,phase3,phase4,shell}.ts`.

## Invariants (apply to every view model)

1. **Tenant-scoped**: every projection is resolved from the authenticated tenant
   context (`tenant.context` middleware); no prop is keyed by a client-supplied
   tenant id, and a tenant-mismatched record is never rendered.
2. **Permission-scoped**: a prop/action a user may not perform is omitted or
   flagged non-actionable via the shared `can` map; the UI never relies on hiding
   alone for security.
3. **Minimal & non-secret**: no signing key, credential secret, M2M/transport
   token, or raw QR payload appears in any prop (CR-004/CR-005); operational feeds
   carry a credential reference, not full PII.
4. **Read-through**: props come only from published module queries; the
   presentation layer never reads another module's persistence directly
   (Constitution VI).

## Shell view models

- **SessionContext**: `user{ id, name, email, role_label }`, `tenant{ id, name,
  slug, branding, default_locale, default_timezone }`, `locale`, `theme`,
  `can: Record<permissionKey, boolean>`. Source: Foundation membership/tenant
  queries via `HandleInertiaRequests` shared props.
- **NavigationManifest**: ordered items `{ key, label_i18n, href, permission,
  children[] }` filtered by `can`. Source: static manifest + `can`.
- **Breadcrumbs**: `{ label_i18n, href }[]` derived from the active route.

## Overview view model

- **DashboardOverview**: counts `{ events_total, events_published, attendees_total,
  orders_total, credentials_issued, checkins_today, kiosks_active, gates_active,
  scans_failed }`, `recent_audit_events[]`. Source: `FoundationDashboardViewModel`
  (extend) + per-module summary queries. Any figure without a source → Missing-API
  row.

## Foundation administration view models

- **UserRow / UserDetail**: `{ id, name, email, phone?, status, roles[],
  last_login_at? }`. Source: platform/tenant membership queries
  (`membership.view`/`platform.user.view`).
- **RoleRow / RoleDetail**: `{ id, name, is_system, permissions[] }`. Source: role
  queries (`role.view`/`platform.role.view`).
- **TenantSettings**: `{ name, slug, branding, default_locale, default_timezone,
  residency?, retention? }`. Source: tenant/configuration queries (`tenant.view`/
  `configuration.view`).
- **AuditLogRow / AuditLogDetail**: `{ id, actor, action, entity_type, entity_id,
  outcome, occurred_at, before?, after? }`. Source: Audit query (`audit.view`).

## Events / registration / ticketing view models

- **EventRow / EventDetail**: `{ id, name, tier, status, starts_at, ends_at,
  capacity, registrations_count, location?, timezone, branding? }`. Source: Events
  query (`event.view`). Tabs are presentation only.
- **RegistrationFormField**: `{ id, type, label_i18n, required, order,
  validation, options? }` across text/email/phone/number/date/dropdown/
  multi-select/checkbox/hidden/consent. Source: Registration query
  (`registration.manage`).
- **TicketTypeRow**: `{ id, name, description?, price, currency, quantity,
  remaining, sale_starts_at, sale_ends_at, attendee_type, tier, status }`. Source:
  Ticketing query (`ticketing.manage`).
- **PriceTierRow**: `{ id, name, ticket_type_id, price, currency, starts_at,
  ends_at, capacity_threshold, priority, status, is_active_now }`. Source: Ticketing
  query. *(Pages new — confirm read projection exists; else Missing-API.)*

## Orders / attendees / credentials view models

- **OrderRow / OrderDetail**: `{ id, number, buyer_name, buyer_email, total,
  currency, payment_status, order_status, created_at, items[], attendees[],
  payment_reference?, audit[] }`. Source: Orders query (`order.view`).
- **AttendeeRow / AttendeeDetail**: `{ id, name, email, phone, ticket_type,
  registration_status, checkin_status, credential_status, custom_fields[],
  order_ref, checkin_history[], badge_history[], audit[] }`. Source: Attendees query
  (`attendee.view`).
- **CredentialRow / CredentialDetail**: `{ id, code, attendee_ref, ticket_type,
  status ∈ {pending,active,revoked,expired,reissued}, issued_at, expires_at,
  revoked_at?, revoke_reason?, reissued_from?, scan_history[], audit[] }`. Source:
  Credentials query (`credential.view`). No signing material.

## Wallet / scanning view models

- **WalletPassRow / WalletPassDetail**: `{ id, provider ∈ {apple,google}, serial,
  attendee_ref, credential_ref, status ∈ {created,active,updated,revoked,expired,
  failed}, last_pushed_at?, pass_url? }`. Source: WalletPasses query
  (`wallet.pass.view`).
- **ScanResult** (scanner): `{ decision ∈ {accepted,rejected}, attendee_name?,
  ticket_type?, status?, reason_code?, reason_text_i18n? }`. Source: Scanning
  submit action (`checkin.scan.submit`); server-validated, no payload echoed.
- **CheckInSummary**: `{ registered, checked_in, not_checked_in, accepted,
  rejected, duplicate, revoked_attempts, per_hour[], latest[] }`. Source: check-in
  summary query (`checkin.dashboard.view`).
- **ScanEventRow**: `{ id, result, scanner_type, gate?, zone?, offline, attendee_ref,
  occurred_at }` across accepted/rejected/duplicate/revoked/expired/
  unauthorized_zone/anti_passback_rejected/manual_override. Source: Scanning query.
  *(Scan-events page new — confirm read projection; else Missing-API.)*

## Kiosk / badge / manual-desk view models

- **KioskRow / KioskDetail**: `{ id, device_name, device_code, location, status,
  last_seen_at, printer_status, scanner_status, app_version, recent_checkins[],
  recent_print_jobs[] }`. Source: Kiosk query (`kiosk.manage`/`kiosk.health.view`).
- **KioskModeContext**: `{ event_branding, device_code, lookup_result?,
  print_state }`. Source: kiosk-session-scoped read (`kiosk.session` middleware).
- **BadgeTemplateRow**: `{ id, name, attendee_type, paper_size, printer_type,
  layout, status }`. Source: BadgePrinting query (`badge.template.manage`).
- **BadgePrintJobRow**: `{ id, attendee_ref, status ∈ {pending,printing,printed,
  failed,cancelled}, printed_by?, printed_at?, reprint_reason? }`. Source:
  BadgePrinting query (`badge.print`). *(Print-jobs page new — confirm; else
  Missing-API.)*
- **ManualDeskLookup**: `{ attendee_ref, status, actions_allowed[] }`. Source:
  ManualDesk query (`checkin.desk.perform`).

## ACS view models

- **AcsOverview**: `{ zones_total, lanes_total, gates_active, gates_offline,
  entries_accepted, entries_rejected, anti_passback_rejections, emergency_events,
  latest_gate_events[] }`. Source: AccessControl queries (`acs.events.view`/
  `acs.health.view`).
- **AcsZoneRow**: `{ id, name, external_acs_zone_id, status }`. Source:
  AccessControl query (`acs.configure`).
- **AcsLaneRow**: `{ id, name, zone_id, external_acs_lane_id, gate_type, status }`.
- **AcsRuleRow**: `{ id, ticket_type, attendee_type, zone_id, lane_id, direction,
  valid_from, valid_until, status }`.
- **AcsAccessLogRow**: `{ id, result, zone, lane, direction, attendee_ref?,
  credential_ref?, reason_code?, occurred_at }`. *(Access-logs page new — confirm
  read projection; else Missing-API.)*
- **GateHealthRow**: `{ gate_or_lane, status, last_heartbeat, last_event,
  error_state?, emergency_mode }`. Source: AccessControl health query
  (`acs.health.view`). Reuses existing `acs-health` page.

## Reports view model

- **EventReport**: `{ registrations, paid_orders, payment_success_rate,
  credentials_issued, credentials_revoked, wallet_adoption, checkins,
  first_scan_success_rate, badge_prints, acs_entries_accepted,
  acs_entries_rejected }`. Source: composed from the per-module summary queries
  above; any missing figure → Missing-API row.

## State transitions

This phase persists no state, so it owns no transitions. It **displays**
backend-owned lifecycles (credential pending→active→revoked/expired/reissued;
badge-job pending→printing→printed/failed/cancelled; wallet created→active→updated/
revoked/expired/failed; ACS lane online→degraded→offline) via the shared
`StatusBadge`, and triggers transitions only through existing module actions behind
confirmation/reason modals.
