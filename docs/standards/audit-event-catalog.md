# Audit event catalog

Owner: Security Engineering  
Last reviewed: 2026-07-03

Catalog categories include authentication/session/token outcomes, authorization denial,
tenant/user/membership lifecycle, role/permission/assignment changes, privileged recovery,
audit search/export/download/integrity, feature-flag governance, configuration governance,
and adapter failure. Events record scope, actor, target, outcome, stable reason, correlation,
channel, fingerprints, sanitized changed fields, key ID, algorithm, and HMAC.

Phase 1 required action families are:

- `event.published|reopened|archived`, `registration_form.published`, and
  `ticket_type.created|updated|archived`;
- `inventory.held|sold|released|expired`;
- `registration.free_completed`;
- `payment.pending|captured|failed|unknown` and
  `refund.pending|succeeded|failed|unknown`;
- `attendee.corrected`;
- `credential.revoked|reissued` and validation denial evidence;
- `notification.delivered|permanent_failure`.

Authenticated callback denial evidence uses `payment.callback_denied` and
`notification.callback_denied` with a stable reason and no route token,
signature, destination, provider identifier, or payload.

Notification evidence contains only event ID, channel, notification ID, outcome,
and stable reason. Payment evidence excludes provider payload and card data.
Credential evidence excludes signed tokens. Attendee evidence records changed
field names, never old/new values.

Phase 2 required action families:

- `wallet_pass.generated|generation_denied|updated|update_failed|revoked|revocation_failed`;
- `scan.accepted|rejected|duplicate|revoked|expired|manual_override`;
- `offline_scan_batch.received|processed|conflict_flagged`;
- `checkin_dashboard.viewed` (optional per tenant access-logging policy).

Wallet pass evidence excludes provider payloads and certificate material. Scan evidence
excludes attendee PII beyond stable identifiers. Full Phase 2 tables live in
`docs/security/audit-catalog.md`.
