# Phase 3 Research: Kiosk Check-In, Badge Printing, and Manual Desk

**Date**: 2026-07-06

This research resolves the technical choices needed by the Phase 3 plan. The
accepted Phase 0 foundation, Phase 1 registration/ticketing/credential core,
and Phase 2 wallet/scanning core remain authoritative unless a decision below
explicitly extends them.

## Decision 1: Add `Kiosk` and `BadgePrinting` as the only two new owned modules; "manual desk" is a surface, not a module

**Decision**: Introduce `Kiosk` (device registration, sessions, health) and
`BadgePrinting` (badge templates, print jobs, printer adapter) as new owned
modules. Manual desk capability is implemented as new HTTP surfaces and a
small number of new application actions/queries added to the existing
`Scanning` module (lookup, desk check-in) and `Attendees` module (walk-up
registration), not as a third new domain module.

**Rationale**: `all_plan.md` §31.3 lists the Phase 3 backend modules as
"Kiosk Module, Badge Printing Module, Attendee Module, Credential Module,
Check-In Module, Audit Module" — it names an Attendee/Credential/Check-In
module extension, not a standalone "Manual Desk module." The manual desk is
staff operating the same check-in and printing capability a kiosk uses,
minus the unattended-device concerns (session pairing, health heartbeats).
Modeling it as a module would duplicate the check-in decision order that
Phase 2 already owns and risks a second trust path.

**Alternatives considered**:

- A single combined "OnSite" module for kiosk, badge, and manual desk:
  rejected because kiosk device lifecycle (pairing, heartbeats, printer
  health) and badge template/print-job lifecycle are independently owned
  concerns with different consumers (unattended hardware vs. staff HTTP
  session vs. organizer configuration UI); combining them would blur adapter
  boundaries the same way Phase 2 rejected a combined wallet+scanning module.
- A separate `ManualDesk` module wrapping `Scanning`/`Attendees`/
  `BadgePrinting` calls: rejected as unnecessary indirection; the desk is a
  thin controller/query layer, not a new bounded context with its own
  owned data.

## Decision 2: Kiosk devices authenticate with a paired device-session token, never a human RBAC identity

**Decision**: A `Kiosk` row is created by an authorized human (`kiosk.manage`
permission). Pairing issues one hashed device-session secret bound to that
kiosk row and its tenant/event. Every kiosk API call (lookup, scan, print,
heartbeat) authenticates with that session secret via a dedicated
`KioskSessionAuth` guard, resolving tenant/event from the kiosk record —
never from a client-supplied header. An optional kiosk-level confirmation
step (a short organizer-configured PIN/one-time code) may be required before
a *newly started* kiosk session accepts scans, to reduce risk from a
physically compromised device without turning every scan into a login.

**Rationale**: This mirrors Phase 2's Apple PassKit web-service pattern
(Decision reused from `specs/003-wallet-passes-scanning/contracts/wallet-adapter.md`):
the caller is a device, not a logged-in user, but tenant/event scope is still
authoritatively resolved from a stored record and every call is authenticated.
Treating the kiosk as a human actor would either require sharing a staff
login on unattended hardware (a credential-hygiene risk) or force one
permission grant per kiosk, complicating least-privilege administration.

**Alternatives considered**:

- Kiosk uses a normal user account/session: rejected; unattended devices
  sharing a human session violate least-privilege and complicate audit
  attribution (who is "logged in" to a public kiosk?).
- No session pairing, only a static per-tenant API key: rejected; a single
  shared secret cannot be revoked per-device and does not support FR-004's
  requirement that a kiosk only ever act within its own registered
  event/tenant.

## Decision 3: Attendee lookup by name/email/phone is added to `Scanning`, reused by both Kiosk and the manual desk

**Decision**: Add one bounded, tenant/event-scoped `LookupAttendeesQuery` to
`Scanning` (it already owns the check-in read side) returning matching
attendees with credential/check-in status. Both the `Kiosk` module and the
manual desk HTTP surface call this query through `Scanning`'s existing
public contract; neither queries `Attendees` or `Credentials` persistence
directly.

