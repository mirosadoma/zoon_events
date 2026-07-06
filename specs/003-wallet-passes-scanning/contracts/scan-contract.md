# Scan Validation and Check-In Contract

## Purpose

Define the authoritative, provider-agnostic decision a staff scan receives,
extending the Phase 1 credential validation contract
(`specs/002-registration-ticketing-credentials/contracts/credential-contract.md`)
with entry/check-in semantics. This contract is also the foundation Phase 3
(kiosk) and Phase 4 (ACS) reuse; Phase 2 implements only the rows and result
categories in scope below.

## Relationship to the Credential Contract

A scan always begins with the unchanged Phase 1 validation order and stable
results (`valid`, `malformed`, `invalid_signature`, `unknown_key`, `expired`,
`revoked`, `superseded`, `wrong_tenant`, `wrong_event`,
`service_unavailable`). This contract adds what happens **after** a
credential is found `valid`: single-entry evaluation, scan-event recording,
and check-in state update.

## Invocation Context

Every scan submission carries trusted:

- tenant and event identifiers (from the authenticated staff session/device,
  never from the scanned payload);
- scanner actor identity and `scanner_type` (`staff_phone`,
  `handheld_scanner` in Phase 2);
- the raw scanned QR payload;
- optional `override` flag and `override_reason` (requires a separate
  permission, see below);
- `offline_mode` and `scanned_at` when submitting a reconciled offline scan.

## Decision Order

1. Parse and validate the credential per the Phase 1 credential contract.
2. If the credential result is not `valid`, map it directly to a stable scan
   result (`revoked` → `revoked`, `expired` → `expired`, all other non-valid
   results → `rejected`) and skip to step 6.
3. Confirm the credential's event matches the scanning context's event; a
   mismatch produces `rejected` with a generic reason, identical to an
   unknown credential (never disclosing the credential's real event/tenant).
4. Evaluate single-entry enforcement using the event's
   `EventCheckInSetting`:
   - If disabled, or this is the credential's first `accepted`/
     `manual_override` scan in the configured scope, continue to step 5.
   - If a prior `accepted` (or `manual_override`) scan already exists in the
     enforced scope and no valid `override` is supplied, the result is
     `duplicate`.
   - If a valid `override` (permission + reason present) is supplied, the
     result is `manual_override`.
5. Result is `accepted`.
6. Record one immutable `ScanEvent` with the determined result and reason.
7. If the result is `accepted` or `manual_override`, update the attendee's
   `checkin_status` and `first_checked_in_at` (if not already set) and
   increment `EventCheckInSummary.checked_in_count`; otherwise increment the
   matching non-accepted counter. All of step 6 and 7 commit in one audited
   transaction (`research.md` Decision 7).
8. After commit, dispatch (a) an audit record and (b) any wallet
   synchronization implied by a credential-state change already handled by
   Phase 1's revoke/reissue actions (this contract does not itself revoke or
   reissue credentials).

## Stable Scan Results

| Result | Meaning | Entry allowed |
|---|---|---|
| `accepted` | Valid credential, first entry in scope | Yes |
| `manual_override` | Valid credential, would be duplicate, authorized override applied | Yes |
| `duplicate` | Valid credential, already used in the enforced scope, no override | No |
| `revoked` | Credential is authoritatively revoked | No |
| `expired` | Credential is past its authoritative expiry | No |
| `rejected` | Malformed, unknown, wrong scope, or otherwise invalid | No |

`unauthorized_zone` and `anti_passback_rejected` are reserved result values
that remain unused until Phase 4 introduces zone/lane authorization; Phase 2
scan submissions never produce them.

## Response Contract

A scan response contains only:

- the stable result category;
- a stable safe reason code;
- for `accepted`/`manual_override`: attendee display name and ticket type
  label (the minimum staff need to confirm the right person), never full
  contact details, national identifiers, or payment data;
- a scan event identifier for support/audit correlation.

Rejected/duplicate/revoked/expired responses never reveal which specific
check failed if doing so would disclose cross-tenant or cross-event
information; internal logs may retain the specific check for support use.

## Manual Override

- Requires an explicit permission distinct from ordinary scan submission.
- Requires a non-empty reason.
- Produces `manual_override`, never silently reclassified as `accepted`, in
  every stored record, audit entry, and dashboard count.
- Is itself audited with the overriding actor, reason, and the credential
  that was overridden.

## Offline-Originated Scans

A scan submitted with `offline_mode: true` follows the same decision order at
reconciliation time, using server-authoritative state as it exists during
reconciliation (not the state at the original offline scan time). Two
independently offline-accepted scans of the same credential reconcile as:
one `accepted` (the earliest by `scanned_at`) and one `duplicate` carrying a
reason indicating post-hoc conflict resolution; the owning
`OfflineScanReconciliationBatch.conflict_count` is incremented for both
submitting batches.

## Dashboard Aggregation Contract

`EventCheckInSummary` is read through a bounded, tenant/event-scoped query
returning `registered_count`, `checked_in_count`, `rejected_count`,
`duplicate_count`, and `last_scan_at`. It is never computed by scanning
unbounded `ScanEvent` history at request time in the request path; the
summary row is maintained transactionally as scans are recorded (see
`data-model.md`).

## Contract Test Matrix

Every scan-submission implementation must pass:

1. Accepted, duplicate, revoked, expired, and rejected results for their
   respective credential/history fixtures.
2. Cross-tenant and cross-event credentials produce `rejected` responses
   indistinguishable from an unknown credential.
3. Manual override requires its own permission and reason, and is never
   recorded as `accepted`.
4. Scan result, check-in state update, and audit record commit or fail
   together (forced audit failure leaves zero partial state).
5. Concurrent scans of the same credential at the same instant produce
   exactly one `accepted` and correctly reject the other as `duplicate`.
6. Offline reconciliation of a genuine conflict produces exactly one
   `accepted` and flags the rest, never two `accepted` results for one
   credential.
7. Dashboard counts reflect only `accepted`/`manual_override` scans in
   `checked_in_count` across all fixtures.
