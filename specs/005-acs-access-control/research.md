# Phase 4 Research: ACS and Access Control

**Date**: 2026-07-07

This research resolves the technical choices needed by the Phase 4 plan. The
accepted Phase 0 foundation, Phase 1 registration/ticketing/credential core,
Phase 2 wallet/scanning core, and Phase 3 kiosk/badge/manual-desk increment
remain authoritative unless a decision below explicitly extends them.

`all_plan.md` records the exact Runa ACS transport, latency budget, zone/lane
representation, emergency-egress signaling, and existing anti-passback
implementation as open integration questions (§38.2, §39.2). These are
resolved here as an **adapter-boundary** decision (Decision 3): the phase
ships against a mock ACS and defines behavior protocol-agnostically, keeping
the unknown transport an explicit blocking assumption rather than a disguised
production integration.

## Decision 1: Add `AccessControl` as the only new owned module

**Decision**: Introduce one new owned module, `AccessControl`, that owns ACS
zones, lanes, authorization rules, access events (decisions and entry/exit/
emergency), anti-passback state, emergency events, and the provider-neutral
`AcsAdapter` contract with its mock and (later) real Runa implementations.
No second module is created for anti-passback or emergency egress; they are
concerns of the same bounded context.

**Rationale**: `all_plan.md` §32.3 lists the Phase 4 backend modules as "ACS
Integration Module, Credential Module, Scan Module, Event Module, Reporting
Module, Audit Module" — only the ACS Integration piece is new; the rest are
existing modules this phase consumes. Zones, lanes, rules, decisions,
anti-passback, and emergency egress are one tightly coupled access-control
domain; splitting them would fragment the single decision authority and
duplicate scope resolution.

**Alternatives considered**:

- A separate `AntiPassback` or `EmergencyEgress` module: rejected; both are
  behaviors of the same authorization decision and share the same access-
  event data. Separate modules would force cross-module reads of the same
  event stream and blur ownership of the decision.
- Folding ACS into `Scanning`: rejected; `Scanning` owns event check-in
  (single-entry semantics), while access control is repeated zone entry/exit
  with directional and anti-passback rules. Merging them would overload the
  Phase 2 scan decision with concerns it was never scoped for.

## Decision 2: The authorization decision reuses the Phase 1 credential validation and Phase 2 scan decision order as the single credential trust path

**Decision**: A gate authorization decision has two layers. The
**credential-validity** layer reuses the unchanged Phase 1 `Credentials`
validation and the Phase 2 scan decision order (signature, expiry,
revocation, replay resistance, key identification/rotation). The
**access-rule** layer then evaluates the `AcsAuthorizationRule` set (ticket
type, attendee type, zone, lane, direction, time window) and anti-passback.
Only if both layers pass does the decision return `allowed`.

**Rationale**: CR-004 and constitution principle II forbid a second
credential trust path. Phase 2 already owns the sole credential-validity
decision order; Phase 4 must not re-implement signature/revocation/replay
checks. Layering access rules on top of the reused validity check keeps one
authority for "is this credential real and live" while adding "is it allowed
here, now, and not passing back."

**Alternatives considered**:

- A standalone ACS credential validator: rejected; it would duplicate
  Phase 1/2 signing and revocation logic and could drift, exactly the risk
  the constitution's phased-delivery principle warns against.
- Trusting the ACS to pre-validate the credential: rejected; the ACS is an
  external adapter-boundary system and cannot be the authority for
  Zonetec-issued credential validity.

## Decision 3: The external Runa ACS is isolated behind one `AcsAdapter` contract with a mock implementation

**Decision**: Define one `AcsAdapter` interface in
`AccessControl\Contracts` that abstracts the ACS transport. The primary
integration is **inbound** (the ACS calls Zonetec's `/acs/v1/*` endpoints for
authorization and posts entry/exit/emergency callbacks); the adapter also
covers any **outbound** calls Zonetec makes to the ACS (e.g. health probe)
where the confirmed protocol requires them. A `MockAcsAdapter` (tests/local
dev) implements the interface; a `RunaAcsAdapter` is added once the transport
(REST/WebSocket/TCP/MQTT), latency budget, and payloads are documented.

