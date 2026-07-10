# Adapter authoring standard

Owner: Integrations  
Last reviewed: 2026-07-03  
Applies to: SaaS and on-premise

Adapters implement `CapabilityAdapter`, declare a stable descriptor, accept only a validated
`AdapterInvocationContext`, and return provider-neutral `AdapterResult` values. Every adapter must
pass the shared success, rejection, timeout-before-send, unknown-outcome, offline/recovery,
tenant-isolation, stable-idempotency, bounded-retry, telemetry, and redaction tests.

Production readiness additionally requires credential rotation, regional endpoints, data
classification/residency/retention, timeout and reconciliation evidence, monitoring ownership,
incident and outage runbooks, security approval, and rollback evidence. The fake adapter is
testing-only and cannot satisfy production readiness.

Run:

```text
php artisan test --group=adapter-contract
php artisan test --group=adapter-security
```
