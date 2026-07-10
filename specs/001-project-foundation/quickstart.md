# Phase 0 Validation Quickstart

This guide describes how to validate the planned Laravel foundation after implementation. It does not use Docker and does not include product-feature workflows.

## Prerequisites

- PHP 8.3 with Laravel-required extensions
- Composer 2.x
- MySQL 8.4 LTS
- Node.js 20+ and npm for the React/Inertia dashboard build, frontend checks, and OpenAPI lint
- A local filesystem path writable by the application for private audit exports

The current workspace has PHP 8.3 and Composer, but the MySQL client is not currently on `PATH`. Install/configure MySQL 8.4 or point the application at an existing test instance before running database validation.

## 1. Install and Configure

After implementation:

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
npm run build
```

Create separate local and test MySQL databases using your approved native MySQL administration method. Configure `.env` and `.env.testing` with least-privilege credentials. Do not commit either file.

Required foundation configuration includes:

- Application environment, key, URL, locale, fallback locale, and debug safety
- MySQL connection for application and integration tests
- Database queue connection
- Private filesystem disk/path
- Current audit HMAC key ID and key ring supplied through secret configuration
- Audit retention and export-expiry bounds
- Telemetry exporter selection with a local/no-network-safe default
- SaaS or on-premise deployment profile

Validate without printing secret values:

```powershell
php artisan zonetec:config:validate
php artisan config:cache
php artisan zonetec:config:validate
```

Expected: both validations pass. Removing a required value or enabling unsafe production debug causes a non-zero result naming the setting, never its value.

## 2. Migrate and Seed

```powershell
php artisan migrate:fresh --seed --env=testing
php artisan migrate:status --env=testing
```

Expected:

- Framework, Sanctum, queue, users, tenants, memberships, tenant/platform RBAC, audit, audit export, and idempotency tables exist.
- Permission/system-role seeders can run repeatedly without duplicates.
- Development/test seed data is synthetic and creates two independent tenants.
- No registration, ticketing, payment, credential/wallet, kiosk/scanner, ACS, identity, marketplace, or product-adapter tables exist.

Production bootstrap must require explicit administrator values; it must never create a known default password.

## 3. Validate the API Contract

```powershell
npx redocly lint specs/001-project-foundation/contracts/openapi.yaml
php artisan test --testsuite=Contract
```

Expected:

- OpenAPI lint succeeds.
- Contract tests confirm documented routes, bearer authentication, tenant header requirements, common errors, correlation headers, and schemas.
- Feature-flag and tenant-configuration inspection operations conform to the contract.
- No undocumented public Phase 0 route exists.

Review [API standards](contracts/api-standards.md) and the [OpenAPI contract](contracts/openapi.yaml) before exercising requests.

## 4. Run the Application Processes

Use separate terminals:

```powershell
php artisan serve
```

```powershell
php artisan queue:work --queue=foundation --tries=3
```

For dashboard asset development:

```powershell
npm run dev
```

For one-shot scheduler validation:

```powershell
php artisan schedule:run
```

Expected: the application, compiled React/Inertia dashboard, queue worker, and scheduler run natively without Docker/Sail or a runtime external-network dependency.

## 5. Verify Health and Redaction

```powershell
Invoke-RestMethod http://127.0.0.1:8000/api/v1/health/live
Invoke-RestMethod http://127.0.0.1:8000/api/v1/health/ready
```

Expected:

- Live returns `status: ok`.
- Ready returns only aggregate status and no host, database name, credentials, tenant values, stack trace, or connection detail.
- Stopping MySQL changes readiness to unavailable within 60 seconds while live remains available.
- Restoring MySQL returns readiness to ready within 60 seconds.
- The authenticated platform health endpoint returns safe check categories to an actor with `operations.health.view`.

## 6. Authenticate and Select Tenant Context

Use synthetic seeded credentials supplied by the test seeder:

```powershell
$login = Invoke-RestMethod `
  -Method Post `
  -Uri http://127.0.0.1:8000/api/v1/auth/token `
  -ContentType 'application/json' `
  -Body (@{
    email = $env:ZONETEC_TEST_ADMIN_EMAIL
    password = $env:ZONETEC_TEST_ADMIN_PASSWORD
    device_name = 'phase-0-validation'
  } | ConvertTo-Json)

$token = $login.data.token
$authHeaders = @{ Authorization = "Bearer $token" }
$tenantChoices = Invoke-RestMethod `
  -Headers $authHeaders `
  -Uri http://127.0.0.1:8000/api/v1/auth/tenants
```

Expected:

- Authentication returns a token once and creates sanitized audit evidence.
- `/auth/tenants` lists only active memberships.
- No public user or tenant registration endpoint exists.

## 7. Prove Tenant Isolation

Set tenant A and tenant B identifiers from the synthetic test fixtures:

```powershell
$tenantAHeaders = @{
  Authorization = "Bearer $token"
  'X-Tenant-ID' = $env:ZONETEC_TEST_TENANT_A
  'X-Correlation-ID' = 'phase0-isolation-a'
}

$tenantBHeaders = @{
  Authorization = "Bearer $token"
  'X-Tenant-ID' = $env:ZONETEC_TEST_TENANT_B
  'X-Correlation-ID' = 'phase0-isolation-b'
}
```

Validate:

1. An authorized Tenant A membership list succeeds with Tenant A headers.
2. Removing `X-Tenant-ID` fails closed.
3. A user without active Tenant B membership cannot use Tenant B headers.
4. Supplying a Tenant B role/membership/export ID while in Tenant A returns the same not-found contract as a random ID.
5. Cross-tenant attempts are audited but responses do not disclose target existence.
6. Tenant context is cleared between requests handled by the same process.

