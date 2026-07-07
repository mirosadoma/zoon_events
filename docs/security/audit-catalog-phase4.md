# Phase 4 audit action catalog

Owner: Security Engineering  
Last reviewed: 2026-07-07

Phase 0–3 action families remain in `docs/standards/audit-event-catalog.md`.
Every Phase 4 row records scope, actor, target, outcome, stable reason,
correlation, channel, fingerprints, sanitized metadata, key ID, algorithm, and
HMAC. Integration secrets, raw M2M tokens, and credential payloads never enter
audit metadata.

## `acs_zone.*` / `acs_lane.*` / `acs_rule.*`

| Action | Outcome | Target | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- |
| `acs_zone.created` | succeeded | `acs_zone` | `event_id` | Operator created zone mapping |
| `acs_zone.updated` | succeeded | `acs_zone` | `event_id` | Modes, name, or status changed |
| `acs_lane.created` | succeeded | `acs_lane` | `event_id` | Operator created lane mapping |
| `acs_rule.created` | succeeded | `acs_rule` | `event_id` | Operator created authorization rule |

## `acs_integration.*`

| Action | Outcome | Target | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- |
| `acs_integration.credential_registered` | succeeded | `acs_integration_credential` | `event_id` | Secret shown once in API response only; never in audit |

## `access.*`

| Action | Outcome | Target | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- |
| `access.authorized` | succeeded | `access_event` | `event_id`, `zone_id`, `lane_id`, `direction`, `reason_code` | Gate allow decision |
| `access.denied` | denied | `access_event` | `event_id`, `zone_id`, `lane_id`, `direction`, `reason_code` | Gate deny decision |
| `access.entry` | succeeded | `access_event` | `event_id`, `lane_id`, `zone_id`, `direction` | Ingested entry callback |
| `access.exit` | succeeded | `access_event` | `event_id`, `lane_id`, `zone_id`, `direction` | Ingested exit callback |

Gate decision evidence is synchronous inside the audited transaction. Queue
failure cannot remove required access evidence.

## `acs_emergency.*`

| Action | Outcome | Target | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- |
| `acs_emergency.raised` | succeeded | `emergency_event` | `event_id`, `zone_id` | Operator or ACS raised emergency |
| `acs_emergency.cleared` | succeeded | `emergency_event` | `event_id`, `zone_id` | Emergency cleared |
