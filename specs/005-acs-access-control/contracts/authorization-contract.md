# Gate Authorization Contract

## Purpose

Define the gate authorization decision order, its reason-code set, and how it
reuses the Phase 2 scan decision order and the Phase 1 credential validation
as the single credential trust path, extending
`specs/003-wallet-passes-scanning/contracts/scan-contract.md` exactly at its
documented Phase 2/Phase 4 boundary. This contract is the boundary between the
external ACS and the authoritative access decision; it MUST NOT introduce a
second credential-validity path.

## Relationship to the Scan Contract

The credential-validity portion of every gate decision ends at the exact
Phase 2 decision order (signature, expiry, revocation, replay resistance, key
identification/rotation) via the Phase 1 `Credentials` validation contract.
This contract adds the **access-rule and anti-passback layer** on top and the
**entry/exit/emergency event model**; it introduces no new credential
`result` value and no new signing path.

## Decision Order

A gate authorization request (`credential/identity reference`, resolved lane,
requested `direction`) is decided in this fixed order. The first failing step
determines the deny reason; success at every step yields `allow`/`allowed`.

```text
1. Resolve AcsLane from external_acs_lane_id within the M2M actor's mapped
   event scope.
     - unmapped lane / cross-scope  -> reject as unknown target
       (acs_lane_unmapped / acs_event_out_of_scope)
2. Emergency short-circuit: if an EmergencyEvent is active for the lane's
   zone and the zone is emergency_egress_mode = fail_open, for BOTH entry and
   exit presentations:
     -> allow, reason = emergency_fail_open   (skip steps 3-6)
3. Credential validity (Phase 1 validation + Phase 2 scan decision order,
   unchanged):
     - expired  -> deny, credential_expired
     - revoked  -> deny, credential_revoked
     - unknown / bad signature / unknown key / replay -> deny,
       credential_unknown
4. Rule evaluation for (ticket_type, attendee_type, zone, lane, direction,
   now):
     - no active rule permits the zone            -> deny, zone_not_permitted
     - a rule permits the zone but not this lane  -> deny, lane_not_permitted
     - permitted zone/lane but outside window     -> deny, outside_time_window
5. Anti-passback (entry direction only; zone.anti_passback_enabled and rule
   not anti_passback_exempt):
     - credential already inside the zone -> deny, anti_passback_violation
6. ACS dependency availability (if the confirmed transport requires an
   ACS-side round trip and it is unreachable / exceeds the latency budget):
     - zone unavailability_mode = fail_open  -> allow, acs_unavailable_fail_open
     - zone unavailability_mode = fail_closed -> deny, acs_unavailable_fail_closed
7. Otherwise -> allow, allowed
```

Recording (single audited transaction, `research.md` Decision 9):

```text
- always: append AccessEvent(event_type = decision, decision, reason_code,
  direction, zone, lane, credential, occurred_at, source = acs_gate)
- if allow AND direction = entry AND lane.is_admission_lane:
    call Phase 2 SubmitScanAction (unchanged) with scanner_type = 'acs_gate';
    link the resulting ScanEvent via AccessEvent.scan_event_id
- after commit: audit the decision (access.authorized / access.denied)
```

## Entry/Exit Event Model

Entry/exit `AccessEvent` rows are created by ACS callbacks
(`acs-adapter.md` §Ingest Entry/Exit Event), not by the decision itself,
because the physical pass-through is confirmed by the ACS after the gate
releases (`all_plan.md` §19.3 steps 6-7):

- `entry` event -> `AntiPassbackState(credential, zone) = inside`
- `exit` event  -> `AntiPassbackState(credential, zone) = outside`
- ordered by `occurred_at`; out-of-order arrival reconciles to the correct
  final state (`research.md` Decision 6).
- idempotent by `(tenant_id, external_event_id)`.

## Reason Codes

The full language-neutral reason-code set is defined in
[../data-model.md](../data-model.md) §Reason Codes. Deny reasons never
disclose which specific rule or scope check failed beyond the stable category
(e.g. a cross-tenant credential and an unknown credential both return
`credential_unknown`).

## Tenant Isolation and Data Handling

- Tenant/event/lane scope is resolved from the M2M integration record and the
  referenced external identifiers, never a request-supplied tenant/event id.
- The response to the ACS is only `allow`/`deny` + `reason_code`; no
  credential payload, key material, or attendee personal data is returned.
- A cross-tenant or cross-event credential/lane/zone reference produces the
  same rejected/unknown response as an unknown reference in every operation.

## Contract Test Matrix

Every implementation must pass:

1. Valid credential + permitting rule + entry within window returns
   `allow`/`allowed` and appends one `decision` `AccessEvent`.
2. Expired, revoked, and unknown credentials return the mapped deny reason
   with no rule/anti-passback evaluation leaking a different reason.
3. Valid credential with no permitting zone rule returns
   `zone_not_permitted`; permitted zone but wrong lane returns
   `lane_not_permitted`; outside window returns `outside_time_window`.
4. Entry while already inside an anti-passback-enabled zone returns
   `anti_passback_violation`; the same credential after a recorded exit is
   allowed; an `anti_passback_exempt` rule never triggers the violation.
5. An active fail-open emergency short-circuits to
   `allow`/`emergency_fail_open` even when anti-passback or window rules would
   otherwise deny.
6. ACS unavailable / latency exceeded returns
   `acs_unavailable_fail_open` (allow) or `acs_unavailable_fail_closed`
   (deny) per zone mode, each recorded on an `AccessEvent`.
7. An allowed entry at an `is_admission_lane` lane records a Phase 2
   `ScanEvent` (`scanner_type = 'acs_gate'`) linked via
   `AccessEvent.scan_event_id`; a non-admission lane does not.
8. Entry/exit callbacks update anti-passback state correctly, are idempotent
   by `external_event_id`, and reconcile out-of-order arrival by
   `occurred_at`.
9. Every allow and deny decision is audited with actor (the M2M ACS
   integration), tenant, event, lane/zone, decision, reason code, and
   correlation, without leaking the M2M secret or credential payload.
10. A cross-tenant/cross-event credential, lane, or zone reference is denied
    identically to an unknown target in every operation.
