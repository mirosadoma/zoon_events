# Check-In Operations Runbook

Staff QR scanning, duplicate handling, and dashboard counters share one authoritative
decision order implemented by `ScanDecisionEvaluatorImpl` and replayed during offline
batch reconciliation.

## Scan decision order

Evaluation runs in this fixed order for every online scan and offline replay:

1. **Credential validation** — parse and verify the `zt1` QR payload against tenant,
   event, and signing keys. Failures map to:
   - `expired` / `credential_expired`
   - `revoked` / `credential_revoked`
   - `rejected` / `credential_invalid` (malformed, wrong tenant/event, or unknown)
2. **Single-entry duplicate check** — when enabled, a prior `accepted` or
   `manual_override` for the credential (per scope) yields `duplicate` /
   `already_checked_in`.
3. **Manual override branch** — when duplicate would occur, the scan request includes
   `override: true`, a non-empty `override_reason`, and the scanner holds
   `checkin.scan.override`, the result is `manual_override` / `duplicate_overridden`
   (never stored as `accepted`).
4. **Accept** — otherwise the scan is `accepted` / `entry_granted`.

Accepted and manual-override results update attendee check-in state and dashboard
counters. Rejected, revoked, expired, and duplicate results do not increment checked-in
counts.

Scan submission and audit evidence run in one audited database transaction. If audit
evidence fails, no `scan_events` row or check-in mutation is persisted.

## Single-entry configuration

Per-event settings live in `event_check_in_settings`:

| Field | Values | Default | Effect |
| --- | --- | --- | --- |
| `single_entry_enabled` | boolean | `true` (or `wallet.single_entry_default_enabled`) | When `false`, duplicate detection is skipped |
| `single_entry_scope` | `event`, `ticket_type` | `event` | `event`: any prior accepted scan for the credential blocks re-entry; `ticket_type`: duplicate detection is limited to the same ticket type |

Configure before doors open. Changes apply to subsequent scans only; existing
`scan_events` rows remain authoritative history.

## Manual override procedure

Use only when staff must admit an attendee who already has an accepted scan (lost
device, escort correction, operations exception):

1. Confirm the prior scan in the dashboard or audit log (`scan.accepted` or
   `scan.manual_override`).
2. Grant `checkin.scan.override` to the scanner role if not already present.
3. Submit the scan with `override: true` and a short operational `override_reason`
   (required; unknown fields are rejected).
4. Verify the API returns `manual_override`, audit shows `scan.manual_override` with
   the reason, and dashboard counters reflect manual overrides separately from accepted
   scans.

Overrides never downgrade an earlier accepted scan. Offline batches may include override
flags per scan item; reconciliation applies the same permission and reason rules at
replay time.

## Related operations

- Offline allowlist export and batch reconciliation: `docs/operations/offline-scanning-design.md`
- Dashboard polling and summary repair: `zonetec:checkin:refresh-summary --event={id}`
- Credential revocation is immediate: revoked credentials return `scan.revoked` on next scan
