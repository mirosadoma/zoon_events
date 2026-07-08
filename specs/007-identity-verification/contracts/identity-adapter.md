# Adapter Contracts: Identity Verification (Phase 5)

Two provider-neutral adapter interfaces isolate all external identity capabilities.
Domain logic depends only on these interfaces; production brands (Nafath/Absher/
Yaqeen and any face/liveness vendor) are never referenced in business code
(`all_plan.md` §20.3, Constitution VI). Each ships with a Mock (default) and a
Fake (tests), bound in `IdentityVerificationServiceProvider` via
`match(config('identity-verification.default_*_adapter','mock'))` — the same
pattern as `AccessControlServiceProvider`.

## GovernmentIdentityAdapter

`app/Modules/IdentityVerification/Contracts/GovernmentIdentityAdapter.php`

| Method | Purpose | Notes |
|---|---|---|
| `startVerification(context): StartResult` | Begin a verification session for the attendee/event | Returns a session/redirect handle or an unsupported result; idempotent per `Idempotency-Key` |
| `handleCallback(payload): CallbackResult` | Process a signed provider callback | Signature/route-secret verified; processed idempotently; never trusts client-supplied tenant/event |
| `fetchResult(reference): VerificationResult` | Pull the verification outcome | Returns success/failure/unsupported + minimized attributes |
| `mapAttributes(raw): VerifiedAttributes` | Map provider payload to `verified_name`, `verified_nationality` | Drops all non-minimal fields; raw payload never persisted in returnable form |

- **Failure modes**: unreachable → `identity_provider_unavailable` (offer fallback/
  retry, never fabricate success); rejected → status `rejected`; unsupported →
  `pending` + face fallback where enabled.
- **Cross-cutting**: timeout, retry with backoff, idempotency, error mapping, and
  observability per `docs/standards/adapter-authoring.md`. Production readiness is
  evidence-gated; until then `MockGovernmentIdentityAdapter` is bound.

## FaceCaptureAdapter

`app/Modules/IdentityVerification/Contracts/FaceCaptureAdapter.php`

| Method | Purpose | Notes |
|---|---|---|
| `submitCapture(context, capture): CaptureResult` | Persist a minimized capture (template preferred) | Returns opaque reference + optional liveness result; stored encrypted with `retention_until` |
| `liveness(capture): LivenessResult?` | Optional liveness check | `passed`/`failed`/`unavailable`; absence does not block the fallback |

- **Minimization**: templates preferred over raw images; raw biometrics never
  returned through any API or resource.
- **Review**: a submitted capture creates a `pending` verification routed to the
  `identity.review` queue for approve/reject (reason required on reject).

## Shared adapter rules

- Provider-neutral results only; no brand names in domain code.
- All calls tenant/event-scoped from server context, never a client identifier.
- Mocks cover success, failure, unsupported, and unavailable paths for contract
  tests; Fakes allow deterministic assertions in feature tests.
- On-premise deployments run the same interfaces with local processing and
  cross-border transfer disabled by default (`config('identity-verification.residency')`).
