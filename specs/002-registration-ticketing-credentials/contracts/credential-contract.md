# Credential Token and Validation Contract

## Token

```text
zt1.<key_id>.<base64url(canonical_payload)>.<base64url(ed25519_signature)>
```

The signature covers the ASCII bytes:

```text
zt1.<key_id>.<base64url(canonical_payload)>
```

Base64url uses no padding. Canonical payload keys are sorted and encoded without
insignificant whitespace.

## Payload

```json
{
  "cid": "credential ULID",
  "eid": "event ULID",
  "iat": 1783072800,
  "exp": 1783159200,
  "nonce": "128-bit random base64url value",
  "tid": "ticket type ULID"
}
```

No tenant identifier or personal, contact, form, order, payment, identity, or
access data appears in the token. Tenant is resolved from trusted invocation
context and checked against the credential record.

## Key Lifecycle

- `pending`: not used.
- `active`: signs and verifies.
- `verify_only`: verifies existing credentials but never signs.
- `retired`: rejected after its approved verification window.
- `compromised`: always rejected and triggers controlled response.

Private key material is loaded from a secret reference only during signing.
Public keys and key metadata may be distributed to later approved verifiers.

## Validation Order

1. Enforce length and token-segment bounds.
2. Require version `zt1` and a syntactically valid key ID.
3. Decode strictly; reject non-canonical or malformed payloads.
4. Load an allowed key and verify Ed25519 signature.
5. Validate required claims, identifier syntax, issue/expiry bounds, and nonce.
6. Load the authoritative credential in trusted tenant/event scope.
7. Compare token digest, nonce digest, event, ticket type, key, and expiry.
8. Reject expired, revoked, superseded, wrong-scope, or compromised-key state.
9. Return a stable result without attendee data.

## Stable Results

`valid`, `malformed`, `invalid_signature`, `unknown_key`, `expired`, `revoked`,
`superseded`, `wrong_tenant`, `wrong_event`, and `service_unavailable`.

Wrong tenant/event results are logged internally but exposed as an equivalent
invalid/not-found result to untrusted callers.

## Phase Boundary

Phase 1 validates entitlement state only. It does not consume a credential,
record entry/exit, enforce anti-passback, perform offline scanning, or update
check-in state. Those operations belong to Phase 2 and reuse this token.

