# ACS Zone, Lane, Rule, and Anti-Passback Runbook

Owner: Operations  
Last reviewed: 2026-07-07

This runbook covers operator configuration of ACS zones, lanes, authorization
rules, and anti-passback behavior before and during an event. Requires
`acs.configure` for mutations and `acs.events.view` / `acs.health.view` for
monitoring surfaces.

## Prerequisites

- Published event with at least one ticket type and issued credentials (Phase 1/2).
- ACS integration credential registered (`docs/operations/acs-integration-protocol.md`).
- External ACS zone and lane identifiers agreed with the venue ACS vendor.

## Zone configuration

Create a zone mapping Zonetec to an external ACS zone id:

```http
POST /api/v1/tenant/events/{event_id}/acs/zones
```

| Field | Notes |
| --- | --- |
| `name` | Operator label (max 120 chars) |
| `external_acs_zone_id` | Unique per event; duplicate → `409 acs_duplicate_external_id` |
| `anti_passback_enabled` | When true, re-entry without a recorded exit is denied |
| `unavailability_mode` | `fail_open` or `fail_closed` when ACS adapter is unreachable |
| `emergency_egress_mode` | `fail_open` or `fail_closed` during active emergency |
| `status` | `active` or `inactive` |

Update modes with `PATCH .../acs/zones/{zone_id}` (name and mode fields only;
external id is immutable after create).

List zones: `GET .../acs/zones`.

## Lane configuration

Create a lane under a zone in the **same** event:

```http
POST /api/v1/tenant/events/{event_id}/acs/lanes
```

| Field | Notes |
| --- | --- |
| `zone_id` | Must belong to the same tenant/event |
| `external_acs_lane_id` | Unique per event |
| `gate_type` | `turnstile`, `door`, `speedgate`, `manual` |
| `access_direction` | `entry`, `exit`, or `bidirectional` |
| `is_admission_lane` | When true, allowed **entry** also records Phase 2 check-in (`scanner_type = acs_gate`) |

Lane health starts `offline` until the ACS posts entry/exit callbacks or
authorization traffic updates `last_seen_at`.

## Authorization rules

Rules map ticket types (and optional attendee types) to zones/lanes/directions:

```http
POST /api/v1/tenant/events/{event_id}/acs/rules
```

| Field | Notes |
| --- | --- |
| `zone_id` | Required |
| `access_direction` | `entry`, `exit`, or `bidirectional` |
| `ticket_type_id` | Null = any ticket type |
| `lane_id` | Null = any lane in the zone |
| `anti_passback_exempt` | When true, anti-passback never denies this rule match |
| `valid_from` / `valid_until` | Optional window; inverted window → `422 acs_invalid_time_window` |

Gate authorization evaluates rules in the order documented in
`specs/005-acs-access-control/contracts/authorization-contract.md`.

## Anti-passback

Anti-passback is **derived from ingested entry/exit events**, not from
authorization alone.

1. Enable `anti_passback_enabled` on the zone.
2. Ensure the ACS posts `POST /acs/v1/events` callbacks with
   `credential_reference` after physical pass-through.
3. Authorization at an entry lane calls `AntiPassbackService::isInside()`:
   - After an ingested **entry**, state is `inside`.
   - A second entry authorization returns `deny` / `anti_passback_violation`.
   - After an ingested **exit**, state is `outside`; entry is allowed again.
4. Out-of-order callbacks reconcile by `occurred_at` (older events ignored if
   superseded).
5. Rules with `anti_passback_exempt = true` skip the violation check.
6. Disabling anti-passback on the zone allows repeat entry without exit events.

### Troubleshooting anti-passback

| Symptom | Check |
| --- | --- |
| False `anti_passback_violation` | Confirm exit callback was ingested for the credential/zone |
| No violation when expected | `anti_passback_enabled` false, or rule is exempt |
| State stuck after exit | Verify `external_event_id` idempotency did not drop the exit |
| Cross-zone bleed | Anti-passback state is per `(credential, zone)` |

## Day-of-event checklist

1. List zones, lanes, and rules; confirm external ids match ACS configuration.
2. Register or rotate integration credential; store secret in ACS secret manager.
3. Send a test authorize for a known credential → expect `allow`/`allowed`.
4. Post test entry/exit callbacks; confirm gate-events feed and lane `last_seen_at`.
5. Open ACS health view; confirm lanes move from `offline` to `online` after contact.
6. If anti-passback enabled, verify entry → exit → re-entry sequence in staging.

## Isolation

- Configuration and feeds are tenant- and event-scoped.
- Cross-tenant event URLs return empty lists on GET or client errors on mutation;
  foreign zone/lane ids in the caller's event return `404`.
- Integration credentials cannot act outside their mapped event.
