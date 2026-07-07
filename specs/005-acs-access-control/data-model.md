# Phase 4 Data Model

**Feature**: ACS and Access Control
**Database**: MySQL 8.4, shared schema, tenant-first ownership

## Conventions

Phase 4 reuses every Phase 0/1/2/3 convention: 26-character ULIDs, non-null
`tenant_id` on every record, non-null `event_id` on every event-owned record,
composite foreign keys enforcing same-tenant/same-event ownership, UTC
microsecond timestamps, database-checked status enums, `created_at`-only
immutable evidence rows where applicable, and `created_at`/`updated_at` on
mutable rows.

## Entity Relationships

```text
Tenant
  └─ Event
      ├─ AcsZone (0..n)
      │   ├─ AcsLane (0..n)
      │   ├─ AcsAuthorizationRule (0..n; may also target a specific lane)
      │   ├─ AntiPassbackState (0..n; one per credential currently tracked)
      │   └─ EmergencyEvent (0..n; zone-scoped or event-wide)
      ├─ AcsAuthorizationRule (references TicketType, AcsZone, AcsLane)
      ├─ AccessEvent (0..n)
      │   ├─ references Credential (nullable for unknown), AcsZone, AcsLane
      │   └─ scan_event_id -> ScanEvent (admission lanes only; Phase 2)
      ├─ Credential (Phase 1, referenced only)
      ├─ TicketType (Phase 1, referenced only)
      └─ ScanEvent (Phase 2, referenced: `scanner_type = 'acs_gate'`)
```

## AcsZone

Tenant- and event-owned controlled area, linked to an external ACS zone.
(Extends `all_plan.md` §12.17 with access-control mode fields.)

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `name` | Human-readable label |
| `external_acs_zone_id` | Opaque identifier from the external ACS; unique per `(tenant_id, event_id)`; used to resolve a zone from an ACS reference |
| `anti_passback_enabled` | Boolean; default `false`; whether re-entry requires a recorded exit (`research.md` Decision 5) |
| `unavailability_mode` | `fail_open`, `fail_closed`; default `fail_closed`; behavior when the ACS dependency is unreachable/latency-exceeded (`research.md` Decision 7) |
| `emergency_egress_mode` | `fail_open`, `fail_closed`; default `fail_open`; behavior while an emergency signal is active |
| `status` | `active`, `inactive` |
| `created_at`, `updated_at` | Standard |

Indexes: `(tenant_id, event_id, status)`, unique `(tenant_id, event_id,
external_acs_zone_id)`.

## AcsLane

Tenant- and event-owned gate/lane within a zone, linked to an external ACS
lane. (Extends `all_plan.md` §12.18.)

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `zone_id` | Same tenant/event; the zone this lane belongs to |
| `name` | Human-readable label |
| `external_acs_lane_id` | Opaque identifier from the external ACS; unique per `(tenant_id, event_id)` |
| `gate_type` | e.g. `turnstile`, `door`, `speedgate`, `manual`; validated against a supported-values list |
| `access_direction` | Supported direction(s): `entry`, `exit`, `bidirectional` |
| `is_admission_lane` | Boolean; default `false`; when true, an allowed entry also records a Phase 2 check-in via `SubmitScanAction` (`research.md` Decision 9) |
| `status` | `active`, `inactive` |
| `health_status` | `online`, `degraded`, `offline`; derived from adapter heartbeat/last contact |
| `last_seen_at` | Last time the ACS reported/contacted this lane; drives health derivation |
| `created_at`, `updated_at` | Standard |

Indexes: `(tenant_id, event_id, zone_id, status)`, unique `(tenant_id,
event_id, external_acs_lane_id)`.

## AcsAuthorizationRule

Tenant- and event-owned rule mapping ticket/attendee type to permitted zone/
lane/direction/time window. (Extends `all_plan.md` §12.19.)

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `ticket_type_id` | Nullable; same scope; the Phase 1 ticket type this rule applies to (null = any ticket type) |
| `attendee_type` | Nullable; e.g. `attendee`, `staff`, `vip`, `vendor` (null = any) |
| `zone_id` | Same scope; the permitted zone |
| `lane_id` | Nullable; same scope/zone; when set, restricts the rule to one lane (null = any lane in the zone) |
| `access_direction` | `entry`, `exit`, `bidirectional` |
| `anti_passback_exempt` | Boolean; default `false`; per-ticket-type anti-passback exemption (`research.md` Decision 5) |
| `valid_from`, `valid_until` | Nullable UTC bounds; null = unbounded on that side |
| `status` | `active`, `inactive` |
| `created_at`, `updated_at` | Standard |

