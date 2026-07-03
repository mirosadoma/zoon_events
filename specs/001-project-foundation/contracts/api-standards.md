# Zonetec Phase 0 API Standards

## Contract Authority

`contracts/openapi.yaml` is the Phase 0 review contract. Implementation routes, request validation, resources, errors, authorization tests, and generated documentation must conform to it. An incompatible change requires a new major API version, migration guidance, support window, and retirement approval.

## Protocol and Representation

- HTTPS is mandatory outside local development.
- Version 1 routes begin `/api/v1`.
- Requests and responses use JSON unless downloading a completed audit export.
- Field names use `snake_case`.
- Identifiers are opaque strings; clients must not infer ordering or tenant ownership.
- Timestamps use UTC RFC 3339. User-visible rendering may use tenant/user locale and time zone, but contracts retain UTC.
- Unknown request fields are rejected on security-sensitive write operations.

## Authentication and Context

- Protected routes accept a Sanctum bearer token.
- The same-origin foundation dashboard may use a Fortify-backed secure browser session; it is subject to the same user lifecycle, tenant context, policies, rate limits, validation, and audit requirements as bearer-token requests.
- Tenant routes require `X-Tenant-ID`. The server verifies an active tenant, active user, active membership, and permission before application work.
- Platform routes live under `/api/v1/platform`, require platform permissions, and do not infer platform scope from a missing tenant header.
- Tenant and platform context may not be combined in one operation.
- `Accept-Language` supports `ar` and `en`; the selected language affects message text only.

## Correlation

- Clients may send `X-Correlation-ID` using 1–64 safe characters.
- Invalid or absent values are replaced with a generated ID.
- Every response returns the effective `X-Correlation-ID`.
- The value propagates to audit logs, structured logs, jobs, events/listeners, idempotency records, and adapter invocations.

## Authorization and Resource Discovery

- Authentication never implies authorization.
- Policies or explicit permission middleware guard every protected operation.
- A resource ID owned by another tenant produces the same `404 resource_not_found` response as an absent ID.
- A known in-scope resource for which the actor lacks an action permission produces `403 forbidden`.
- Denied and cross-tenant attempts are audited without storing target secrets or revealing target existence to the caller.

## Errors

Errors use `application/problem+json` and include:

```json
{
  "type": "https://docs.zonetec.example/problems/validation_failed",
  "title": "Validation failed",
  "status": 422,
  "code": "validation_failed",
  "detail": "One or more fields are invalid.",
  "instance": "/api/v1/tenant/roles",
  "correlation_id": "01J...",
  "errors": {
    "name": ["The name has already been used in this tenant."]
  }
}
```

Rules:

- `code` is stable and language-neutral.
- `detail`, `title`, and field messages have equivalent Arabic and English catalogs.
- Production errors never include stack traces, SQL, internal paths, connection strings, secret values, or cross-tenant facts.
- Expected baseline codes include `unauthenticated`, `forbidden`, `tenant_context_required`, `tenant_context_invalid`, `resource_not_found`, `validation_failed`, `conflict`, `idempotency_conflict`, `rate_limited`, `dependency_unavailable`, and `service_unavailable`.

## Success Envelopes

Single-resource responses use:

```json
{
  "data": {},
  "meta": {
    "correlation_id": "01J..."
  }
}
```

Collection responses use:

```json
{
  "data": [],
  "meta": {
    "correlation_id": "01J...",
    "page_size": 50,
    "has_more": true,
    "next_cursor": "opaque-value"
  },
  "links": {
    "next": "/api/v1/tenant/audit-logs?cursor=opaque-value"
  }
}
```

Delete/revoke operations may return `204` with no body but still return `X-Correlation-ID`.

## Pagination and Filtering

- Cursor pagination is required for unbounded collections.
- Default page size is 50; maximum is 100.
- Ordering is deterministic by `(created_at, id)` descending unless the contract states otherwise.
- Cursors are opaque, signed, and bound to normalized filters and tenant/platform scope.
- Invalid, expired, or cross-scope cursors return `422 validation_failed`.
- Date filters use inclusive `from` and exclusive `to` UTC boundaries.
- Audit searches require a bounded configured date range.

## Idempotency

- Retriable state-changing operations marked in OpenAPI require `Idempotency-Key`.
- Keys are 16–255 characters and stored only as hashes.
- A key is bound to actor, tenant/platform scope, operation ID, and canonical request digest.
- Matching replay returns the original safe response and an `Idempotent-Replayed: true` header.
- Reuse with a different request returns `409 idempotency_conflict`.
- In-progress duplicate requests return `409 conflict` with retry guidance.
- The replay window is documented per operation and must outlive the longest supported client retry window.

## Rate Limits

- Authentication is limited by normalized account identifier and source fingerprint.
- Tenant operations are limited by tenant, actor, and operation class.
- Platform and audit export operations use stricter privileged limits.
- `429` responses include `Retry-After` and do not reveal whether an account or cross-tenant target exists.

## Dashboard Contract Parity

- Every dashboard capability must also exist as a documented `/api/v1` capability.
- Inertia/dashboard controllers invoke public application actions and policies; they do not query module persistence directly.
- Browser mutations use CSRF protection and the same validators, idempotency semantics where applicable, events, transactions, and audit writer as API mutations.
- Explicit view models allow-list page props; Eloquent models and secret-bearing configuration are never serialized to pages.
- Client-side permission-aware navigation is convenience only; server policies remain authoritative.
- Cross-tenant or unauthorized resources appear in zero rendered props and use the same safe not-found/forbidden semantics as the API.

## Feature Flags and Configuration

- Feature-flag keys are stable and immutable; definitions state type, owner, safe default, lifecycle, and optional-capability classification.
- Tenant overrides require trusted tenant context and permission. Missing, invalid, disabled, or expired overrides fall back to the safe platform default.
- Mandatory security and compliance controls cannot be represented as feature flags.
- Phase 0 tenant configuration APIs are read-only and expose validated schema/value inspection only; domain provisioning, brand assets, theme editing, and product rendering remain out of scope.

## Telemetry

- Correlation and trusted tenant scope propagate through structured logs, metrics, distributed traces, error reports, jobs, events, and adapters.
- Telemetry never contains secrets, raw sensitive payloads, raw IP addresses, or full user-agent strings.
- Telemetry exporter failure is bounded and observable; it cannot block core behavior or substitute for transactional audit evidence.

## Compatibility

Backward-compatible within `/api/v1`:

- Add optional response fields.
- Add optional request fields with safe defaults.
- Add new endpoints or new enum values only when clients are documented to tolerate them.

Breaking:

- Remove/rename a field or operation.
- Make an optional field required.
- Change field type, meaning, authorization, idempotency, or error semantics incompatibly.
- Narrow accepted values without a migration period.

Breaking changes require `/api/v2`, migration documentation, announced support dates, and contract tests for the overlap period.

## OpenAPI Quality Gate

The contract gate must verify:

1. OpenAPI document lint passes.
2. Every application route is documented or explicitly internal.
3. Every protected operation declares bearer auth and tenant/platform context.
4. Permissions and audit actions appear in operation descriptions or extensions.
5. Common error responses and correlation headers are referenced.
6. Request/response examples contain no real personal or secret data.
7. Implementation feature tests validate each operation's success and principal failure branches.
8. Contract changes receive compatibility classification.
