# Phase 4 workforce permissions

Owner: Security Engineering  
Last reviewed: 2026-07-07

Executable source: `Database\Seeders\PermissionSeeder::definitions()`. CI compares
documentation with the seeder. Phase 0–3 keys remain in
`docs/standards/permission-catalog.md`.

ACS integration actors authenticate with M2M credentials (`Authorization:
AcsIntegration <secret>`) and capability lists — not workforce RBAC. See
`docs/operations/acs-integration-protocol.md`.

| Key | Module | Scope | Risk | Description | Primary enforcement |
| --- | --- | --- | --- | --- | --- |
| `acs.configure` | access-control | tenant | sensitive | Create zones, lanes, rules; register/rotate ACS integration credentials. | `Phase4Policy::configureAcs` on management controllers |
| `acs.events.view` | access-control | tenant | standard | View gate access events feed. | `Phase4Policy::viewGateEvents` on `GateEventsController` |
| `acs.health.view` | access-control | tenant | standard | View ACS integration and lane health summary. | `Phase4Policy::viewAcsHealth` on `AcsHealthController` |
| `acs.emergency.manage` | access-control | tenant | privileged | Raise and clear emergency egress via operator API. | `Phase4Policy::manageEmergency` on `EmergencyController` |

## M2M capabilities (not RBAC permissions)

Registered per integration credential; enforced by `acs.capability:*` middleware:

| Capability | Endpoint |
| --- | --- |
| `authorize` | `POST /api/v1/acs/v1/authorize` |
| `event.ingest` | `POST /api/v1/acs/v1/events` |
| `emergency.ingest` | `POST /api/v1/acs/v1/emergency` |

A credential with only `authorize` cannot post events or emergencies
(`403 acs_capability_denied`).

## System role defaults

| System role | Phase 4 grants |
| --- | --- |
| Tenant Administrator | all four keys |
| ACS Operator | `acs.configure`, `acs.events.view`, `acs.health.view` (not emergency) |
| Custom roles | empty until explicitly granted |
