# Phase 2 Research: Wallet Passes and QR Scanning

**Date**: 2026-07-06

This research resolves the technical choices needed by the Phase 2 plan. The
accepted Phase 0 and Phase 1 architecture remains authoritative unless a
decision below explicitly extends it.

## Decision 1: Extend the modular monolith with a Wallet and a Scanning module

**Decision**: Add a `WalletPasses` module (owns wallet pass records, provider
adapters, and push/update orchestration) and a `Scanning` module (owns scan
events, check-in state, single-entry enforcement, and the offline allowlist
design) to the existing Laravel modular monolith. Both consume the Phase 1
`Credentials` validation contract rather than reimplementing signature or
status checks.

**Rationale**: Wallet delivery and on-site scanning are distinct concerns with
different external dependencies (Apple/Google cloud services vs. an
authoritative local decision) and different consumers (attendee devices vs.
staff devices). Keeping them as separate modules preserves the constitution's
module-ownership rule and lets Phase 3 (kiosk) and Phase 4 (ACS) build on
`Scanning`'s contracts without depending on wallet internals.

**Alternatives considered**:

- One combined "Check-In" module for wallet and scanning: rejected because
  wallet push failures and scan validation failures have unrelated recovery
  paths, and a combined module would blur adapter boundaries.
- Extending the Phase 1 `Credentials` module directly: rejected because
  `Credentials` must stay a stable, minimal validation authority that Phase 2
  consumes; adding wallet/scan concerns there would risk weakening its
  contract per `credential-contract.md`'s documented Phase 1/Phase 2
  boundary.

## Decision 2: Wallet passes carry an opaque reference to the existing credential, never a duplicate trust path

**Decision**: A wallet pass stores only `credential_id`, provider, serial
number, delivery status, and last-pushed time. The QR content embedded in the
pass is the exact same signed token issued in Phase 1. No wallet-specific
signing key, validation rule, or cached status is introduced.

**Rationale**: Constitution principle II requires QR/wallet payloads to be
uniquely identifiable, signed, expiry-aware, and revocable through one
authority. Introducing a second signing or trust path would create a
bypass risk if the two paths ever disagree (for example, a revoked
credential whose wallet pass has not yet received a push update).

**Alternatives considered**:

- Sign a separate wallet-specific token: rejected because it duplicates key
  management and creates two credential formats to keep in sync.
- Cache credential status on the wallet pass row and let scanning trust it:
  rejected because a scan must always re-check authoritative credential state
  (CR-004); a cached wallet-side status could serve a stale accept.

## Decision 3: Apple Wallet uses the PassKit signed-bundle and web-service update protocol

**Decision**: Implement pass generation as a signed `.pkpass` bundle (manifest
SHA-1/SHA-256 digests plus a PKCS#7 detached signature using the tenant's Pass
Type ID certificate) containing `webServiceURL` and a per-pass
`authenticationToken`. Implement the required web-service endpoints:
device registration (`POST /v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}`),
unregistration (`DELETE` on the same path), updated-serial-number listing
(`GET /v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}?passesUpdatedSince={tag}`),
and updated-pass retrieval (`GET /v1/passes/{passTypeIdentifier}/{serialNumber}`).
When a pass changes, send an empty-payload APNs push (topic = Pass Type
Identifier, signed with the same Pass Type ID certificate) to every
registered device for that serial number.

**Rationale**: This is Apple's only supported update mechanism for Wallet
passes; there is no alternative "just call an API" update path. The protocol
is a device-registration/push/pull cycle, not a direct device write, so the
adapter must persist device registrations per pass and treat push delivery as
best-effort (the push only tells the device to ask for changes; it does not
guarantee the change is applied immediately).

**Alternatives considered**:

- Static, non-updating passes only: rejected because FR-007/FR-008 require
  event-change propagation and credential-driven invalidation.
- Re-sending the whole pass file via email/link instead of using the update
  protocol: rejected because it does not update the pass already saved on the
  device and contradicts the "wallet pass reflects event changes" acceptance
  criteria.

## Decision 4: Google Wallet uses the Generic pass object model with a signed-JWT save link and REST updates

**Decision**: Model each event as one `GenericClass` (or one class per ticket
type where visual differentiation is required) and each attendee's pass as
one `GenericObject` addressed by `{issuer_id}.{object_suffix}`. Issue the pass
via an "Add to Google Wallet" link built from a JWT signed with the tenant's
(or platform's) Google Cloud service account key. Update an issued pass with
an authenticated `PATCH` to
`https://walletobjects.googleapis.com/walletobjects/v1/genericobject/{resourceId}`
using the service account's OAuth2 credentials, not the save-link JWT.

**Rationale**: This is the documented Google Wallet API integration surface
for a non-boarding-pass, non-ticket-specific pass type flexible enough for
Zonetec's bilingual, tenant-branded event fields (hero image, text modules,
links module, barcode). Google Wallet objects are updated by direct
authenticated API call rather than a push/pull device protocol, so updates
can be applied synchronously by the adapter without a device-registration
table.

**Alternatives considered**:

- Google Wallet Event Ticket object class: considered for richer native
  ticket UI, but deferred; the Generic pass class covers Phase 2's required
  fields (FR-002) without committing to a ticket-specific schema before
  organizer branding and seat/zone requirements are finalized in a later
  phase. This choice is revisited if Phase 4 needs zone-specific pass
  behavior.
- Client-side pass creation from a mobile app: rejected because Phase 2 has
  no native mobile app; passes are issued from the web confirmation/order
  page.

## Decision 5: Revocation and reissue invalidate wallet passes through the same adapters, not a new mechanism