Run the full automated matrix:

```powershell
php artisan test --group=tenant-isolation
```

Expected: 100% of cross-tenant attempts are denied across HTTP, persistence entry points, jobs, events/listeners, files/exports, idempotency, logs, and fake adapters.

## 8. Verify RBAC and Last-Administrator Protection

Using the Tenant A administrator:

1. Create a custom role; verify it has no permissions.
2. Grant only `audit.view`.
3. Assign it to a second active membership.
4. Verify audit reads succeed and membership/role writes are forbidden.
5. Revoke the role and verify access is denied immediately.
6. Attempt to suspend or remove the final effective Tenant Administrator; verify `409 conflict`.
7. Verify successful and denied operations have audit evidence.

```powershell
php artisan test --group=rbac
```

Expected: every seeded permission has at least one allowed and one denied test, tenant and platform assignments never combine, and custom roles receive no implicit grants.

## 9. Verify Audit Atomicity and Integrity

```powershell
php artisan test --group=audit
php artisan zonetec:audit:verify --recent
```

Expected:

- Every cataloged security action records actor, scope, action, target, timestamp, outcome, reason, channel, and correlation.
- Secret-like metadata is rejected/redacted.
- Simulated audit persistence failure rolls back the associated role/tenant/user state mutation.
- A controlled test alteration is detected by HMAC verification.
- No application route supports audit update/delete.

Request a bounded tenant audit export. Run the queue worker and verify:

- Job restores tenant/correlation context.
- File is written only under `tenants/{tenantId}/audit-exports/`.
- Another tenant receives not-found for the export ID.
- Download authorization is rechecked and audited.
- Expired export cleanup removes the file while preserving audit evidence.

## 10. Verify Adapter Boundary

```powershell
php artisan test --group=adapter
```

Expected: the fake adapter passes every case in [adapter-contract.md](contracts/adapter-contract.md), including context rejection, timeout classification, bounded retry, idempotency, provider-neutral errors, redaction, offline/degraded behavior, and queued context propagation. No production product adapter is registered.

## 11. Verify Feature Flags and Tenant Configuration

Run the focused suites:

```powershell
php artisan test --group=feature-flags
php artisan test --group=tenant-configuration
```

Expected:

- Safe platform defaults and tenant overrides evaluate deterministically in trusted context.
- Missing, invalid, disabled, expired, or cross-tenant overrides never leak and fall back safely.
- Every flag/override change is permission-checked and audited.
- Tests prove that tenant isolation, authentication, RBAC, audit integrity, secret protection, and residency controls are not flaggable.
- Branding, domain, residency, and retention values validate against versioned schemas.
- Configuration API/dashboard is read-only in Phase 0 and exposes no domain provisioning, asset upload, theme editor, or branded rendering.

## 12. Verify the Foundation Admin Dashboard

Review [dashboard-contract.md](contracts/dashboard-contract.md), then run:

```powershell
npm run lint
npm run typecheck
npm run test
npm run build
php artisan test --group=admin-dashboard
```

Expected:

- Browser login/logout works for administratively provisioned active human users; registration, reset, email-verification, MFA, teams, API-key, and service-token flows are absent.
- Platform and tenant pages enforce the same policies and application contracts as API operations.
- Cross-tenant and unauthorized data appears in zero HTML or Inertia props.
- Tenant switching clears prior tenant page state.
- English/LTR and Arabic/RTL layouts, light/dark/system themes, keyboard/focus behavior, mobile/desktop widths, and loading/empty/error/forbidden states pass.
- Navigation contains only Phase 0 capabilities.
- No licensed dashboard-template files are required; the custom design uses project-owned React/Tailwind/shadcn/ui code.

## 13. Run All Quality Gates

```powershell
composer validate --strict
vendor/bin/pint --test
npm run lint
npm run typecheck
npm run test
npm run build
php artisan migrate:fresh --seed --env=testing
php artisan test
npx redocly lint specs/001-project-foundation/contracts/openapi.yaml
php artisan zonetec:docs:check
php artisan zonetec:phase-boundary:check
```

Expected:

- All commands succeed.
- Documentation links, permission/audit catalogs, configuration reference, and OpenAPI are current.
- Phase-boundary check finds no excluded product module, route, migration, dashboard navigation item, or production adapter.
- There are no unresolved critical/high isolation, authorization, audit-integrity, secret-exposure, or deployment-parity findings.

## 14. SaaS and On-Premise Parity

Run the same automated suite with the SaaS and on-premise configuration profiles:

```powershell
$env:ZONETEC_DEPLOYMENT_MODE = 'saas'
php artisan test --group=deployment-parity

$env:ZONETEC_DEPLOYMENT_MODE = 'on_premise'
php artisan test --group=deployment-parity
```

For the on-premise run, block outbound network access while retaining local MySQL/filesystem access.

Expected: auth, tenancy, RBAC, audit, API errors, configuration, feature flags, health/telemetry, dashboard assets, queue, and fake-adapter semantics remain equivalent. Core foundation controls and the dashboard continue locally; unavailable external adapters report degraded status.

## Completion Evidence

Phase 0 is ready for Phase 1 planning only when:

- All commands above pass on MySQL 8.4.
- The OpenAPI contract and implementation agree.
- Tenant isolation and RBAC denial matrices are complete.
- Audit atomicity, integrity, export, and failure tests pass.
- SaaS/on-premise parity evidence is recorded.
- Required architecture, security, data, operations, adapter, test, and contributor documents are approved.
- No expired governance exception exists.
- No excluded product feature or production product adapter has been introduced.
