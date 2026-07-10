# Phase 0 Data Model

## Conventions

- MySQL 8.4 LTS with `utf8mb4` and UTC application/database timestamps.
- Externally visible records use 26-character ULIDs.
- Tenant-owned tables have a non-null `tenant_id`, a foreign key to `tenants.id`, and tenant-first indexes/unique constraints.
- Status values are represented by application enums and database check constraints.
- Foreign-key deletion defaults to restriction. Lifecycle records are deactivated rather than cascaded away.
- Secret material is never stored in metadata or audit payloads. Passwords and Sanctum token values use one-way hashes.
- JSON is limited to sanitized metadata, policy references, filters, and response snapshots where field variability is necessary; core searchable fields remain columns.
- Platform and tenant authorization use separate tables. A nullable tenant ID never means platform privilege.

## Relationship Overview

```text
User ──< TenantMembership >── Tenant
  │              │
  │              └──< TenantRoleAssignment >── TenantRole ──< TenantRolePermission >── Permission
  │
  └──< PlatformRoleAssignment >── PlatformRole ──< PlatformRolePermission >── Permission

Tenant ──< AuditLog
Tenant ──< AuditExport
Tenant ──< IdempotencyRecord
Tenant ──< TenantConfiguration
Tenant ──< FeatureFlagOverride >── FeatureFlagDefinition

User ──< PersonalAccessToken
User ──< AuditLog (actor reference, logically retained after lifecycle changes)
```

## Entity: Tenant

**Table**: `tenants`

| Field | Type | Rules |
|-------|------|-------|
| `id` | ULID | Primary key, immutable |
| `name` | string(160) | Required, trimmed |
| `slug` | string(100) | Required, globally unique, lowercase URL-safe value |
| `status` | string(24) | `active`, `suspended`, or `deactivated` |
| `default_locale` | string(10) | Required; initially `en` or `ar` |
| `timezone` | string(64) | Required IANA time-zone name |
| `data_residency_region` | string(64) | Required deployment/policy region identifier |
| `policy_profile` | JSON, nullable | Approved non-secret retention/residency policy references |
| `created_by_user_id` | ULID | Required platform user reference |
| `suspended_at` | timestamp, nullable | Set only in suspended state |
| `deactivated_at` | timestamp, nullable | Set only in deactivated state |
| `created_at`, `updated_at` | timestamp | Required |

**Indexes/constraints**:

- Unique `slug`.
- Index `(status, created_at, id)`.
- Check lifecycle timestamps agree with status.

**State transitions**:

```text
active <-> suspended
active/suspended -> deactivated
deactivated is terminal through ordinary APIs
```

Suspension blocks new tenant work but preserves records. Deactivation invalidates active tenant context and queued tenant work.

## Entity: User

**Table**: `users`

| Field | Type | Rules |
|-------|------|-------|
| `id` | ULID | Primary key |
| `name` | string(160) | Required |
| `email` | string(254) | Required, canonical lowercase value, globally unique |
| `password` | string | Required one-way password hash |
| `status` | string(24) | `active`, `suspended`, or `deactivated` |
| `preferred_locale` | string(10) | Required; initially `en` or `ar` |
| `last_authenticated_at` | timestamp, nullable | Updated after successful auth |
| `suspended_at` | timestamp, nullable | Status-aligned |
| `deactivated_at` | timestamp, nullable | Status-aligned |
| `created_by_user_id` | ULID, nullable | Null only for controlled bootstrap |
| `created_at`, `updated_at` | timestamp | Required |

**Indexes/constraints**:

- Unique canonical `email`.
- Index `(status, created_at, id)`.
- Deactivated or suspended users cannot authenticate or use existing tokens.

**State transitions**:

```text
active <-> suspended
active/suspended -> deactivated
deactivated is terminal through ordinary APIs
```

Deactivation revokes all active tokens and active memberships in one audited operation.

## Entity: Personal Access Token

**Table**: `personal_access_tokens` (Laravel Sanctum)

Standard Sanctum token fields are retained. Phase 0 adds or enforces:

- Tokenable user reference.
- Hashed token only; plaintext is returned once.
- Coarse `api` ability.
- Required device/token name.
- Optional expiry controlled by configuration.
- Revocation on user suspension/deactivation.

Tokens do not contain tenant permissions. Tenant membership and RBAC are evaluated on each request.

## Entity: Tenant Membership

**Table**: `tenant_memberships`

