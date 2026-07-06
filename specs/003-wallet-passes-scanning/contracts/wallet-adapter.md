# Wallet Pass Adapter Contract

## Purpose

Keep credential and check-in rules independent of Apple's and Google's wallet
platforms. This contract governs the `WalletPasses` module's internal
provider-neutral interface and the Apple and Google adapters that implement
it, following the conventions of
`specs/001-project-foundation/contracts/adapter-contract.md`.

## Non-Negotiable Boundary

A wallet pass is a presentation of an existing Phase 1 credential. This
contract MUST NOT introduce a second way to mark a credential valid,
expired, or revoked. Every operation below either reads current credential
state or reacts to a credential/event change already decided elsewhere.

## Invocation Context

Every call carries trusted:

- tenant and event identifiers;
- attendee and credential identifiers;
- wallet pass identifier and provider (`apple`, `google`);
- operation and correlation identifiers;
- idempotency key for generation/update calls;
- timeout budget.

Provider certificates, private keys, and service-account credentials are
resolved inside infrastructure from a `secret_reference`. They never enter
domain requests, logs, audit metadata, exceptions, job payloads, or API
responses.

## Operations

### Generate pass

Input: attendee reference, active credential reference, event display data
(name, date, location, branding-approved title/logo reference), ticket type
label, optional zone/tier label, locale, idempotency key.

Preconditions: the referenced credential MUST be `active` at call time
(re-checked, not cached). A non-active credential fails closed with
`credential_not_active` before any provider call.

Result:

- `pass_serial_number` (opaque, unique per tenant/provider);
- provider-specific issuance artifact:
  - Apple: a signed `.pkpass` bundle reference (manifest digests + PKCS#7
    signature over the Pass Type ID certificate), containing `webServiceURL`
    and a freshly generated per-pass `authenticationToken`;
  - Google: a signed JWT suitable for an "Add to Google Wallet" link
    (`https://pay.google.com/gp/v/save/<jwt>`), encoding one `GenericObject`
    referencing the tenant/event `GenericClass`;
- stable status (`created`, `failed`);
- safe reason category on failure.

### Update pass

Input: existing `pass_serial_number`, changed display fields (event date,
time, location, branding), idempotency key.

Result: stable status (`updated`, `unavailable`, `failed`) and safe reason
category.

Behavior differs by provider and MUST be hidden behind this one contract:

- Apple: persist the new pass content, then send an empty-payload APNs push
  (topic = Pass Type Identifier, signed with the same Pass Type ID
  certificate) to every currently registered device
  (`WalletPassAppleDeviceRegistration` rows with `unregistered_at IS NULL`)
  for that serial number. A successful call means the push was accepted by
  APNs, not that the device has applied the update; devices pull the new
  content when they process the push.
- Google: issue an authenticated `PATCH` to
  `https://walletobjects.googleapis.com/walletobjects/v1/genericobject/{resourceId}`
  with the changed fields. A successful call means the object is updated
  immediately; Google Wallet reflects it on the user's device without a
  separate push step.

### Revoke or expire pass

Input: existing `pass_serial_number`, reason category, idempotency key.

Result: stable status (`revoked`, `unavailable`, `failed`).

- Apple: mark the pass row revoked and push an update per "Update pass"
  above; the updated pass content reflects the invalidated/expired
  presentation (e.g., relevant fields cleared or a voided indicator per the
  approved pass design). This does not and cannot force immediate removal
  from an offline device.
- Google: `PATCH` the object's `state` field to `EXPIRED`, the documented
  mechanism for making an issued Google Wallet object no longer valid/
  prominent.

Revocation/expiry of the wallet pass NEVER substitutes for authoritative
credential revocation; it is a best-effort device-side signal only. Entry
decisions always come from live scan validation (see `scan-contract.md`).

### Handle device registration callback (Apple only)

Input: `deviceLibraryIdentifier`, `passTypeIdentifier`, `serialNumber`, push
token, `ApplePass {authenticationToken}` header.

Result: `201` on new registration, `200` on already-registered, `401` on
authentication-token mismatch. Persists or updates one
`WalletPassAppleDeviceRegistration` row.

### Handle device unregistration callback (Apple only)

Input: same identifiers as registration, `ApplePass {authenticationToken}`
header.

Result: `200` on success. Sets `unregistered_at` on the matching
registration row; does not delete history.

### Handle updated-serial-numbers query (Apple only)

Input: `deviceLibraryIdentifier`, `passTypeIdentifier`, optional
`passesUpdatedSince` tag.

Result: `200` with serial numbers and a new tag, or `204` when nothing
changed, scoped only to passes registered by that device.

### Handle updated-pass fetch (Apple only)

Input: `passTypeIdentifier`, `serialNumber`, `ApplePass {authenticationToken}`
header, optional `If-Modified-Since`.

Result: `200` with the current signed `.pkpass` bundle, `304` if unchanged,
`401` on authentication-token mismatch.

## Stable Error Categories

Reuses the Phase 0 adapter categories
(`specs/001-project-foundation/contracts/adapter-contract.md`) plus:

| Category | Meaning | Default retry |
|---|---|---|
| `credential_not_active` | Referenced credential is not active at call time | Never without a new active credential |
| `certificate_expired` | Pass Type ID certificate or service-account key has expired | Never until configuration is rotated |
| `device_unregistered` | Target device has unregistered or its push token is invalid | Never for that device; remove registration |
| `provider_object_conflict` | Google Wallet object/class already exists with conflicting data | Reconcile first |

## Tenant Isolation and Data Handling

- Pass content includes only fields listed in `data-model.md` (event name,
  date, location, attendee name, ticket type, QR credential, optional
  zone/tier label); no national identifier, biometric, or payment data.
- Apple device push tokens and `authenticationToken` values, and Google
  service-account keys, are treated as sensitive and excluded from logs,
  metrics, audit metadata, and error messages.
- Apple web-service endpoints authenticate every call using the per-pass
  `authenticationToken` (register/unregister/fetch-pass) or the device's
  registered identity (updated-serials query) rather than tenant session
  authentication, because the caller is Apple's Wallet app, not a logged-in
  user; the resolved tenant/event/credential scope is still enforced from
  the pass record before any data is returned.

## Contract Test Matrix

Every provider adapter must pass:

1. Reject generation for a non-active credential without calling the
   provider.
2. Generate succeeds and returns a pass containing only approved fields.
3. Update pushes/patches successfully and is retried on transient failure.
4. Revoke/expire succeeds and is idempotent when repeated.
5. Update/revoke against an unknown or already-superseded pass fails safely
   without leaking whether the target ever existed to an untrusted caller.
6. Apple: device registration, unregistration, updated-serials, and
   updated-pass endpoints enforce `authenticationToken` and never expose
   another tenant's pass through a guessed serial number.
7. Apple: an invalid APNs push token is detected and the registration is
   removed without retrying the push.
8. Google: JWT generation uses the correct issuer/class/object identifiers
   and is rejected if signed with an unapproved service-account key.
9. Both providers redact certificates/keys/tokens from every log, audit, and
   error path.
10. Both providers report a distinguishable `unavailable`/`failed` state
    when the provider is unreachable, without blocking the underlying
    credential or registration operation.
