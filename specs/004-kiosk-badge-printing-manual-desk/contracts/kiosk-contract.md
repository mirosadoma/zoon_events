# Kiosk Session, Lookup, and Check-In Contract

## Purpose

Define how a registered kiosk device authenticates and how kiosk-originated
lookups and check-ins reuse the Phase 2 scan decision order, extending
`specs/003-wallet-passes-scanning/contracts/scan-contract.md` with the
`kiosk` scanner source and the manual desk's equivalent staff-session path.
This contract is the boundary between unattended hardware and the
authoritative check-in core; it MUST NOT introduce a second entry decision.

## Relationship to the Scan Contract

A kiosk or manual-desk check-in always ends at the exact Phase 2 decision
order and stable result set (`accepted`, `manual_override`, `duplicate`,
`revoked`, `expired`, `rejected`). This contract adds **how the request
reaches that decision**: kiosk device-session authentication, and the
attendee-lookup path used when no scannable QR is presented.

## Kiosk Registration and Pairing

1. An authorized actor (`kiosk.manage`) creates a `Kiosk` row scoped to one
   tenant/event.
2. The same actor triggers pairing, which issues one raw device-session
   secret, shown exactly once, and stores only its hash
   (`data-model.md` §Kiosk Session). Pairing a new session immediately
   revokes any prior session for that kiosk.
3. If `Kiosk.confirmation_required` is true, the newly paired session
   remains unconfirmed until the kiosk operator enters the configured
   PIN/one-time code on first use; an unconfirmed session may not submit
   scans, lookups, or print requests.
4. A retired kiosk (`Kiosk.status = 'retired'`) has all sessions revoked
   immediately and can never be re-paired without organizer/ops
   re-registration.

## Kiosk Session Authentication

Every kiosk API call (heartbeat, lookup, scan, print) carries the paired
session secret in an `Authorization: KioskSession {secret}` header. The
server:

1. Resolves the session by its hash; rejects unknown, revoked, or expired
   sessions with `401`.
2. Resolves tenant/event/kiosk identity entirely from the matched session
   record — never from a client-supplied tenant/event identifier.
3. Rejects any request whose target event/tenant differs from the kiosk's
   own registration with the same response as an unknown target (CR-001),
   never disclosing which part of the mismatch failed.

## Heartbeat

Input: kiosk session auth, current printer health category (from
`printer-adapter.md` §Health), app/software version.

Effect: updates `Kiosk.last_heartbeat_at`, `Kiosk.printer_status`, and
derives `Kiosk.status` (`online` if within
`EventCheckInSetting.kiosk_offline_threshold_seconds`, else `offline`; a
reported printer fault marks `degraded` even while heartbeats continue).

## Attendee Lookup

Input: kiosk session auth (or authenticated staff session for the manual
desk), one of `qr_payload` or a `name`/`email`/`phone` query fragment.

Behavior:

- `qr_payload` present: parsed and validated exactly as in
  `scan-contract.md` step 1; a non-`valid` result is returned as the same
  stable category without proceeding to a name/email/phone search.
- Name/email/phone fragment present: returns matching attendees scoped to
  the kiosk's (or staff session's) authenticated event/tenant only,
  including each match's current credential reference and check-in status,
  reusing `Scanning`'s bounded lookup query (`research.md` Decision 3).
  Result set size is bounded; an overly broad fragment returns a stable
  "too many matches, refine search" response rather than an unbounded list.
- If `EventCheckInSetting.lookup_confirmation_required` is true and the
  request did not use `qr_payload`, the response indicates a confirmation
  is required and a one-time code is dispatched through the existing
  notification adapter (`research.md` Decision 4) before check-in may
  proceed for that attendee.

## Check-In

Input: kiosk session auth (scanner_type `kiosk`) or authenticated staff
session (scanner_type `manual_desk`), the resolved credential reference
from Lookup, optional `override`/`override_reason` (staff/manual desk only
— a kiosk session alone never carries override permission).

Behavior: delegates directly to the unchanged Phase 2 `SubmitScanAction`
with the resolved `scanner_type` and `scanner_id` (kiosk id or staff user
id). The response is the exact stable result set from `scan-contract.md`;
no kiosk- or desk-specific result value exists.

## Badge Print Trigger

After an `accepted` or `manual_override` check-in, the kiosk or desk may
request a badge print for the same attendee/credential, which follows
`badge-contract.md` unchanged. A kiosk/desk MUST NOT print before check-in
completes with an entry-allowed result (FR requires check-in to gate
printing at the manual desk and kiosk alike).

## Stable Response Categories (kiosk/desk-specific, in addition to scan-contract.md)

| Category | Meaning |
|---|---|
| `kiosk_session_invalid` | Session secret unknown, revoked, or expired |
| `kiosk_session_unconfirmed` | Session requires the PIN/one-time-code confirmation step before use |
| `kiosk_retired` | Kiosk has been decommissioned |
| `lookup_too_many_matches` | Name/email/phone fragment matched more results than the bounded limit; caller must refine |
| `lookup_confirmation_required` | A one-time code must be confirmed before check-in may proceed for this lookup match |
| `lookup_confirmation_invalid` | Submitted one-time code did not match or expired |

## Tenant Isolation and Data Handling

- A kiosk session never carries or infers a tenant/event identifier from
  the request; it is resolved solely from the matched, non-revoked session
  record.
- The kiosk device-session secret is treated as sensitive: excluded from
  logs, telemetry, audit metadata, and any API response after the one-time
  pairing response.
- Lookup responses return only display name, ticket type, and check-in
  status — the same minimum Phase 2 already permits for staff scanning —
  never full contact details, national identifiers, or payment data, even
  though the lookup was performed by name/email/phone.
- A cross-tenant or cross-event credential/attendee reference produces the
  same rejected/not-found response as an unknown reference in every
  operation above.

## Contract Test Matrix

Every implementation must pass:

1. Pairing issues a session usable exactly once as the shown raw secret;
   the stored record never allows recovering that raw value.
2. Pairing a new session for a kiosk revokes the previous session
   immediately.
3. An unconfirmed session (when `confirmation_required`) is rejected for
   scan/lookup/print until confirmed.
4. A retired kiosk's session (even if not yet expired) is rejected for
   every operation.
5. A kiosk session naming (directly or via a resolved credential) a
   different tenant/event than its own registration is rejected
   identically to an unknown target.
6. Lookup by `qr_payload` and by name/email/phone both resolve to the same
   downstream check-in decision order and stable result set.
7. A lookup fragment matching more than the bounded limit returns
   `lookup_too_many_matches` rather than an unbounded list.
8. When `lookup_confirmation_required` is enabled, check-in is blocked
   until a valid one-time code is confirmed, and an expired/incorrect code
   is rejected without disclosing which check failed.
9. Kiosk-originated and manual-desk-originated check-ins produce
   `ScanEvent` rows with `scanner_type` of `kiosk`/`manual_desk`
   respectively and no new `result` value outside `scan-contract.md`'s set.
10. Every kiosk pairing, confirmation, heartbeat-derived status change,
    lookup, and check-in is audited with actor type (`kiosk` or the staff
    user), tenant, event, action, outcome, and correlation, without
    leaking the session secret.