**Rationale**: The lookup result already needs the same check-in status and
credential-summary shape the dashboard and scan responses use, and `Scanning`
already owns the trusted resolution of "attendee + credential + check-in
state for this event." Duplicating that in two new call sites (kiosk,
manual desk) would either force a new cross-module read path or invite the
architecture violation Phase 2 had to fix (a module reaching into another
module's tables).

**Alternatives considered**:

- Put the lookup in `Attendees`: rejected; `Attendees` does not own
  check-in state (`Scanning` does per Phase 2's `data-model.md`), so a
  lookup useful for kiosk/desk purposes would still need a second call into
  `Scanning` for check-in status, doubling the round trip for no benefit.

## Decision 4: Optional lookup confirmation reuses the Phase 0/1 notification adapter, not a new channel

**Decision**: When an event enables "confirm lookup with a one-time code"
(FR-006), the code is generated and delivered through the existing
notification adapter contract (email/SMS, whichever the tenant already has
configured), short-lived and single-use, verified before the kiosk/desk may
proceed to check-in. No new communication channel or provider integration is
introduced.

**Rationale**: Constitution principle VI requires adapters for external
systems; Phase 1 already has an approved notification adapter boundary. A
kiosk-specific messaging integration would duplicate that boundary for no
functional gain.

**Alternatives considered**:

- On-screen-only code with no delivery channel: rejected as it defeats the
  purpose of confirming the attendee actually controls the contact
  information on file; the spec's assumption explicitly ties this to the
  "existing notification adapter boundary."

## Decision 5: Badge templates are validated structured JSON over a fixed field allowlist, rendered by the server into a provider-neutral print payload

**Decision**: A `BadgeTemplate` stores a `layout` JSON document validated
against a fixed schema whose leaf values may only reference the allowlisted
fields named in the spec (`attendee_name`, `company`, `job_title`, `qr`,
`ticket_type`, `tier`, `zone`, `sponsor_logo_ref`, `organizer_logo_ref`,
`color_code`) plus static layout metadata (position, size, paper size,
printer type). `BadgePrinting` renders an active template plus one
attendee/credential's data into a provider-neutral `PrintPayload` (already
resolved text/image references, no template logic left for the adapter to
interpret) before calling the printer adapter.

**Rationale**: FR-009 requires no-code template editing; FR-011 requires the
print job to render only configured, allowlisted fields, never arbitrary
personal data. A fixed allowlist prevents both accidental data
overexposure (e.g., a template author trying to add a national ID field
that does not exist in the allowlist) and injection of executable content
into what is ultimately sent to physical hardware.

**Alternatives considered**:

- Free-form HTML/Liquid-style template strings: rejected; arbitrary markup
  editable by a tenant admin is an injection and data-exposure risk, and
  contradicts CR-005's purpose-limited field list.
- Let the printer adapter interpret the template directly: rejected; it
  would leak organizer-configuration concerns into adapter implementations
  and make every new printer type responsible for template semantics.

## Decision 6: Printer output is a new `PrinterAdapter` contract following the Phase 0 adapter-contract pattern

**Decision**: Define one `PrinterAdapter` interface (`print(PrintPayload):
PrintResult`, `health(): PrinterHealth`) in `BadgePrinting\Contracts`,
implemented by a `FakePrinterAdapter` (tests/local dev) and a generic
network/driver-backed adapter selected by tenant/kiosk configuration exactly
like Phase 1/2's payment and wallet adapter selection. The kiosk relays its
locally connected printer's health to the server on its heartbeat; the
server never talks to kiosk-local hardware directly.

**Rationale**: Constitution principle VI requires external hardware behind
an adapter interface with explicit timeout/retry/idempotency/error mapping.
Printer makes/models vary by venue and are a "planning-phase input" per the
spec's assumptions; the adapter boundary lets Phase 3 ship with a fake/local
adapter now and add real hardware adapters later without changing
`BadgePrinting`'s domain logic.

**Alternatives considered**:

- Direct browser/kiosk-side printing (e.g., a JS print dialog) with no
  server-side adapter: rejected; it would leave no server-tracked
  `BadgePrintJob` status, contradicting FR-012, and would bypass the
  permission/reason enforcement required for reprints (FR-013/FR-014).

## Decision 7: Reprint creates a new linked `BadgePrintJob`; old-badge QR revocation reuses the existing credential revoke/reissue action verbatim

**Decision**: Reprinting never mutates the original `BadgePrintJob`; it
creates a new row with `original_print_job_id` pointing at the prior job,
its own `reprint_reason`, and its own audit record. When an event has
"revoke old badge QR on reprint" enabled, `BadgePrinting` calls the existing
Phase 1 `Credentials` revoke-and-reissue action (unchanged) for the
attendee's credential before creating the new print job, then renders the
new job from the reissued credential's QR.

**Rationale**: CR-004 forbids a badge-specific shortcut around credential
revocation; the Phase 1 action is already the sole authority for
revoking/reissuing a QR credential (Phase 2 reused it unmodified for wallet
sync). Preserving every prior `BadgePrintJob` row (rather than overwriting)
keeps a complete, auditable print history per attendee, matching the
append-only evidence pattern used for `ScanEvent` in Phase 2.

**Alternatives considered**:

- Invent a badge-specific "void QR" flag stored on `BadgePrintJob`: rejected;
  it would create a second, weaker trust path for credential validity that
  Phase 2's scan validation would have to special-case, violating CR-004 and
  the constitution's single-authority credential rule.

## Decision 8: Kiosk and printer health reuse Phase 2's bounded-polling dashboard pattern; no new persistent-connection infrastructure

**Decision**: Kiosks send a periodic heartbeat (status, timestamp, optional
printer health) to a bounded `POST` endpoint. The server derives
online/offline from heartbeat recency against a configurable threshold and
stores the kiosk's last-reported printer status. Operations/organizer
viewers read kiosk health through a bounded, tenant/event-scoped endpoint
polled on a short fixed interval, extending the same approach as Phase 2's
`EventCheckInSummary` dashboard.

**Rationale**: SC-007 requires a "short, bounded delay," which polling
already satisfies for Phase 2's check-in counts without adding a
broadcasting/WebSocket dependency that would complicate on-premise parity
(constitution principle III). There is no requirement in this phase for
sub-second health push.

**Alternatives considered**:

- WebSocket/SSE push for kiosk health: deferred as a future enhancement for
  the same reasons Phase 2 deferred it for check-in counts; not required to
  meet SC-007 and would add an operational dependency.

## Decision 9: Walk-up registration reuses the Phase 1 registration/credential-issuance action with an `origin` flag, not a second identity or payment model

**Decision**: `Attendees` gains an `origin` enum (`standard`, `walk_up`),
default `standard`, set once at creation. A new `RegisterWalkUpAttendeeAction`
in `Attendees` validates the event's `walk_up_registration_enabled` toggle,
collects the minimum required fields, and then calls the exact same Phase 1
attendee-creation and credential-issuance code path standard registration
uses (no parallel implementation). On-site payment collection is gated on
whether the event already has an enabled Phase 1 payment adapter/method for
on-site use; if not enabled, the action records the order/attendee as
`payment_pending`/unpaid and surfaces that state explicitly rather than
marking it paid.

**Rationale**: FR-017/FR-018 and CR-004's "no separate identity or payment
model" requirement are satisfied by extending the existing action with one
flag rather than writing a second registration pipeline that could drift
from Phase 1's validation, credential-signing, or audit behavior over time.

**Alternatives considered**:

- A dedicated `WalkUpRegistration` write path independent of Phase 1's
  registration action: rejected; duplicate logic is the exact drift risk
  the constitution's "test-gated, phased delivery" principle warns against
  (later phases must build on the accepted core, not fork it).

## Decision 10: Kiosk registration/session, badge template/print, and walk-up toggle configuration are tenant/event-scoped exactly like Phase 1/2 configuration

**Decision**: `Kiosk`, `BadgeTemplate`, `BadgePrintJob`, and the walk-up
toggle all carry `tenant_id` (+ `event_id` where event-scoped) with the same
composite-foreign-key and fail-closed-unknown-target pattern Phase 1/2
established. No new tenant-scoping mechanism is introduced.

**Rationale**: Consistent application of an already-accepted pattern is
lower risk than inventing a new one, and directly satisfies CR-001.

**Alternatives considered**: None; this directly reuses the accepted
Phase 0/1/2 pattern.