**Rationale**: `all_plan.md` §38.2/§39.2 lists the transport, latency,
zone/lane representation, emergency signaling, and existing anti-passback as
open questions, and §38.2's mitigation is explicitly "create ACS contract
early, build mock ACS, run integration tests before Phase 4 starts."
Constitution principle VI requires external systems behind an adapter with
explicit timeout/retry/idempotency/error mapping. The adapter lets Phase 4
ship and pass contract tests against the mock now and bind the real transport
later without changing domain logic.

**Alternatives considered**:

- Commit to a specific transport now (e.g. REST): rejected; the protocol is
  explicitly unconfirmed, and hard-coding it would leak a guessed transport
  into domain code and misrepresent readiness.
- Direct ACS integration without an adapter: rejected; it violates principle
  VI and would couple the authorization domain to Runa-specific concerns.

## Decision 4: The ACS authenticates as an M2M integration actor scoped to its mapped event/lanes

**Decision**: The external ACS calls Zonetec's integration endpoints as a
machine-to-machine actor authenticated by an ACS integration credential (M2M
secret/token), scoped to a specific tenant/event and its mapped lanes. It is
capability-limited to authorization requests and entry/exit/emergency
callbacks; it holds no human RBAC permission and cannot read configuration or
dashboards. Tenant/event/lane scope is resolved from the mapped integration
record and the referenced lane, never from a request-supplied identifier.

**Rationale**: This mirrors the Phase 1/2 adapter/M2M authentication pattern
and the Phase 3 kiosk-session precedent (Decision 2 there): the caller is a
system, not a logged-in user, but scope is authoritatively resolved from a
stored record and every call is authenticated and audited. Constitution
principle II requires RBAC/least-privilege at every entry point including
machine-to-machine flows.

**Alternatives considered**:

- ACS shares a human operator account: rejected; violates least-privilege
  and corrupts audit attribution (who "logged in" as the turnstile?).
- A single static per-tenant key for all ACS traffic: rejected; it cannot be
  scoped or revoked per event/integration and cannot enforce that a lane
  reference belongs to the caller's mapped event.

## Decision 5: Anti-passback state is derived and materialized from recorded entry/exit `AccessEvent` rows

**Decision**: Maintain an `AntiPassbackState` row per `(credential, zone)`
holding `inside`/`outside` derived from ordered entry/exit `AccessEvent`
rows. On an entry authorization for a zone with `anti_passback_enabled`, a
credential currently `inside` is denied (`anti_passback_violation`). A
recorded exit flips state to `outside`. Anti-passback is configurable per
event, per zone (`AcsZone.anti_passback_enabled`), and per ticket type (a
rule-level exemption flag). A bounded repair job recomputes state from the
ordered event stream if drift is detected.