Index: `(tenant_id, event_id, zone_id, status)`; evaluation resolves the
applicable rules for a `(ticket_type, attendee_type, zone, lane, direction,
now)` tuple.

A credential is `allowed` by the rule layer only if at least one `active`
rule permits the presented `(zone, lane, direction)` for its ticket/attendee
type within the time window; otherwise the decision denies with
`zone_not_permitted`, `lane_not_permitted`, or `outside_time_window` (the
most specific matched-but-failed reason).

## AccessEvent

Tenant- and event-scoped, append-only record of one authorization decision or
one entry/exit/emergency event. (Realizes the "access event" of `all_plan.md`
§19.3 and the entry/exit/ACS event stream of §36.)

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `event_type` | `decision`, `entry`, `exit`, `emergency` |
| `credential_id` | Nullable (null for an unknown/unresolvable credential reference); same scope when set |
| `zone_id` | Same scope; nullable only for an event-wide emergency |
| `lane_id` | Same scope; nullable for an event-wide emergency |
| `direction` | `entry`, `exit`, `none` (for `decision`/`emergency` where not directional) |
| `decision` | `allow`, `deny`, `n/a`; set for `event_type = decision`, `n/a` otherwise |
| `reason_code` | Stable machine-readable code (see Reason Codes); language-neutral |
| `source` | `acs_gate`, `operator`, `system`; who/what produced the event |
| `external_event_id` | Nullable; the ACS-supplied idempotency key for ingested entry/exit callbacks |
| `scan_event_id` | Nullable; links to the Phase 2 `ScanEvent` when an admission lane also recorded a check-in (`research.md` Decision 9) |
| `occurred_at` | Event time as reported by the ACS (or server time for operator/system events); used for anti-passback ordering |
| `created_at` | Standard; append-only, no `updated_at` |

Indexes: `(tenant_id, event_id, occurred_at)` for the gate-event feed,
`(tenant_id, event_id, credential_id, zone_id, occurred_at)` for anti-passback
reconciliation, unique `(tenant_id, external_event_id)` where
`external_event_id` is not null (idempotency, `research.md` Decision 6).

## AntiPassbackState

Tenant- and event-scoped materialized inside/outside state per credential per
zone, derived from ordered entry/exit `AccessEvent` rows (`research.md`
Decision 5).

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `credential_id` | Same scope; the tracked credential |
| `zone_id` | Same scope; the zone whose occupancy is tracked |
| `state` | `inside`, `outside` |
| `last_access_event_id` | The `AccessEvent` that last set this state |
| `last_transition_at` | `occurred_at` of the last state-changing event |
| `created_at`, `updated_at` | Standard |

Unique index: `(tenant_id, event_id, credential_id, zone_id)` — at most one
state row per credential per zone. This row is a derived cache; the
`AccessEvent` stream remains authoritative and can rebuild it.

## EmergencyEvent

Tenant- and event-scoped record of an emergency-egress signal and the applied
behavior (`all_plan.md` §19.5).

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `zone_id` | Nullable; the affected zone, or null for an event-wide emergency |
| `signal_source` | `operator`, `acs`, `fire_alarm`, `system` |
| `behavior_applied` | `fail_open`, `fail_closed`; the mode applied for affected zones at raise time |
| `raised_at` | When the emergency became active |
| `cleared_at` | Nullable; set when the emergency is cleared and normal decisioning resumes |
| `created_at`, `updated_at` | Standard |

Index: `(tenant_id, event_id, cleared_at)` for "currently active emergencies"
lookup. While an emergency is active (`cleared_at IS NULL`) for a zone
configured `emergency_egress_mode = fail_open`, entry and exit decisions at
affected lanes return `allow` with reason `emergency_fail_open`.

## AcsIntegrationCredential (M2M)

