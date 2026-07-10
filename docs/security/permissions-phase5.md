# Phase 5 workforce permissions

Owner: Security Engineering  
Last reviewed: 2026-07-08

Executable source: `Database\Seeders\PermissionSeeder::definitions()`. CI compares
documentation with the seeder. Phase 0-4 keys remain in
`docs/standards/permission-catalog.md` and `docs/security/permissions-phase4.md`.

Attendee identity verification uses the public order access token for self-service
consent and provider flows. Those routes are not protected by workforce RBAC.

| Key | Module | Scope | Risk | Description | Primary enforcement |
| --- | --- | --- | --- | --- | --- |
| `identity.configure` | identity-verification | tenant | sensitive | Configure event-level and ticket-tier identity verification requirements. | `permission:identity.configure,tenant` on requirements management routes |
| `identity.review` | identity-verification | tenant | sensitive | Review face-capture and manual identity verification submissions. | `permission:identity.review,tenant` on review queue routes |
| `identity.data.view` | identity-verification | tenant | privileged | View minimized sensitive identity verification metadata. | `permission:identity.data.view,tenant` on compliance detail routes |
| `identity.data.manage` | identity-verification | tenant | privileged | Delete sensitive identity verification data and perform exceptional compliance actions. | `permission:identity.data.manage,tenant` on compliance deletion routes |

## System role defaults

| System role | Phase 5 grants |
| --- | --- |
| Tenant Administrator | all four keys |
| Identity Reviewer | `identity.review` |
| Compliance Administrator | `identity.data.view`, `identity.data.manage` |
| Custom roles | empty until explicitly granted |
