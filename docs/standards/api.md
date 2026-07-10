# API standard

Owner: API Governance  
Last reviewed: 2026-07-03

The source contract is `specs/001-project-foundation/contracts/openapi.yaml`. APIs use
`/api/v1`, Sanctum, explicit tenant/platform scope, Problem Details, correlation headers,
snake_case envelopes, bounded signed cursors, rate limits, and hashed idempotency keys.
Breaking changes require a new major API version and migration/support/retirement policy.
