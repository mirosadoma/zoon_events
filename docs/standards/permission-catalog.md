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
`credential.revoke`, `credential.reissue`, `identity.configure`,
`identity.review`, `identity.data.view`, and `identity.data.manage`.
Phase 2 tenant keys:
`wallet.pass.view`, `wallet.pass.generate`, `wallet.pass.manage`,
`checkin.scan.submit`, `checkin.scan.override`, and `checkin.dashboard.view`.
Platform keys cover tenant, user,
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

Phase 2 workforce permissions are tabulated in `docs/security/permissions.md`.
`wallet.pass.generate` is also available to the public attendee journey through the
order access token, not a workforce role.

Phase 3 tenant keys: `kiosk.manage`, `kiosk.health.view`, `checkin.desk.perform`,
`badge.print`, `badge.reprint`, `badge.template.manage`, and `attendee.walkup.register`.
Kiosk devices authenticate via device-session tokens resolved by the `kiosk.session`
middleware and do not hold user RBAC permissions.

Phase 4 tenant keys: `acs.configure`, `acs.events.view`, `acs.health.view`, and
`acs.emergency.manage`. ACS integrations authenticate with M2M credentials and
capability lists (`authorize`, `event.ingest`, `emergency.ingest`), not workforce
RBAC. Full Phase 4 tables live in `docs/security/permissions-phase4.md`.

Phase 5 tenant keys: `identity.configure`, `identity.review`,
`identity.data.view`, and `identity.data.manage`. Public attendee identity flows
authenticate with the order access token rather than workforce RBAC. Full Phase 5
tables live in `docs/security/permissions-phase5.md`.
