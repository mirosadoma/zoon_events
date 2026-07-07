# Phase 4 Dashboard and ACS Operations Contract

Extends `specs/004-kiosk-badge-printing-manual-desk/contracts/dashboard-contract.md`
and `specs/003-wallet-passes-scanning/contracts/dashboard-contract.md`. Only
the additions and changes below apply; every unchanged Phase 0/1/2/3 rule
remains authoritative.

## Organizer/Operations Navigation Additions

Tenant navigation adds only:

- ACS Configuration (per event: zones, lanes, authorization rules,
  anti-passback, unavailability/emergency modes, ACS integration credential)
- Gate Events (per event: live allowed/denied/entry/exit/emergency feed with
  reasons)
- ACS Health (per event: lane and ACS integration status)

Identity verification and venue marketplace pages or placeholders remain
forbidden in Phase 4.

## Page Authorization

| Page | Required permission |
|---|---|
| ACS zone/lane/rule configuration | `acs.configure` |
| ACS integration credential registration/rotation | `acs.configure` |
| Gate events feed | `acs.events.view` |
| ACS/lane health view | `acs.health.view` |
| Emergency egress raise/clear | `acs.emergency.manage` |

Navigation visibility is only a convenience. Server authorization is
authoritative; the external ACS authenticates as an M2M integration actor and
never appears as a human user in this surface, and an operator scoped to one
event never sees another tenant's or event's ACS data (CR-002).

## Required States

- **ACS configuration**: loading, empty (no zones/lanes), editing (zone/lane/
  rule create/edit), validation-error (duplicate external identifier,
  invalid time window), and error states; the integration-credential screen
  shows the raw M2M secret exactly once.
- **Gate events feed**: loading, empty (no events yet), live (decision/entry/
  exit/emergency rows with reason codes and human-readable localized reason
  text), and error states; an active emergency is prominently flagged.
- **ACS health view**: loading, empty (no lanes), live (per-lane
  `online`/`degraded`/`offline` and overall ACS integration status), and
  error states; a lane with no ACS contact within its threshold visibly shows
  `offline`.

## ACS Integration Credential UX

- Registration/rotation displays the raw M2M secret exactly once, with an
  explicit warning that it cannot be retrieved again; the secret is never
  re-displayed or logged after this screen.
- Rotating a credential clearly warns that it immediately invalidates the
  currently deployed ACS integration's secret.

## Gate Events Feed UX

- Rows show zone, lane, direction, decision, and the stable `reason_code`
  with localized human-readable reason text; deny rows never disclose which
  specific rule or scope check failed beyond the stable category.
- The feed shows only a credential reference and access metadata — never full
  attendee contact details, national identifiers, biometric data, or payment
  data (CR-005).
- An active emergency is surfaced as a banner with the affected zone(s) and
  the applied fail-open/fail-closed behavior, with a clear
  `acs.emergency.manage`-gated clear action.

## ACS/Lane Health View

- Health polls the bounded ACS-health endpoint on the same short fixed
  interval pattern as the Phase 2/3 dashboards (`research.md` Decision 8); it
  does not open a persistent streaming connection.
- Displays each lane's `online`/`degraded`/`offline` status and the overall
  ACS integration status side by side, scoped to one authorized event at a
  time.

## Emergency Egress UX

- Raising or clearing an emergency requires an explicit confirmation step and
  the `acs.emergency.manage` permission, since it immediately changes gate
  decisioning for the affected zone(s).
- The UI clearly distinguishes emergency fail-open state from normal
  operation and from ACS-unavailable fail-open, since the reason codes and
  operational responses differ.

## Accessibility and Privacy

- All ACS configuration, gate-events, and health surfaces have programmatic
  labels, descriptions, and keyboard-operable controls, consistent with the
  Phase 0-3 accessibility bar.
- Direction changes (Arabic/RTL, English/LTR) use logical layout properties;
  machine-readable reason codes stay language-neutral and are localized only
  for display.
- No ACS M2M secret, transport token, or raw credential payload ever appears
  in dashboard props, logs, or HTML.

## Parity

ACS zone/lane/rule configuration, authorization, event ingestion, emergency
egress, and health actions invoke the same application actions, permissions,
idempotency handling, audited transactions, and domain events as the
versioned API operations in `openapi.yaml`. No presentation layer queries
`AccessControl`, `Scanning`, or `Credentials` persistence directly.
