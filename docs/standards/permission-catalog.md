# Permission catalog

Owner: Security Engineering  
Last reviewed: 2026-07-03

The executable source is `Database\Seeders\PermissionSeeder::definitions()`. Tenant keys:
`tenant.view`, `membership.view`, `membership.manage`, `role.view`, `role.manage`,
`role.assign`, `audit.view`, `audit.export`, `audit.verify`, `configuration.view`,
`feature_flag.view`, `feature_flag.manage`, `event.view`, `event.manage`,
`event.publish`, `event.cancel`, `event.reopen`, `event.archive`,
`registration.manage`, `ticketing.manage`,
`order.view`, `order.manage`, `payment.refund`, `attendee.view`,
`attendee.manage`, `credential.view`, `credential.validate`,
`credential.revoke`, and `credential.reissue`. Platform keys cover tenant, user,
role, audit, feature-flag and configuration operations, health visibility, and explicit
access recovery. CI compares documentation with the seeder.

Every tenant Phase 1 route requires the narrow operation permission in addition
to authenticated tenant context. Public registration/payment and signed
provider callbacks are deliberately outside organizer RBAC and instead use
host resolution, opaque identifiers, idempotency, signatures/route secrets, and
dedicated throttles. Revocation takes effect on the next request.

Exact platform keys: `platform.tenant.view`, `platform.tenant.manage`,
`platform.user.view`, `platform.user.manage`, `platform.role.view`,
`platform.role.manage`, `platform.role.assign`, `platform.access.recover`,
`platform.audit.view`, `platform.audit.export`, `platform.audit.verify`,
`operations.health.view`, `platform.feature_flag.view`,
`platform.feature_flag.manage`, and `platform.configuration.view`.