Registration of the external ACS as a machine-to-machine actor (`research.md`
Decision 4). Reuses the Phase 0 integration-credential store if one exists;
otherwise a dedicated table with these fields.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required; scopes the integration to one event |
| `name` | Operator-facing label |
| `secret_hash` | Hashed M2M secret; raw value shown to the registering operator exactly once, never stored or logged |
| `capabilities` | Allowlist: `authorize`, `event.ingest`, `emergency.ingest` |
| `status` | `active`, `revoked` |
| `expires_at`, `revoked_at` | Bounded lifetime; rotation revokes and replaces |
| `created_at`, `updated_at` | Standard |

The integration credential resolves tenant/event scope; a referenced lane/
zone must belong to that event or the request is rejected as an unknown
target.

## Credential (Phase 1, referenced not redefined)

Unchanged. Gate authorization consumes the existing Phase 1 validation
contract (signature, expiry, revocation, replay, key rotation); Phase 4 never
persists or re-derives credential validity, matching Phase 2/3 precedent.

## Scan Event (Phase 2, referenced/extended)

`scanner_type = 'acs_gate'` identifies a check-in recorded because an
admission `AcsLane` allowed an entry (`research.md` Decision 9). If Phase 2's
`scanner_type` check constraint does not already reserve `acs_gate`, a single
check-constraint extension migration adds it; no new `ScanEvent.result` value
is introduced.

## Cross-Entity Invariants

1. A record may reference only records sharing its `tenant_id`; event-owned
   records must also share `event_id`. An authorization request, event
   callback, or configuration write naming a different tenant/event than its
   authenticated/mapped context fails closed and returns a response
   indistinguishable from an unknown target (CR-001).
2. The credential-validity portion of every `decision` reuses the exact
   Phase 1 validation and Phase 2 scan decision order; no new credential
   trust path or `ScanEvent.result` value is introduced (CR-004).
3. `AccessEvent` is append-only; corrections happen by recording new events,
   never by editing history, matching `ScanEvent`'s Phase 2 pattern.
4. An ingested entry/exit callback with an `external_event_id` already seen
   for the tenant is an idempotent no-op — no duplicate `AccessEvent`, no
   double state transition, no double occupancy (CR-006, `research.md`
   Decision 6).
5. `AntiPassbackState` is fully derivable from the ordered `AccessEvent`
   stream; if the cache and stream disagree, the stream wins and the repair
   job rebuilds the cache.
6. A `decision` with `decision = deny` always carries a specific
   `reason_code`; an `allow` may carry `allowed`, `emergency_fail_open`, or
   `acs_unavailable_fail_open`.
7. While an emergency is active for a zone configured `fail_open`, entry and
   exit decisions at affected lanes return `allow`/`emergency_fail_open`
   regardless of anti-passback or time-window rules; the emergency event and
   each affected decision are auditable.
8. The external ACS never receives signing keys, a raw credential payload, or
   a re-scoped credential — only an allow/deny decision and reason code.
9. Audit and telemetry for ACS actions receive identifiers, classifications,
   and safe reason codes, never a raw M2M secret, ACS transport payload, or
   attendee personal data beyond what Phase 1/2/3 already permit.

## Reason Codes

Language-neutral codes recorded on `AccessEvent.reason_code` and returned in
authorization responses (see `contracts/authorization-contract.md`):

| Reason code | Decision | Meaning |
|---|---|---|
| `allowed` | allow | Credential valid and a rule permits this zone/lane/direction/time |
| `credential_expired` | deny | Credential past expiry |
| `credential_revoked` | deny | Credential revoked |
| `credential_unknown` | deny | Credential not found / unresolvable / unknown key |
| `zone_not_permitted` | deny | No active rule permits this zone for the ticket/attendee type |
| `lane_not_permitted` | deny | Rule permits the zone but not this lane |
| `outside_time_window` | deny | Presented outside the rule's valid_from/valid_until |
| `anti_passback_violation` | deny | Entry attempted while credential is already inside the zone |
| `acs_unavailable_fail_open` | allow | ACS dependency unavailable; zone configured fail-open |
| `acs_unavailable_fail_closed` | deny | ACS dependency unavailable; zone configured fail-closed |
| `emergency_fail_open` | allow | Emergency active; zone configured fail-open |