| Field | Type | Rules |
|-------|------|-------|
| `id` | ULID | Primary key |
| `tenant_id` | ULID | Required tenant foreign key |
| `user_id` | ULID | Required user foreign key |
| `status` | string(24) | `active`, `suspended`, or `deactivated` |
| `created_by_user_id` | ULID | Required grantor |
| `suspended_at` | timestamp, nullable | Status-aligned |
| `deactivated_at` | timestamp, nullable | Status-aligned |
| `created_at`, `updated_at` | timestamp | Required |

**Indexes/constraints**:

- Unique `(tenant_id, user_id)`.
- Index `(tenant_id, status, created_at, id)`.
- Index `(user_id, status)`.
- Membership cannot be active when its tenant or user is inactive.

**State transitions**: Same active/suspended/deactivated flow as Tenant. A membership cannot be suspended/deactivated if doing so would leave no active tenant administrator, unless an audited platform recovery action supplies a replacement.

## Entity: Permission

**Table**: `permissions`

| Field | Type | Rules |
|-------|------|-------|
| `id` | ULID | Primary key |
| `key` | string(120) | Required immutable machine key |
| `module` | string(80) | Owning module |
| `description` | string(500) | Required English governance description |
| `scope` | string(16) | `tenant` or `platform` |
| `risk_level` | string(16) | `standard`, `sensitive`, or `privileged` |
| `created_at`, `updated_at` | timestamp | Required |

**Indexes/constraints**:

- Unique `key`.
- Check scope/risk values.
- Seeded catalog entries are updated by key; keys are never silently renamed or deleted.

**Initial tenant permissions**:

- `tenant.view`
- `membership.view`, `membership.manage`
- `role.view`, `role.manage`, `role.assign`
- `audit.view`, `audit.export`, `audit.verify`
- `configuration.view`
- `feature_flag.view`, `feature_flag.manage`

**Initial platform permissions**:

- `platform.tenant.view`, `platform.tenant.manage`
- `platform.user.view`, `platform.user.manage`
- `platform.role.view`, `platform.role.manage`, `platform.role.assign`
- `platform.access.recover`
- `platform.audit.view`, `platform.audit.export`, `platform.audit.verify`
- `operations.health.view`
- `platform.feature_flag.view`, `platform.feature_flag.manage`
- `platform.configuration.view`

## Entity: Tenant Role

**Table**: `tenant_roles`

| Field | Type | Rules |
|-------|------|-------|
| `id` | ULID | Primary key |
| `tenant_id` | ULID | Required tenant foreign key |
| `name` | string(100) | Required, tenant-unique |
| `description` | string(500), nullable | Sanitized |
| `is_system` | boolean | System roles cannot be deleted or renamed |
| `created_by_user_id` | ULID | Required |
| `created_at`, `updated_at` | timestamp | Required |

**Indexes/constraints**:

- Unique `(tenant_id, name)`.
- Index `(tenant_id, created_at, id)`.

The seeded `Tenant Administrator` system role includes all Phase 0 tenant permissions. New custom roles contain no permissions by default.

## Entity: Tenant Role Permission

**Table**: `tenant_role_permissions`

| Field | Type | Rules |
|-------|------|-------|
| `tenant_id` | ULID | Required; must match role tenant |
| `tenant_role_id` | ULID | Required role foreign key |
| `permission_id` | ULID | Required permission with `tenant` scope |
| `granted_by_user_id` | ULID | Required |
| `created_at` | timestamp | Required |

**Indexes/constraints**:

- Composite primary/unique `(tenant_role_id, permission_id)`.
- Index `(tenant_id, permission_id)`.
- Application and integration tests enforce matching tenant and permission scope.

## Entity: Tenant Role Assignment

**Table**: `tenant_role_assignments`

| Field | Type | Rules |
|-------|------|-------|
| `id` | ULID | Primary key |
| `tenant_id` | ULID | Required; must match membership and role |
| `tenant_membership_id` | ULID | Required |
| `tenant_role_id` | ULID | Required |
| `granted_by_user_id` | ULID | Required |
| `expires_at` | timestamp, nullable | Optional bounded assignment |
| `revoked_at` | timestamp, nullable | Null while active |
| `revoked_by_user_id` | ULID, nullable | Required when revoked |
| `created_at`, `updated_at` | timestamp | Required |

**Indexes/constraints**:

- At most one active assignment for `(tenant_membership_id, tenant_role_id)`.
- Index `(tenant_id, tenant_membership_id, revoked_at, expires_at)`.
- The role and membership must belong to the same tenant.
- Revoking the last effective Tenant Administrator assignment is rejected.

