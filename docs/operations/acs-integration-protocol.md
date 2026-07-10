# ACS M2M Integration Protocol and Credential Rotation

Owner: Security Engineering  
Last reviewed: 2026-07-07

This guide documents how an external ACS (Access Control System) integrates with
Zonetec over machine-to-machine (M2M) credentials. Phase 4 ships with a
`MockAcsAdapter` only; the real Runa ACS transport is **not confirmed**
(`all_plan.md` §38.2, §39.2). Until Runa documents REST/WebSocket/TCP/MQTT
behavior, latency budgets, and offline semantics, production integrations MUST
use the mock adapter in non-production environments and treat outbound transport
as adapter-owned infrastructure.

## Blocking assumption — Runa transport unconfirmed

Per `all_plan.md` §38.2 and §39.2:

- The exact ACS wire protocol (REST, WebSocket, TCP, MQTT, or other) is unknown.
- Expected latency, offline behavior, zone/lane representation, emergency signal
  delivery, and anti-passback semantics on the ACS side are open questions.
- Zonetec therefore exposes **inbound** HTTP endpoints (`/api/v1/acs/v1/*`) as the
  authoritative contract. Any future Runa adapter implements
  `App\Modules\AccessControl\Contracts\AcsAdapter` without changing domain
  decision order (`specs/005-acs-access-control/contracts/authorization-contract.md`).

## Authentication

ACS integrations authenticate with an `Authorization` header:

```http
Authorization: AcsIntegration <raw-secret>
```

- The raw secret is shown **exactly once** when an operator registers a credential
  via `POST /api/v1/tenant/events/{event_id}/acs/integration-credentials`.
- Zonetec stores only a SHA-256 hash; lost secrets cannot be retrieved.
- Credentials are scoped to one `(tenant_id, event_id)` pair.
- Capabilities are explicit: `authorize`, `event.ingest`, `emergency.ingest`.
- Missing capability → `403 acs_capability_denied`.
- Invalid, expired, or revoked secret → `401 acs_integration_invalid`.

State-changing integration requests require an `Idempotency-Key` header (same
semantics as organizer APIs).

## Integration endpoints

Base path: `/api/v1/acs/v1` (no Sanctum session).

| Operation | Method | Capability | Idempotent key |
| --- | --- | --- | --- |
| Gate authorization | `POST /authorize` | `authorize` | `Idempotency-Key` |
| Entry/exit callback | `POST /events` | `event.ingest` | `Idempotency-Key` + `external_event_id` |
| Emergency signal | `POST /emergency` | `emergency.ingest` | `Idempotency-Key` |

Contract reference: `specs/005-acs-access-control/contracts/openapi.yaml`.

### Authorize (`POST /authorize`)

Request body:

- `external_acs_lane_id` (required) — lane identifier in the ACS namespace.
- `direction` (required) — `entry` or `exit`.
- `credential_reference` (optional) — QR payload or credential reference
  validated through the **single** Phase 1/2 credential path.

Response `200` for both allow and deny:

- `decision` — `allow` | `deny`
- `reason_code` — stable catalog value (e.g. `allowed`, `credential_expired`,
  `anti_passback_violation`, `emergency_fail_open`)
- `access_event_id` — appended `AccessEvent` row
- `scan_event_id` — set when an admission lane records a Phase 2 check-in

Responses never include signing keys or raw credential payloads.

### Event ingest (`POST /events`)

- `external_event_id` — ACS-supplied idempotency key (unique per tenant).
- `external_acs_lane_id`, `event_type` (`entry`|`exit`), `occurred_at`.
- Optional `credential_reference` for anti-passback state updates.
- Returns `202`; duplicate `external_event_id` is a success no-op.

### Emergency callback (`POST /emergency`)

- `action` — `raise` or `clear`.
- Optional `external_acs_zone_id` (null = event-wide).
- `signal_source` — `acs`, `fire_alarm`, or `system`.
- `occurred_at` — signal timestamp.

## Credential registration and rotation

Operators with `acs.configure` register credentials:

```http
POST /api/v1/tenant/events/{event_id}/acs/integration-credentials
Idempotency-Key: <uuid>
X-Tenant-ID: <tenant>

{
  "name": "Venue turnstile cluster",
  "capabilities": ["authorize", "event.ingest", "emergency.ingest"]
}
```

Response includes `secret` once. TTL defaults to `ACS_INTEGRATION_TTL_HOURS`
(config, default 168 h).

### Rotation procedure

1. Register a new credential with the required capabilities.
2. Deploy the new secret to the ACS integration layer (secret manager or ACS
   config — never commit to git, tickets, or chat).
3. Verify authorize, event ingest, and emergency callbacks against staging.
4. Confirm audit row `acs_integration.credential_registered` with no secret in
   metadata.
5. The prior active credential is revoked automatically on successful registration.
6. Remove the old secret from ACS configuration after cutover.

### Compromise response

1. Register a replacement credential immediately (revokes the compromised one).
2. Review `access.*` and `acs_emergency.*` audit rows for the affected event.
3. Block inbound traffic at the network layer if rotation cannot complete in time.
4. Never log or echo the raw `Authorization` header.

## Configuration

| Key | Purpose |
| --- | --- |
| `ACS_ADAPTER` | `mock` (default) or future real adapter |
| `ACS_INTEGRATION_TTL_HOURS` | M2M credential lifetime |
| `ACS_LATENCY_BUDGET_MS` | Application authorization budget (default 500 ms) |
| `acs.lane.offline_threshold_seconds` | Lane health offline threshold (default 120 s) |

Validate with `php artisan zonetec:config:validate --env=testing`. Configuration
output MUST NOT print integration secrets.

## Security boundaries

- ACS integrations never receive organizer Sanctum tokens.
- Domain code calls `CredentialValidator::validate()` only from
  `AuthorizeGateAction` and `IngestAccessEventAction` (architecture test enforced).
- Cross-tenant and cross-event lane/credential references are rejected
  identically to unknown targets (`acs_lane_unmapped`, `acs_event_out_of_scope`,
  or `credential_unknown`).