**Rationale**: `all_plan.md` §19.4 requires anti-passback configurable per
event, zone, and ticket type, and §33/§19.3 make entry/exit events the source
of truth. A materialized state row keeps the on-request entry check O(1)
while the append-only `AccessEvent` stream remains the authoritative record
(matching Phase 2's append-only `ScanEvent` precedent).

**Alternatives considered**:

- Recompute inside/outside from the full event stream on every entry:
  rejected; unbounded scan cost on the latency-sensitive turnstile path.
- Store state only, no event stream: rejected; loses the auditable,
  reconcilable history and cannot repair drift or answer occupancy/reporting.

## Decision 6: Entry/exit event callbacks are ingested idempotently by external event id and reconciled for out-of-order arrival

**Decision**: Each ACS entry/exit callback carries an `external_event_id`.
Ingestion dedupes on `(tenant_id, external_event_id)`; a duplicate is a safe
no-op (success, not error). `AccessEvent` rows carry `occurred_at` from the
ACS; anti-passback state reconciliation orders by `occurred_at` so an
out-of-order exit/entry still yields the correct final state.

**Rationale**: FR-014 requires idempotent processing so duplicates/replays do
not double-count; §19.3 shows events flowing back asynchronously, which can
duplicate or reorder. Idempotency by a caller-supplied stable id is the same
pattern Phase 1/2/3 use for payment/scan/print submission.

**Alternatives considered**:

- Trust arrival order and dedupe by timestamp only: rejected; timestamps
  collide and reorder; a stable external id is the reliable idempotency key.

## Decision 7: ACS unavailability applies a per-zone `fail_open`/`fail_closed` mode; emergency-egress fail-open is a separate per-zone signal path

**Decision**: `AcsZone` carries two independent modes: `unavailability_mode`
(`fail_open`/`fail_closed`, safe default `fail_closed`) applied when the ACS
dependency is unreachable or exceeds the latency budget, and
`emergency_egress_mode` (`fail_open`/`fail_closed`, safe default `fail_open`
for life safety) applied when an emergency signal is active. Each applied
mode is recorded on the resulting `AccessEvent` with a distinct reason code.

**Rationale**: FR-016/FR-020 and §19.5 require explicit, testable
emergency-egress and unavailability behavior; conflating them is unsafe
(availability failure should usually fail closed for security; fire alarm
should fail open for life safety). Separating the two modes lets an organizer
choose each independently, per zone.

**Alternatives considered**:

- One combined "failure behavior" flag: rejected; it cannot express
  "fail closed on outage but fail open on fire alarm," the common real
  requirement.
- Global (non-per-zone) modes: rejected; §19.5 and §32.5 imply per-zone
  emergency and access behavior.

## Decision 8: Gate events and ACS/lane health reuse the Phase 2/3 bounded-polling dashboard pattern

**Decision**: Authorized viewers read gate events (allowed/denied/entry/exit/
emergency with reasons) and ACS/lane health (`online`/`degraded`/`offline`
derived from adapter heartbeat/last-contact) through bounded, tenant/event-
scoped endpoints polled on a short fixed interval, extending the Phase 2
`EventCheckInSummary` and Phase 3 kiosk-health dashboards. No
broadcasting/WebSocket dependency is added.

**Rationale**: SC-009 requires a "short, bounded delay," which polling
already satisfies without a persistent-connection dependency that would
complicate on-premise parity (principle III). There is no requirement for
sub-second health push in this phase.

**Alternatives considered**:

- WebSocket/SSE push for gate events: deferred for the same reason Phase 2/3
  deferred it; not required to meet SC-009 and adds an operational dependency
  that conflicts with the local-ACS on-premise profile.

## Decision 9: A gate that also represents event admission records a Phase 2 check-in via the unchanged `SubmitScanAction`; zone/lane movement is an `AccessEvent`

**Decision**: When an `AcsLane` is flagged as an event-admission lane, an
`allowed` entry decision also records a Phase 2 check-in by calling the
unchanged `SubmitScanAction` with `scanner_type = 'acs_gate'` and links the
resulting `ScanEvent` to the `AccessEvent`. Non-admission zone/lane movements
(inner zones, exits, re-entries) record only an `AccessEvent`. This satisfies
`all_plan.md` §19.3 step 7 ("records scan and access event") without a second
trust path.

**Rationale**: Event admission is genuinely a check-in and should appear in
the Phase 2 check-in record; but repeated inner-zone movement is not a
check-in and must not corrupt single-entry check-in semantics. Reusing
`SubmitScanAction` unchanged keeps one check-in authority; the `AccessEvent`
carries the access-control-specific zone/lane/direction/reason.

**Alternatives considered**:

- Treat every gate entry as a check-in: rejected; it breaks Phase 2
  single-entry semantics for multi-zone venues.
- Never create a check-in at a gate: rejected; it contradicts §19.3 step 7
  and would hide admission from Phase 2 check-in reporting.

## Decision 10: All ACS entities are tenant/event-scoped exactly like Phase 1/2/3 configuration

**Decision**: `AcsZone`, `AcsLane`, `AcsAuthorizationRule`, `AccessEvent`,
`AntiPassbackState`, and `EmergencyEvent` all carry `tenant_id` (+ `event_id`
where event-scoped) with the same composite-foreign-key and
fail-closed-unknown-target pattern Phase 1/2/3 established. External ACS
identifiers (`external_acs_zone_id`, `external_acs_lane_id`) are unique per
event, never a substitute for tenant/event scope.

**Rationale**: Consistent application of an already-accepted pattern is lower
risk than inventing a new one and directly satisfies CR-001.

**Alternatives considered**: None; this directly reuses the accepted
Phase 0/1/2/3 pattern.
