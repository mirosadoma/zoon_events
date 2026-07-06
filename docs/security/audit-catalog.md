# Phase 2 audit action catalog

Owner: Security Engineering  
Last reviewed: 2026-07-06

Phase 0/1 action families remain in `docs/standards/audit-event-catalog.md`. Every
Phase 2 row records scope, actor, target, outcome, stable reason, correlation, channel,
fingerprints, sanitized metadata, key ID, algorithm, and HMAC. Provider certificates,
private keys, service-account JSON, signed pass payloads, and raw QR tokens never enter
audit metadata.

## `wallet_pass.*`

| Action | Outcome | Target | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- |
| `wallet_pass.generated` | succeeded | `wallet_pass` | `event_id`, `provider`, `credential_id` | Written on successful pass creation |
| `wallet_pass.generation_denied` | failed | `wallet_pass` | `event_id`, `provider`, stable reason | Credential not active or policy denial before provider call |
| `wallet_pass.updated` | succeeded | `wallet_pass` | `event_id`, `provider` | Provider push/update completed |
| `wallet_pass.update_failed` | failed | `wallet_pass` | `event_id`, `provider`, stable reason | Bounded retry; no provider payload |
| `wallet_pass.revoked` | succeeded | `wallet_pass` | `event_id`, `provider` | Revocation propagated |
| `wallet_pass.revocation_failed` | failed | `wallet_pass` | `event_id`, `provider`, stable reason | Bounded retry; no provider payload |

## `scan.*`

| Action | Outcome | Target | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- |
| `scan.accepted` | succeeded | `scan_event` | `event_id`, `credential_id`, reason | Entry granted |
| `scan.duplicate` | succeeded | `scan_event` | `event_id`, `credential_id`, reason | Includes offline conflict downgrade |
| `scan.revoked` | succeeded | `scan_event` | `event_id`, `credential_id`, reason | Revoked credential presented |
| `scan.expired` | succeeded | `scan_event` | `event_id`, `credential_id`, reason | Expired credential presented |
| `scan.rejected` | succeeded | `scan_event` | `event_id`, `credential_id`, reason | Malformed or cross-context credential |
| `scan.manual_override` | succeeded | `scan_event` | `event_id`, `credential_id`, `override_reason` | Requires `checkin.scan.override`; never stored as `accepted` |

Scan evidence is synchronous inside the scan audited transaction. Queue failure cannot
remove or rewrite required scan evidence.

## `offline_scan_batch.*`

| Action | Outcome | Target | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- |
| `offline_scan_batch.received` | succeeded | `offline_scan_reconciliation_batch` | `event_id`, `submitted_scan_count` | Batch accepted for reconciliation |
| `offline_scan_batch.processed` | succeeded | `offline_scan_reconciliation_batch` | `event_id`, `accepted_count`, `duplicate_count`, `conflict_count` | Final batch status recorded in reason |
| `offline_scan_batch.conflict_flagged` | succeeded | `offline_scan_reconciliation_batch` | `event_id`, `credential_id`, reason | Earliest `scanned_at` wins across batches |

## `checkin_dashboard.*`

| Action | Outcome | Target | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- |
| `checkin_dashboard.viewed` | succeeded | `event` | `event_id` | Optional; emit only where tenant policy requires access logging beyond standard request telemetry |
