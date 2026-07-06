# Phase 2 workforce permissions

Owner: Security Engineering  
Last reviewed: 2026-07-06

Executable source: `Database\Seeders\PermissionSeeder::definitions()`. CI compares
documentation with the seeder. Phase 0/1 tenant and platform keys remain documented in
`docs/standards/permission-catalog.md`.

`wallet.pass.generate` is available to the authenticated public attendee journey through
the order access token, not through a workforce role. All other Phase 2 keys below are
workforce-role-scoped like Phase 1.

| Key | Module | Scope | Risk | Description | Primary enforcement |
| --- | --- | --- | --- | --- | --- |
| `wallet.pass.view` | wallet-passes | tenant | standard | View wallet pass status for an event. | `dashboard.permission:wallet.pass.view` on tenant wallet UI routes |
| `wallet.pass.generate` | wallet-passes | tenant | standard | Generate wallet passes for attendees (workforce tooling; public flow uses order token). | Public wallet routes via order access token; organizer tooling via policy |
| `wallet.pass.manage` | wallet-passes | tenant | sensitive | Manage wallet pass lifecycle (revoke, reissue sync). | Wallet pass management actions and jobs |
| `checkin.scan.submit` | scanning | tenant | sensitive | Submit staff QR scans and offline batch reconciliation. | `permission:checkin.scan.submit,tenant` on scan and offline routes |
| `checkin.scan.override` | scanning | tenant | privileged | Override duplicate scan rejections with a documented reason. | Scan submit body `override` + `checkin.scan.override` permission |
| `checkin.dashboard.view` | scanning | tenant | standard | View event check-in dashboard counters. | `permission:checkin.dashboard.view,tenant` and dashboard web middleware |

System role defaults:

| System role | Phase 2 grants |
| --- | --- |
| System Tenant Administrator | all six keys (idempotent seeder update) |
| On-Site Staff | `checkin.scan.submit`, `checkin.dashboard.view` only |

Custom roles start empty of Phase 2 keys until explicitly granted.
