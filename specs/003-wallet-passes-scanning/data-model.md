# Phase 2 Data Model

**Feature**: Wallet Passes and QR Scanning
**Database**: MySQL 8.4, shared schema, tenant-first ownership

## Conventions

Phase 2 reuses every Phase 0/Phase 1 convention: 26-character ULIDs, non-null
`tenant_id` on every record, non-null `event_id` on every event-owned record,
composite foreign keys enforcing same-tenant/same-event ownership, UTC
microsecond timestamps, database-checked status enums, `created_at`-only
immutable evidence rows, and `created_at`/`updated_at` on mutable rows.

## Entity Relationships

```text
Tenant
  └─ Event
      ├─ EventCheckInSetting (one row; extends Event configuration)
      ├─ EventCheckInSummary (one row; dashboard read model)
      ├─ Attendee (Phase 1, extended with check-in fields)
      │   └─ Credential (Phase 1, referenced only)
      │       └─ WalletPass (0..n across provider/lifetime, 1 active per provider)
      │           └─ WalletPassAppleDeviceRegistration (Apple only, 0..n)
      └─ ScanEvent (append-only, 1 per scan attempt)
          └─ OfflineScanReconciliationBatch (only for offline-originated scans)
```

## Wallet Pass

Tenant- and event-owned record of one issued wallet artifact referencing an
existing Phase 1 credential. Never a second trust path (see `research.md`
Decision 2).

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `attendee_id` | Same scope; owning attendee |
| `credential_id` | Same scope; the credential this pass renders, never copied/duplicated |
| `provider` | `apple`, `google` |
| `pass_serial_number` | Opaque, unique per `(tenant_id, provider)`; used as Apple `serialNumber` or the suffix of the Google `resourceId` |
| `pass_url` | Non-secret reference to the retrievable pass artifact: the Apple pass-fetch URL or the Google "Add to Google Wallet" save link; never the raw certificate/JWT signing material |
| `status` | `created`, `active`, `updated`, `revoked`, `expired`, `failed` |
| `last_pushed_at` | Last successful provider push/update attempt time |
| `last_push_reason_code` | Safe category when the most recent push failed |
| `superseded_by_id` | Set when a reissue creates a replacement pass for the same attendee/provider; same tenant/event; acyclic |
| `created_at`, `updated_at` | Standard |

Indexes: `(tenant_id, event_id, attendee_id, provider)`,
`(tenant_id, credential_id)`.

Constraints:

- At most one `active` wallet pass per `(tenant_id, attendee_id, provider)`.
  Reissue supersedes the prior row before or in the same transaction as
  creating the replacement.
- `pass_serial_number` is immutable after creation.
- A wallet pass may only be created when its referenced credential is
  currently `active` (FR-005); the check is re-verified at creation time,
  not cached from an earlier read.

Lifecycle:

```text
created -> active -> updated -> active   (repeatable update cycle)
active/updated -> revoked                (credential revoked)
active/updated -> expired                (event/credential expiry reached)
created -> failed                        (initial generation/signing failed)
```

## Wallet Pass Apple Device Registration

Apple-specific bookkeeping required by the PassKit web-service update
protocol (`research.md` Decision 3). Not used for the Google provider.

| Field | Rules |
|---|---|
| `id`, `tenant_id` | Required |
| `wallet_pass_id` | Same tenant; the Apple wallet pass this registration updates |
| `device_library_identifier` | Device-supplied identifier; unique per `wallet_pass_id` |
| `push_token` | APNs push token; treated as sensitive and never logged |
| `registered_at` | Required |
| `unregistered_at` | Set when the device unregisters or APNs reports an invalid token |

Index: `(tenant_id, wallet_pass_id, unregistered_at)` for push fan-out queries
that must only target currently registered devices.

A device may register the same pass again after reinstall; the existing row
is updated rather than duplicated when `device_library_identifier` matches.

## Scan Event

Immutable, tenant- and event-scoped record of one scan attempt, online or
reconciled-from-offline.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `attendee_id` | Set when the scanned credential resolves to a known attendee in scope; null for rejected unknown/malformed/cross-scope scans |
| `credential_id` | Set when the token parses to a syntactically valid credential reference in scope; null otherwise |
| `scanner_type` | `staff_phone`, `handheld_scanner` in Phase 2; `kiosk`, `gate`, `acs_lane`, `manual_desk` remain reserved values for Phase 3/4 producers reusing this table |
| `scanner_id` | Authenticated staff/service actor reference |
| `gate_id`, `zone_id` | Reserved nullable columns for Phase 4 ACS; always null in Phase 2 |
| `direction` | `in` in Phase 2; `out` reserved for Phase 4 |
| `result` | `accepted`, `rejected`, `duplicate`, `revoked`, `expired`, `manual_override`; `unauthorized_zone` and `anti_passback_rejected` are reserved enum values not produced until Phase 4 |
| `reason` | Stable safe reason code, always present for non-`accepted` results |
| `offline_mode` | `true` when originally recorded on a disconnected device |
| `scanned_at` | Client- or server-observed scan time |
| `synced_at` | Null for online scans; set when an offline scan is reconciled |
| `created_at` | Server insert time; immutable evidence |

