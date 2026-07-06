# Phase 2 Dashboard and Public Experience Contract

Extends `specs/002-registration-ticketing-credentials/contracts/dashboard-contract.md`.
Only the additions and changes below apply; every unchanged Phase 1 rule
remains authoritative.

## Organizer Navigation Additions

Tenant navigation adds only:

- Check-In (per event: live counts, recent scan activity)
- Wallet Passes (per event: issuance status, failed/degraded push summary)

Kiosk, badge printing, manual desk, ACS zone/lane, identity verification, and
venue marketplace pages or placeholders remain forbidden in Phase 2.

## Page Authorization

| Page | Required permission |
|---|---|
| Check-in dashboard | `checkin.dashboard.view` |
| Scan submission (staff scanner UI) | `checkin.scan.submit` |
| Manual override action | `checkin.scan.override` |
| Wallet pass status view | `wallet.pass.view` |

Navigation visibility is only a convenience. Server authorization is
authoritative; an on-site staff account scoped to one event never sees
another tenant's or event's check-in or wallet data, and never gains
organizer financial, attendee-editing, or credential-revocation capability
through this surface.

## Required States

The check-in dashboard has loading, empty (no scans yet), live-updating,
degraded (wallet push backlog or unreachable provider), and error states.
The wallet pass status view distinguishes `created`, `active`, `updated`,
`revoked`, `expired`, and `failed` per pass without exposing provider
payloads or certificates.

## Public Wallet Add-to-Wallet Experience

- The attendee's confirmation/order status page (reached with the existing
  Phase 1 public order access token) shows "Add to Apple Wallet" and "Add to
  Google Wallet" actions only when the attendee's credential is currently
  active.
- Wallet actions render in the attendee's chosen locale where the wallet
  platform supports it; where a platform limits localization, English
  content is used with the limitation documented, never a broken or
  half-translated pass.
- A wallet generation failure shows a safe retry state and never blocks
  access to the already-issued QR credential shown directly on the page.

## Real-Time Check-In Dashboard

- The dashboard polls the bounded `EventCheckInSummary` endpoint on a short
  fixed interval; it does not open a persistent streaming connection in
  Phase 2 (`research.md` Decision 8).
- Displayed counts distinguish `checked_in_count` from `rejected_count` and
  `duplicate_count`; only accepted and manually overridden scans increase
  the check-in count, and overridden entries are visually distinguishable
  from ordinary accepted entries.
- The dashboard is scoped to one authorized event at a time; switching
  events re-authorizes before showing any count.

## Accessibility and Privacy

- All check-in and wallet status surfaces have programmatic labels,
  descriptions, and keyboard-operable controls, consistent with Phase 1's
  accessibility bar.
- Direction changes (Arabic/RTL, English/LTR) use logical layout properties;
  scan timestamps, counts, and identifiers remain bidi-isolated.
- No raw QR credential token, Apple push token, Google service-account key,
  or wallet certificate ever appears in dashboard props, logs, or HTML.
- Scan result lists show attendee display name and ticket type only, never
  full contact details, to on-site staff performing check-in.

## Parity

Check-in and wallet dashboard actions invoke the same scan-submission and
wallet-generation application actions, permissions, idempotency handling,
audited transactions, and domain events as the versioned API operations in
`openapi.yaml`. No presentation layer queries `WalletPasses` or `Scanning`
persistence directly.
