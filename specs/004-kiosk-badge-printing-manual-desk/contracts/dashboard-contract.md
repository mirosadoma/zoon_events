# Phase 3 Dashboard and On-Site Experience Contract

Extends `specs/003-wallet-passes-scanning/contracts/dashboard-contract.md`.
Only the additions and changes below apply; every unchanged Phase 0/1/2 rule
remains authoritative.

## Organizer Navigation Additions

Tenant navigation adds only:

- Kiosks (per event: registration, pairing, live health)
- Badge Templates (per event: no-code designer, activation)
- Manual Desk (per event: attendee lookup, check-in, print, walk-up)

ACS zone/lane, identity verification, and venue marketplace pages or
placeholders remain forbidden in Phase 3.

## Page Authorization

| Page | Required permission |
|---|---|
| Kiosk registration and pairing | `kiosk.manage` |
| Kiosk/printer health view | `kiosk.health.view` |
| Badge template designer | `badge.template.manage` |
| Manual desk lookup/check-in | `checkin.desk.perform` |
| Manual desk badge print | `badge.print` |
| Manual desk badge reprint | `badge.reprint` |
| Manual desk walk-up registration | `attendee.walkup.register` |

Navigation visibility is only a convenience. Server authorization is
authoritative; on-site desk staff scoped to one event never gain organizer
financial, attendee-editing beyond walk-up creation, or credential-
revocation capability through this surface (CR-002).

## Required States

- **Kiosk health page**: loading, empty (no kiosks registered), live
  (online/offline/degraded per kiosk with printer status), and error
  states; a kiosk with no heartbeat within
  `kiosk_offline_threshold_seconds` visibly shows `offline`.
- **Badge template designer**: draft editing, validation-error (field
  outside allowlist), active/inactive list, and a clear "no active
  template" warning surfaced to manual desk/kiosk operators.
- **Manual desk**: idle/search, multiple-match (bounded list),
  confirmation-pending (when `lookup_confirmation_required`), check-in
  result (accepted/duplicate/revoked/expired/rejected, matching
  `scan-contract.md`), print-in-progress, print-failed (with the safe
  reason category), and reprint (reason-required) states.

## Kiosk Pairing UX

- Pairing displays the raw device-session secret exactly once, with an
  explicit warning that it cannot be retrieved again; the paired secret is
  never re-displayed or logged after this screen.
- Re-pairing a kiosk clearly warns that it immediately invalidates the
  currently deployed device's session.

## Manual Desk and Kiosk Attendee Lookup UX

- Search results show only display name, ticket type, and check-in status
  per match — the same minimum already permitted for staff scanning in
  Phase 2 — never full contact details.
- A too-broad search fragment shows a "refine your search" message rather
  than an unbounded list (`kiosk-contract.md`'s `lookup_too_many_matches`).
- When `lookup_confirmation_required` is enabled, the UI clearly indicates
  a one-time code has been sent and blocks check-in until it is entered
  and verified.

## Badge Template Designer

- Field selection is restricted to the fixed allowlist
  (`data-model.md` §Badge Template); the UI never allows a free-form field
  name or arbitrary markup, satisfying CR-005 at the presentation layer as
  well as the API layer.
- Activation requires an explicit confirmation step, since it immediately
  affects what every subsequent kiosk/desk print produces for that event.
- The designer renders a live preview in both Arabic/RTL and English/LTR
  using the same layout data the print payload will use, so bilingual
  layout problems are caught before activation (CR-007).

## Kiosk and Printer Health View

- Health polls the bounded kiosk-health endpoint on the same short fixed
  interval pattern as Phase 2's check-in dashboard (`research.md`
  Decision 8); it does not open a persistent streaming connection.
- Displays online/offline/degraded status and the kiosk's last-reported
  printer status (`ready`, `error`, `disconnected`, `unknown`) side by
  side, scoped to one authorized event at a time.

## Accessibility and Privacy

- All kiosk, badge template, and manual desk surfaces have programmatic
  labels, descriptions, and keyboard-operable controls, consistent with
  Phase 0-2's accessibility bar; the kiosk's attendee-facing screens are
  additionally touch-target sized for unattended public use.
- Direction changes (Arabic/RTL, English/LTR) use logical layout
  properties for both on-screen UI and the printed badge layout preview.
- No kiosk device-session secret, printer connection credential, or raw
  QR credential token ever appears in dashboard props, logs, or HTML.

## Parity

Kiosk, badge template, print/reprint, walk-up, and manual desk check-in
actions invoke the same application actions, permissions, idempotency
handling, audited transactions, and domain events as the versioned API
operations in `openapi.yaml`. No presentation layer queries `Kiosk`,
`BadgePrinting`, `Scanning`, or `Attendees` persistence directly.