## Entity: Platform Role

**Table**: `platform_roles`

Fields mirror Tenant Role except there is no `tenant_id`. Names are globally unique, creation requires a platform actor, and all changes are privileged audit events.

Initial system roles:

- `Platform Administrator`
- `Security Auditor`
- `Operations Viewer`

## Entity: Platform Role Permission

**Table**: `platform_role_permissions`

Links a platform role to a `platform`-scope permission. Composite unique `(platform_role_id, permission_id)`, with grantor and creation time.

## Entity: Platform Role Assignment

**Table**: `platform_role_assignments`

Links a user to a platform role with grantor, optional expiry, revocation fields, and timestamps. Only active users with active, non-expired assignments receive platform permissions.

## Entity: Tenant Configuration

**Table**: `tenant_configurations`

| Field | Type | Rules |
|-------|------|-------|
| `id` | ULID | Primary key |
| `tenant_id` | ULID | Required tenant foreign key |
| `key` | string(80) | `branding`, `domains`, `residency`, or `retention` |
| `schema_version` | unsigned integer | Required version of the project-owned schema |
| `value` | JSON | Validated, secret-free configuration value |
| `status` | string(16) | `draft` or `active` |
| `created_by_user_id` | ULID | Required |
| `activated_by_user_id` | ULID, nullable | Required for active values |
| `activated_at` | timestamp, nullable | Required for active values |
| `created_at`, `updated_at` | timestamp | Required |

**Indexes/constraints**:

- Unique `(tenant_id, key)`.
- Index `(tenant_id, status, key)`.
- The value must validate against the declared schema version before persistence and again before activation.
- Residency/retention values must satisfy deployment policy; branding/domain values contain references only, not uploaded assets or provisioned domains.
- Cross-tenant references and secret-like keys are rejected.
- Every create/change/activation is permission-checked and audited with a sanitized change summary.

Phase 0 exposes schema/value inspection and controlled foundation storage. It does not implement domain verification/provisioning, asset upload, theme editing, or branded product rendering.

## Entity: Feature Flag Definition

**Table**: `feature_flags`

| Field | Type | Rules |
|-------|------|-------|
| `id` | ULID | Primary key |
| `key` | string(120) | Immutable globally unique machine key |
| `name` | string(160) | Required display name |
| `description` | string(500) | Required governance description |
| `owner` | string(120) | Required owning module/team identifier |
| `value_type` | string(16) | `boolean`, `integer`, or `string` |
| `default_value` | JSON | Required and type-valid |
| `status` | string(16) | `draft`, `active`, `disabled`, or `retired` |
| `security_class` | string(24) | Must be `optional_capability`; mandatory controls cannot be flags |
| `created_by_user_id` | ULID | Required platform actor |
| `created_at`, `updated_at` | timestamp | Required |

**Indexes/constraints**:

- Unique immutable `key`.
- Default value must match `value_type`.
- Retired keys cannot be reused.
- Definitions cannot target tenant isolation, authentication, authorization, audit integrity, secret protection, residency enforcement, or other mandatory controls.
- Changes require platform permission, idempotency, and audit evidence.

## Entity: Feature Flag Override

**Table**: `feature_flag_overrides`

| Field | Type | Rules |
|-------|------|-------|
| `id` | ULID | Primary key |
| `tenant_id` | ULID | Required tenant foreign key |
| `feature_flag_id` | ULID | Required flag foreign key |
| `value` | JSON | Required and valid for definition type |
| `status` | string(16) | `active` or `disabled` |
| `reason` | string(500) | Required governance reason |
| `created_by_user_id` | ULID | Required |
| `expires_at` | timestamp, nullable | Optional bounded rollout expiry |
| `created_at`, `updated_at` | timestamp | Required |

**Indexes/constraints**:

- Unique `(tenant_id, feature_flag_id)`.
- Index `(tenant_id, status, expires_at)`.
- The definition must be active and override value must match its type.
- Evaluation requires trusted tenant context; missing, invalid, disabled, or expired overrides return the safe platform default.
- Override writes require tenant feature-flag permission and transactional audit evidence.

## Entity: Audit Log

**Table**: `audit_logs`