**Decision**: Credential revocation and reissue (Phase 1 actions) publish a
domain event consumed by `WalletPasses` listeners. For Apple, the listener
marks the pass row `revoked`/`updated` and triggers the standard push (the
device then re-fetches and Wallet may show the pass as invalid per
`voided`/expired pass semantics or updated relevant fields, depending on
supported pass fields); for Google, the listener issues a `PATCH` setting the
object `state` to `EXPIRED` (Google Wallet's documented mechanism for making
an issued object no longer usable/prominent). Neither path can force instant
removal from a device that is offline; this limitation is documented rather
than hidden.

**Rationale**: Both platforms expose an object/pass "state" concept intended
for exactly this purpose (expiring/invalidating an issued object). Using the
documented state transition avoids inventing a non-standard revocation
signal that wallet apps would not recognize, and keeps the authoritative
entry decision at scan time (Decision 2) rather than relying on the device
state.

**Alternatives considered**:

- Deleting the Google object or the registered Apple device: rejected;
  deletion removes update ability entirely and is not how either platform
  models "this credential is no longer valid but the pass record should
  remain for history."

## Decision 6: Single-entry/duplicate enforcement is a per-event (optionally per-ticket-type) configuration flag evaluated at scan time

**Decision**: Add a boolean-equivalent single-entry configuration on the
event (with an optional ticket-type-level override) that the scan validation
service reads before deciding whether a second accepted scan of the same
credential is "duplicate" or a new "accepted" entry (for multi-entry events).
Anti-passback (direction-aware, zone-aware duplicate logic) remains explicitly
deferred to Phase 4 per the spec's Phase Alignment section.

**Rationale**: `all_plan.md` §17.4 requires "first valid entry accepted,
second entry rejected if anti-passback or single-entry mode is enabled,"
implying single-entry is a per-event configurable rule distinct from the
more complex zone-aware anti-passback rule that needs zone/lane data not yet
modeled in this phase.

**Alternatives considered**:

- Always enforce single-entry: rejected because some event tiers
  legitimately allow re-entry without full ACS anti-passback (e.g., a
  corporate event with a single unmonitored exit/re-entry door).
- Implement full anti-passback now: rejected because it requires the
  zone/lane model that `all_plan.md` explicitly assigns to Phase 4.

## Decision 7: Scan result recording and check-in state update happen in one authoritative, audited local transaction

**Decision**: A scan request performs credential validation (delegated to the
Phase 1 `CredentialValidator` contract), single-entry evaluation, scan-event
insertion, and attendee check-in status update inside one `AuditedTransaction`
consistent with the Phase 0/1 pattern, with wallet notifications and
dashboard cache updates dispatched after commit.

**Rationale**: Constitution principle VII and CR-003 require required state
and audit evidence to commit or fail together; a scan's accepted/rejected
outcome is itself security-relevant state that must not desynchronize from
its audit record or the attendee's check-in status.

**Alternatives considered**:

- Asynchronous scan processing (queue the scan, respond optimistically):
  rejected because SC-002 requires a same-request accepted/rejected result
  under 2 seconds; on-site staff need an immediate authoritative answer, not
  an eventually-consistent one.

## Decision 8: Real-time dashboard uses short-interval polling of an aggregated read model, not a persistent push channel

**Decision**: Maintain a per-event aggregate (registration count, check-in
count, rejected/duplicate/override counts) updated within the same scan
transaction or immediately after commit, exposed through a bounded,
tenant/event-scoped read endpoint that the organizer dashboard polls on a
short fixed interval.

**Rationale**: This keeps Phase 2 within the existing HTTP/API architecture
(no new persistent-connection infrastructure such as WebSockets is
introduced elsewhere in the codebase) while still satisfying the "short,
bounded delay" success criteria. It avoids adding a new deployment
dependency that would complicate on-premise parity (CR-008).

**Alternatives considered**:

- WebSocket/SSE push channel: deferred as a future enhancement; not required
  to meet the bounded-delay success criteria and would add an operational
  dependency (broadcasting driver) not otherwise required by this phase.

## Decision 9: Offline-tolerant scanning ships as a documented design plus a minimal local-allowlist reference implementation

**Decision**: Define the offline allowlist as a signed, time-windowed,
event-scoped export of active credential identifiers (opaque digests, not raw
personal data) that a staff scanning device can fetch while online and
consult while offline, recording local scan attempts for later reconciliation.
Full production-grade offline client tooling (native device app) is treated
as a pilot-driven scope decision per `all_plan.md` §30.5 ("partially
implemented if required for pilot"), but the sync/export/reconciliation
contract and conflict-flagging behavior are specified now so any
implementation depth remains testable against the same contract.

**Rationale**: This satisfies FR-024–FR-027 without over-committing to a
specific offline client technology before pilot requirements are known, while
still making the reconciliation contract concrete and testable.

**Alternatives considered**:

- Require full offline-capable native apps in this phase: rejected; not
  justified without confirmed pilot venues with known connectivity failure
  modes, and `all_plan.md` explicitly allows partial implementation.
- Skip offline design entirely: rejected because the spec and master plan
  both require the design and reconciliation/conflict rules to exist and be
  testable even if the client implementation is minimal.

## Decision 10: Wallet provider credentials and certificates are secret references managed like Phase 1 payment/notification secrets

**Decision**: Apple Pass Type ID certificates/keys and Google service-account
keys are stored as secret references (not raw values) resolved inside the
adapter infrastructure only, following the same pattern as Moyasar and
Unifonic credentials in Phase 1.

**Rationale**: Consistent with constitution principle II ("secrets MUST never
be committed, logged, or returned to clients") and the existing adapter
contract's secret-reference convention; avoids introducing a second secret-
handling pattern for this phase.

**Alternatives considered**: None; this directly reuses the accepted Phase 1
pattern.
