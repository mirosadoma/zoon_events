# ACS Adapter Contract

## Purpose

Keep the `AccessControl` authorization domain independent of the specific
Runa ACS transport and protocol, which are open integration questions in
`all_plan.md` (§38.2, §39.2). This contract governs the module's internal
provider-neutral `AcsAdapter` interface and the mock and (later) Runa-backed
adapters that implement it, following the conventions of
`specs/001-project-foundation/contracts/adapter-contract.md`.

## Non-Negotiable Boundary

The ACS adapter transports authorization requests, event callbacks, and
emergency signals between Zonetec and the external ACS. It MUST NOT decide
credential validity, evaluate access rules, own anti-passback state, or
introduce any second authorization or credential-validity check.
`AccessControl` performs the decision (reusing the Phase 1 credential
validation and Phase 2 scan decision order, then the rule/anti-passback
layer) and the adapter only carries the request in and the decision out. The
external ACS receives only an allow/deny decision and a reason code — never
signing keys, a raw credential payload, or a re-scoped credential.

## Integration Direction

The primary integration is **inbound**: the external ACS calls Zonetec's
`/acs/v1/*` endpoints (authorization request, entry/exit callback, emergency
callback). The adapter normalizes the confirmed transport
(REST/WebSocket/TCP/MQTT — unconfirmed, `research.md` Decision 3) into the
internal request/decision shape. Any **outbound** call Zonetec makes to the
ACS (e.g. a health probe, or pushing an emergency state where the confirmed
protocol requires it) is also expressed through this adapter. Until the real
transport is documented, a `MockAcsAdapter` implements the full interface for
development and contract tests.

## Invocation Context

Every adapter call carries trusted:

- tenant and event identifiers (resolved from the M2M integration record,
  never a client-supplied value);
- the resolved `AcsLane`/`AcsZone` (from `external_acs_lane_id`/
  `external_acs_zone_id`);
- correlation identifier;
- for callbacks, the ACS-supplied `external_event_id` (idempotency key);
- timeout / latency budget.

ACS integration credentials (M2M secret, transport tokens) are resolved
inside infrastructure from a `secret_reference`. They never enter domain
requests, logs, audit metadata, exceptions, job payloads, or API responses.

## Operations

### Authorize (inbound)

Input: credential/identity reference, resolved lane reference, requested
direction, correlation id, timeout budget.

Behavior: the adapter hands the normalized request to `AccessControl`, which
returns an allow/deny decision and reason code. The adapter carries that
decision back to the ACS in the confirmed transport shape.

Result: `allow` | `deny` + stable `reason_code`
(`contracts/authorization-contract.md`). The physical gate is released by the
ACS on `allow`; Zonetec never drives gate hardware.

### Ingest Entry/Exit Event (inbound)

Input: `external_event_id`, resolved lane/zone, credential reference,
`direction` (`entry`/`exit`), `occurred_at`, correlation id.

Behavior: idempotent by `(tenant_id, external_event_id)`; a duplicate is a
success no-op (`research.md` Decision 6). Records an `AccessEvent` and updates
anti-passback state and occupancy.

### Ingest Emergency Signal (inbound)

Input: signal source, affected zone reference (or event-wide), `occurred_at`,
correlation id.

Behavior: raises/clears an `EmergencyEvent` and applies the affected zones'
`emergency_egress_mode`.

### Health (outbound or heartbeat-derived)

Result: stable ACS/lane status (`online`, `degraded`, `offline`) and a safe
reason category where the transport exposes one. Drives `AcsLane.health_status`
and the integration health shown on the dashboard; the server never assumes a
specific transport is reachable in the on-premise local-ACS profile.

## Fail-Open / Fail-Closed and Latency

- Every `Authorize` call is bounded by a timeout/latency budget suitable for
  turnstile operation (target under 500 ms at the application boundary,
  SC-011).
- If the ACS-side dependency is unreachable or the budget is exceeded,
  `AccessControl` applies the target zone's `unavailability_mode`:
  `fail_open` → `allow` / `acs_unavailable_fail_open`; `fail_closed` → `deny`
  / `acs_unavailable_fail_closed`. The applied mode is always recorded on the
  resulting `AccessEvent`; a request is never silently dropped (FR-020,
  `research.md` Decision 7).

## Stable Error Categories

Reuses the Phase 0 adapter categories
(`specs/001-project-foundation/contracts/adapter-contract.md`) plus:

| Category | Meaning | Default handling |
|---|---|---|
| `acs_unavailable` | ACS dependency unreachable or latency budget exceeded | Apply the zone `unavailability_mode`; record the applied decision, never drop |
| `acs_zone_unmapped` | Referenced external zone id has no `AcsZone` mapping | Deny/reject as an unknown target; never auto-create a zone |
| `acs_lane_unmapped` | Referenced external lane id has no `AcsLane` mapping | Deny/reject as an unknown target; never auto-create a lane |
| `acs_event_out_of_scope` | Callback references a lane/zone outside the integration actor's mapped event | Reject; not recorded |

## Tenant Isolation and Data Handling

- Tenant/event/lane scope is resolved solely from the mapped M2M integration
  record and the referenced external identifiers, never from a
  request-supplied tenant/event id.
- The M2M secret and transport tokens are sensitive and excluded from logs,
  metrics, audit metadata, and error messages.
- The decision carried back to the ACS contains only allow/deny + reason
  code; no credential payload, key material, or personal data is returned.
- An authorization/callback/health call scoped to one tenant/event never
  returns or leaks another tenant's or event's zones, lanes, decisions, or
  ACS state.

## Contract Test Matrix

Every ACS adapter implementation (mock and, when available, Runa-backed) must
pass:

1. An authorization request for a valid, in-scope credential with a
   permitting rule returns `allow`/`allowed`.
2. Authorization requests for expired, revoked, and unknown credentials
   return `deny` with `credential_expired`/`credential_revoked`/
   `credential_unknown` respectively.
3. An authorization request referencing an unmapped external zone/lane id is
   rejected with `acs_zone_unmapped`/`acs_lane_unmapped`, identical to an
   unknown target, without creating a mapping.
4. When the ACS dependency is unreachable or exceeds the latency budget, a
   `fail_open` zone returns `allow`/`acs_unavailable_fail_open` and a
   `fail_closed` zone returns `deny`/`acs_unavailable_fail_closed`, each
   recorded on an `AccessEvent`; nothing is silently dropped.
5. An entry/exit callback with a repeated `external_event_id` is an
   idempotent no-op (no duplicate `AccessEvent`, no double state transition).
6. An entry/exit callback referencing a lane/zone outside the actor's mapped
   event is rejected with `acs_event_out_of_scope` and not recorded.
7. An emergency signal for a `fail_open` zone causes subsequent entry
   decisions at affected lanes to return `allow`/`emergency_fail_open` until
   cleared.
8. Health reporting returns `online`/`degraded`/`offline` consistently with
   the last simulated/real ACS state.
9. No M2M secret, transport token, credential payload, key material, or raw
   ACS protocol error string appears in any log, audit record, decision
   reason code, or API response produced during the above.