| Field | Type | Rules |
|-------|------|-------|
| `id` | ULID | Primary key |
| `scope` | string(16) | `tenant` or `platform` |
| `tenant_id` | ULID, nullable | Required only for tenant scope |
| `actor_type` | string(32) | `user`, `service`, `anonymous`, or `system` |
| `actor_id` | ULID, nullable | Null only for anonymous/system cases |
| `action` | string(160) | Cataloged stable action key |
| `target_type` | string(120), nullable | Stable logical type |
| `target_id` | string(64), nullable | Logical identifier |
| `outcome` | string(16) | `succeeded`, `denied`, or `failed` |
| `reason_code` | string(120), nullable | Sanitized stable reason |
| `channel` | string(32) | `api`, `console`, `job`, `scheduler`, or `system` |
| `correlation_id` | string(64) | Required |
| `request_id` | string(64), nullable | Per-request identifier |
| `source_fingerprint` | string(128), nullable | Privacy-preserving source fingerprint |
| `client_fingerprint` | string(128), nullable | Privacy-preserving coarse client fingerprint; no raw user-agent |
| `change_summary` | JSON, nullable | Allow-listed, size-bounded field-level differences |
| `metadata` | JSON, nullable | Allow-listed, bounded, secret-free context |
| `occurred_at` | timestamp(6) | Required event time |
| `integrity_algorithm` | string(32) | Initially `hmac-sha256-v1` |
| `integrity_key_id` | string(64) | Versioned key identifier |
| `integrity_hash` | binary/string(64) | Canonical payload HMAC |
| `created_at` | timestamp(6) | Insert time |

**Indexes/constraints**:

- Check tenant ID is present only for tenant scope.
- Index `(tenant_id, occurred_at, id)`.
- Index `(tenant_id, actor_id, occurred_at, id)`.
- Index `(tenant_id, action, occurred_at, id)`.
- Index `(scope, occurred_at, id)`.
- No `updated_at`, soft-delete field, update API, or delete API.
- Metadata/change-summary size is bounded and secret-like keys are rejected before insert.
- Passwords, tokens, secrets, designated sensitive values, raw IP addresses, full user-agent strings, and full before/after objects are forbidden.
- A secret/sensitive field may record only its field name and a redacted `changed` marker.

User/target foreign keys are intentionally not cascading: audit evidence remains after lifecycle changes.

## Entity: Audit Export

**Table**: `audit_exports`

| Field | Type | Rules |
|-------|------|-------|
| `id` | ULID | Primary key |
| `scope` | string(16) | `tenant` or `platform` |
| `tenant_id` | ULID, nullable | Required for tenant export |
| `requested_by_user_id` | ULID | Required |
| `filters` | JSON | Validated bounded date/action/actor filters |
| `status` | string(24) | `pending`, `processing`, `completed`, `failed`, `expired` |
| `storage_path` | string(500), nullable | Private tenant/platform-scoped path |
| `record_count` | unsigned integer, nullable | Set on completion |
| `failure_code` | string(120), nullable | Sanitized |
| `expires_at` | timestamp | Required and policy-bounded |
| `started_at`, `completed_at` | timestamp, nullable | State-aligned |
| `created_at`, `updated_at` | timestamp | Required |

**Indexes/constraints**:

- Index `(tenant_id, status, created_at, id)`.
- Tenant path begins `tenants/{tenant_id}/audit-exports/`; platform exports use a distinct restricted prefix.
- Download authorization is re-evaluated; possession of an export ID is insufficient.

**State transitions**:

```text
pending -> processing -> completed -> expired
                    \-> failed
pending/failed/completed -> expired (cleanup)
```

The job is idempotent. A duplicate worker does not create multiple final files.

## Entity: Idempotency Record

**Table**: `idempotency_records`

| Field | Type | Rules |
|-------|------|-------|
| `id` | ULID | Primary key |
| `scope` | string(16) | `tenant` or `platform` |
| `tenant_id` | ULID, nullable | Required for tenant operations |
| `actor_id` | ULID | Required |
| `operation` | string(160) | Stable operation ID |
| `key_hash` | string(64) | SHA-256 of caller key; raw key not stored |
| `request_hash` | string(64) | Canonical request digest |
| `state` | string(16) | `processing`, `completed`, or `failed` |
| `response_status` | unsigned small integer, nullable | Completed response status |
| `response_body` | JSON, nullable | Bounded safe response snapshot or stable resource reference |
| `expires_at` | timestamp | Required |
| `created_at`, `updated_at` | timestamp | Required |

**Indexes/constraints**:

- Unique `(scope, tenant_id, actor_id, operation, key_hash)` with a separate platform-safe uniqueness strategy because null is never used as an authorization signal.
- A matching key with a different request hash returns an idempotency conflict.
- Expired records are removed by scheduled maintenance.

