# ACS Emergency Egress Runbook

Owner: Operations  
Last reviewed: 2026-07-07

Emergency egress allows controlled fail-open gate behavior during fire alarms,
venue evacuations, or ACS-initiated emergency signals. Every raise and clear is
recorded as an `EmergencyEvent` and an `AccessEvent` (`event_type = emergency`).

## Permissions and channels

| Actor | Permission / capability | Endpoint |
| --- | --- | --- |
| Operator | `acs.emergency.manage` | `POST /api/v1/tenant/events/{event_id}/acs/emergency` |
| ACS integration | `emergency.ingest` | `POST /api/v1/acs/v1/emergency` |

Operators without `acs.emergency.manage` receive `403 acs_emergency_not_permitted`.

## Raise an emergency

Operator request:

```json
{
  "action": "raise",
  "zone_id": "<zone-ulid-or-null-for-event-wide>"
}
```

ACS callback:

```json
{
  "action": "raise",
  "external_acs_zone_id": "<external-zone-id-or-omit>",
  "signal_source": "acs",
  "occurred_at": "2026-07-07T12:00:00Z"
}
```

Effects:

- Creates an `EmergencyEvent` with `cleared_at = null`.
- `behavior_applied` derives from the target zone's `emergency_egress_mode`
  (`fail_open` default for event-wide).
- Appends an `AccessEvent` with `reason_code = emergency_raised`.
- Audit action `acs_emergency.raised`.

## Authorization behavior while active

For **entry** authorization at a lane in an affected zone:

- If `emergency_egress_mode = fail_open` and an emergency is active (zone-specific
  or event-wide), authorization short-circuits to `allow` / `emergency_fail_open`
  before credential and rule checks.
- Zones with `emergency_egress_mode = fail_closed` continue normal decisioning.

Anti-passback and rule denials are bypassed only for the fail-open emergency path.

## Clear an emergency

```json
{
  "action": "clear",
  "zone_id": "<zone-ulid-or-null>"
}
```

Clears all active emergency rows for the target scope (`cleared_at = now()`),
appends `emergency_cleared` access evidence, and emits `acs_emergency.cleared`
audit rows. Normal authorization resumes immediately.

## Monitoring

- **Gate events feed** (`acs.events.view`): shows emergency-type access rows.
- **ACS health** (`acs.health.view`): `active_emergency: true` while any uncleared
  emergency exists for the event (zone-scoped or event-wide).

Poll interval on dashboard pages: 15 s (same pattern as kiosk health).

## Runbook scenarios

### Fire alarm — fail-open evacuation

1. ACS or operator raises emergency for affected zones (or event-wide).
2. Confirm health banner shows `active_emergency`.
3. Verify entry authorizations return `emergency_fail_open` at fail-open zones.
4. Continue ingesting entry/exit callbacks for occupancy reconciliation.
5. Clear emergency only after venue safety officer confirms all-clear.

### False alarm

1. Operator clears emergency via dashboard or API.
2. Confirm `active_emergency` returns false on health endpoint.
3. Spot-check authorize for a credential previously denied by rules — normal
   decisioning should resume.

### ACS transport unconfirmed

Until Runa transport is documented (`all_plan.md` §38.2, §39.2), emergency
signals may arrive only through the inbound HTTP callback or operator API. Do not
assume ACS can receive outbound emergency state from Zonetec.

## Audit and evidence

| Action | Target | Metadata |
| --- | --- | --- |
| `acs_emergency.raised` | `emergency_event` | `event_id`, `zone_id` |
| `acs_emergency.cleared` | `emergency_event` | `event_id`, `zone_id` |

No integration secrets or credential payloads appear in audit metadata.
