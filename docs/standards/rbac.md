# RBAC standard

Owner: Security Engineering  
Last reviewed: 2026-07-03

Permissions are exact immutable keys. Tenant and platform roles use separate tables and
evaluators. Assignments must be active, unrevoked, unexpired, and scope-consistent.
Empty/custom roles grant nothing. System roles cannot be modified or deleted, and the last
active tenant administrator cannot be removed without the platform recovery permission.
Client-side navigation is never an authorization control.