## Transient Value Objects

### TenantContext

- `tenant_id`
- `membership_id`
- authenticated `actor_id`
- correlation/request identifiers
- locale and deployment mode

Immutable for one request/job/listener. It is resolved from trusted persisted state, never directly from an unverified header or serialized job payload.

### AdapterInvocationContext

- tenant context (required for tenant calls) or explicit platform scope
- actor/service identity
- correlation and idempotency identifiers
- locale
- timeout budget
- data classification

Contains no provider credential. Credentials are resolved inside the infrastructure adapter from approved secret configuration.

### HealthStatus

- `status`: `ok`, `degraded`, or `unavailable`
- check category
- observed time and duration
- safe reason code

Public responses expose only aggregate state. Detailed instances require platform permission.

### ConfigurationDefinition

Documentation/runtime schema containing key, owner, purpose, sensitivity, allowed values, default, required environments, restart behavior, validation rule, and redaction behavior. Secret values themselves are not persisted in this schema.

### FeatureFlagEvaluation

- flag key and value type
- trusted tenant/platform context
- platform default
- optional validated active tenant override
- selected effective value and source (`default` or `tenant_override`)
- evaluated timestamp

Evaluation is deterministic, uncached in Phase 0, and never accepts caller-supplied tenant scope without resolution.

### GovernanceException

Governed documentation record containing rule, necessity, owner, risk, compensating controls, approval, created date, expiry/remediation date, and status. Phase 0 does not require a database table because exceptions are reviewed versioned artifacts, not runtime behavior.

## Audit Event Catalog Baseline

| Category | Example action keys |
|----------|---------------------|
| Authentication | `auth.token.issued`, `auth.failed`, `auth.token.revoked`, `auth.locked_out` |
| Tenant lifecycle | `tenant.created`, `tenant.activated`, `tenant.suspended`, `tenant.deactivated` |
| User lifecycle | `user.provisioned`, `user.activated`, `user.suspended`, `user.deactivated` |
| Membership | `membership.created`, `membership.suspended`, `membership.deactivated`, `membership.last_admin_denied` |
| Authorization | `role.created`, `role.updated`, `role.deleted`, `role.permission_changed`, `role.assigned`, `role.revoked`, `authorization.denied` |
| Privileged access | `platform.tenant_accessed`, `platform.access_recovery` |
| Audit | `audit.searched`, `audit.export_requested`, `audit.export_downloaded`, `audit.integrity_verified`, `audit.integrity_failed` |
| Configuration/operations | `configuration.created`, `configuration.changed`, `configuration.activated`, `configuration.validation_failed`, `adapter.configuration_changed`, `adapter.invocation_failed` |
| Feature flags | `feature_flag.created`, `feature_flag.changed`, `feature_flag.retired`, `feature_flag.override_set`, `feature_flag.override_removed`, `feature_flag.evaluation_failed` |

Every action records succeeded, denied, or failed outcome as applicable.

## Retention, Deletion, and Residency

- Users, memberships, and authorization records follow approved tenant/deployment lifecycle policy; hard deletion must not break retained audit evidence.
- Audit retention duration is mandatory configuration backed by an approved policy. Legal hold prevents eligible purge.
- Audit exports have a short configurable expiry and are deleted by scheduled cleanup; export audit evidence remains per audit policy.
- Idempotency records use a short operation-appropriate replay window.
- Tenant records are stored only in the configured deployment/residency boundary. Adapters cannot move data outside that boundary unless a later approved contract explicitly permits it.
- Backups, restores, and replicas must preserve the same residency and access policy; their implementation is documented operationally.

## Migration Verification

Migration tests must prove:

1. A fresh MySQL database migrates and seeds successfully.
2. Every tenant-owned table has the expected tenant foreign key and tenant-first index.
3. Cross-tenant role/membership/assignment combinations are rejected by application invariants and tests.
4. Platform privilege cannot be represented in tenant tables or inferred from null tenant context.
5. Audit records can be inserted and verified but have no application mutation path.
6. State mutation and required audit insert roll back together on audit failure.
7. Seeders are idempotent and do not grant permissions to custom roles.
8. Migration rollback is safe in development/test and production rollback guidance is documented.
9. Tenant configuration and feature-flag override uniqueness, foreign keys, type/schema validation, and cross-tenant denial are verified.
10. Audit change summaries reject raw sensitive fixtures, raw IP/user-agent values, and full object snapshots.
