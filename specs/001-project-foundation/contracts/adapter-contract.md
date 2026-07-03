# Zonetec External Adapter Contract

## Purpose

All external services, vendors, storage systems, and hardware are reached through provider-neutral adapter interfaces owned by the Integrations module. Core modules depend on capability contracts, never vendor SDKs or provider response types.

Phase 0 supplies contracts, registry behavior, stable result/error types, a fake adapter, and a reusable contract-test suite. It supplies no production payment, notification, wallet, identity, ACS, kiosk, printer, marketplace, or other product adapter.

## Required Capability Contract

Each adapter capability defines:

- A stable capability name and contract version.
- Provider-neutral request and result value objects.
- Required tenant or explicit platform invocation context.
- Data classification, minimization, residency, and retention obligations.
- Authentication/credential requirements without exposing credential values.
- Timeout budget and cancellation behavior.
- Retry eligibility, maximum attempts/backoff, and idempotency semantics.
- Stable error categories and whether an outcome is safe to retry.
- Observability fields and required redaction.
- Online, degraded, offline, recovery, and reconciliation behavior.
- Sandbox/fake strategy and production-readiness evidence.

Conceptual interface:

```text
CapabilityAdapter.execute(
    AdapterInvocationContext context,
    CapabilityRequest request
) -> AdapterResult
```

The exact PHP interfaces are created during implementation within `app/Modules/Integrations/Contracts`; this document governs their semantics.

## Invocation Context

Every tenant invocation includes:

- Trusted tenant ID resolved by the Tenancy module.
- Actor or service identity.
- Correlation ID.
- Idempotency key/reference when the capability can cause an external effect.
- Locale when provider-neutral content is localized.
- Remaining timeout budget.
- Declared data classification.

Platform invocation requires an explicit platform context and permission. Missing context fails before adapter selection.

Provider credentials are resolved inside the infrastructure adapter through approved secret configuration. They are never carried in context, job payloads, logs, audit metadata, or errors.

## Stable Result

An adapter result contains:

- `status`: `succeeded`, `accepted`, `rejected`, `unavailable`, or `unknown`.
- Provider-neutral result/reference fields.
- Retry classification: `never`, `safe`, or `reconcile_first`.
- Safe reason code.
- Correlation and idempotency references.
- Sanitized diagnostic metadata from an allow-list.

Unknown outcomes occur when an external side effect may have happened but confirmation was lost. They must never be blindly retried; reconciliation is required by the future capability contract.

## Stable Error Categories

| Category | Meaning | Default retry |
|----------|---------|---------------|
| `invalid_request` | Adapter contract input is invalid | Never |
| `authentication_failed` | Provider credential/configuration rejected | Never until configuration changes |
| `authorization_failed` | Provider denies configured account/capability | Never until configuration changes |
| `rate_limited` | Provider throttled the call | Safe after provider delay when idempotent |
| `timeout_before_send` | No request was sent | Safe when operation permits |
| `timeout_unknown_outcome` | Request may have caused an effect | Reconcile first |
| `provider_unavailable` | Provider/network unavailable | Safe only under declared retry policy |
| `provider_rejected` | Business-neutral provider rejection | Never unless input changes |
| `malformed_response` | Response violates adapter expectations | Reconcile first for side effects |
| `configuration_invalid` | Required non-secret configuration absent/invalid | Never until fixed |
| `internal_adapter_failure` | Unexpected sanitized adapter failure | Based on operation; default never |

Core modules see these categories, not vendor codes. Vendor codes may appear only in redacted internal diagnostics where policy permits.

## Timeout, Retry, and Idempotency

- Every invocation has an explicit timeout shorter than the caller's remaining budget.
- Retries are bounded and use backoff with jitter.
- A retryable external side effect requires a stable provider idempotency key derived from the Zonetec operation, never from raw personal data.
- Adapter retries must not outlive the operation's idempotency record.
- Jobs record attempt count and sanitized failure category.
- Permanent failure is mapped to a stable application outcome and audit event.

## Tenant Isolation and Data Handling

- Adapter configuration is selected by trusted tenant context or an explicit platform configuration path.
- Cache/configuration keys begin with tenant scope.
- Requests contain only the minimum fields required by the capability.
- Responses are validated and reduced to provider-neutral fields before crossing the adapter boundary.
- Logs and metrics include tenant ID, capability, adapter name/version, outcome, duration, and correlation ID, but no credentials or raw sensitive payload.
- Cross-border movement and residency exceptions must be explicit in a future feature specification and adapter readiness record.

## Registry and Selection

- Application code asks the adapter registry for a capability and approved adapter key.
- Unknown, disabled, or unready adapters fail with `configuration_invalid`/`provider_unavailable`; no implicit network provider is selected.
- Fake adapters are explicitly labeled `testing` and cannot pass a production-readiness check.
- Switching providers does not change core request/result types.

## Common Contract Test Suite

Every adapter implementation must pass the same tests:

1. Reject missing or mismatched tenant/platform context before external work.
2. Map valid requests and successful results without provider types escaping.
3. Enforce timeout and classify pre-send versus unknown-outcome timeout.
4. Apply bounded retry only to declared safe cases.
5. Reuse a stable idempotency key across retries.
6. Map authentication, authorization, rate limit, rejection, unavailable, malformed, and unexpected failures.
7. Redact credentials and sensitive request/response fields from logs, errors, metrics, and audit metadata.
8. Preserve correlation and tenant context through queued execution.
9. Report degraded/offline state and recover without duplicate effects.
10. Demonstrate sandbox/fake behavior and separately provide production readiness evidence.

## Production Readiness Record

A future production adapter cannot be enabled until its owned record documents:

- Contract/capability version and supported deployment modes.
- Provider and regional endpoints.
- Credential provisioning/rotation/revocation.
- Data inventory, classification, residency, retention, and deletion.
- Timeout, retry, idempotency, reconciliation, and rate-limit evidence.
- Monitoring, alerts, support owner, incident and provider-outage runbooks.
- Sandbox and production contract-test evidence.
- Security/privacy/compliance approval where applicable.
- Rollout, rollback, degraded/offline, and recovery procedures.
