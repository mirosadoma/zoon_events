# Credential Key and Lifecycle Runbook

Credential QR payloads use the `zt1` canonical format and Ed25519 signatures.
Private keys are never stored in the database or printed; metadata contains only
the public key and a runtime secret reference.

For rotation, generate the key through the approved secret store, stage metadata
with `zonetec:credentials:rotate-key`, deploy the reference in the credential
key ring, verify with `zonetec:credentials:keys-check`, then change the previous
key to `verify_only`. Keep historical verification only through the approved
credential validity window, then retire it.

For suspected compromise, disable signing immediately and mark the affected key
`compromised`. This intentionally blocks tokens signed by that key. Distinguish
this incident response from routine retirement, where historical verification
continues. Reissue affected active credentials only after the replacement key
passes readiness.

Revocation is immediate and authoritative even when a previously copied QR is
presented. Reissue locks the attendee credential set, supersedes the old record,
and discloses the replacement QR once. Audit records may contain credential IDs
and safe reason text, never raw QR payloads or attendee PII.