Indexes: `(tenant_id, event_id, credential_id, created_at, id)` for duplicate
lookup, `(tenant_id, event_id, result, created_at)` for dashboard aggregation.

No update or delete path exists at the application layer; scan events are
append-only like audit records.

## Event Check-In Setting

One configuration row per event controlling duplicate-entry enforcement.

| Field | Rules |
|---|---|
| `tenant_id`, `event_id` | Required; one row per event |
| `single_entry_enabled` | Boolean; default `true` per `all_plan.md` §17.4 default expectation of rejecting duplicates |
| `single_entry_scope` | `event`, `ticket_type`; determines whether duplicate detection considers the whole event or resets per ticket type |
| `created_at`, `updated_at` | Standard |

## Event Check-In Summary

Dashboard read model updated inside or immediately after the same
transaction as an accepted/rejected scan (`research.md` Decision 8).

| Field | Rules |
|---|---|
| `tenant_id`, `event_id` | Required; one row per event |
| `registered_count` | Mirrors current attendee count for the event |
| `checked_in_count` | Count of attendees with an `accepted` or `manual_override` scan result |
| `rejected_count`, `duplicate_count` | Non-accepted scan counters, tracked separately so they are never added to `checked_in_count` |
| `last_scan_at` | Most recent scan of any result |
| `updated_at` | Standard |

This table exists purely for bounded, fast dashboard reads; `ScanEvent` and
`Attendee` remain the source of truth and a repair job may recompute it from
them.

## Attendee (Phase 1 extension)

Phase 1's `data-model.md` explicitly reserved check-in state for a later
phase. Phase 2 adds:

| Field | Rules |
|---|---|
| `checkin_status` | `not_checked_in`, `checked_in`; default `not_checked_in` |
| `first_checked_in_at` | Set once, on the first `accepted` or `manual_override` scan; never overwritten by a later scan |
| `last_scan_event_id` | Most recent scan event affecting this attendee, of any result, for support/audit traceability |

No other Phase 1 attendee field changes meaning; financial and submission
history remain untouched by this phase.

## Offline Scan Reconciliation Batch

Tracks one device's offline-to-online sync per `research.md` Decision 9.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `device_reference` | Opaque scanning-device identifier |
| `allowlist_issued_at`, `allowlist_expires_at` | Bounds the event/time window the device was authorized to scan offline |
| `submitted_scan_count` | Number of locally recorded scans included in this batch |
| `accepted_count`, `duplicate_count`, `conflict_count` | Reconciliation outcome tallies |
| `status` | `received`, `processed`, `processed_with_conflicts` |
| `created_at`, `processed_at` | Standard |

A conflict (two offline devices independently accepting the same credential
during the same connectivity gap) produces a `ScanEvent` result reflecting
the resolution (one `accepted`, one flagged `duplicate` with a reason
indicating post-hoc conflict resolution) and increments `conflict_count`;
conflicts are never silently discarded.

## Cross-Entity Invariants

1. A record may reference only records sharing its `tenant_id`; event-owned
   records must also share `event_id`; a wallet pass or scan event whose
   resolved credential belongs to a different tenant or event than the
   request context is rejected before persistence, not merely filtered from
   results.
2. A wallet pass never stores or re-derives credential validity; every
   generation, update, and revocation decision re-reads the authoritative
   Phase 1 credential status at the moment of the action.
3. `checked_in_count` in `EventCheckInSummary` only ever increases from an
   `accepted` or `manual_override` `ScanEvent`; `rejected`, `duplicate`,
   `revoked`, and `expired` results never increment it.
4. At most one `active` wallet pass exists per attendee and provider; a
   reissued credential's prior wallet pass is superseded, never left
   ambiguously active alongside its replacement.
5. Scan events are immutable once created; corrections happen only by
   recording a new scan event (e.g., a staff override), never by editing
   history.
6. Offline reconciliation never creates two `accepted` scan events for the
   same credential from the same connectivity gap without recording the
   conflict.
7. Cross-tenant and cross-event wallet pass or credential identifiers used in
   a scan or wallet request produce identical rejected/not-found behavior to
   an unknown identifier.
8. Audit and telemetry for wallet and scan actions receive identifiers,
   classifications, and safe reason codes, never raw device push tokens,
   attendee personal data beyond what Phase 1 already permits, or wallet
   provider payloads/certificates.
