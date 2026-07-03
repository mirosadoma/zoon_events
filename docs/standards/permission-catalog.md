# Permission catalog

Owner: Security Engineering  
Last reviewed: 2026-07-03

The executable source is `Database\Seeders\PermissionSeeder::definitions()`. Tenant keys:
`tenant.view`, `membership.view`, `membership.manage`, `role.view`, `role.manage`,
`role.assign`, `audit.view`, `audit.export`, `audit.verify`, `configuration.view`,
`feature_flag.view`, and `feature_flag.manage`. Platform keys cover tenant, user,
role, audit, feature-flag and configuration operations, health visibility, and explicit
access recovery. CI compares documentation with the seeder.

Exact platform keys: `platform.tenant.view`, `platform.tenant.manage`,
`platform.user.view`, `platform.user.manage`, `platform.role.view`,
`platform.role.manage`, `platform.role.assign`, `platform.access.recover`,
`platform.audit.view`, `platform.audit.export`, `platform.audit.verify`,
`operations.health.view`, `platform.feature_flag.view`,
`platform.feature_flag.manage`, and `platform.configuration.view`.
