# Quickstart: Identity Verification (Phase 5)

End-to-end validation scenarios that prove the feature works. Run against a local
dev instance with mock adapters. This is a run/validation guide — implementation
detail lives in `tasks.md` and the code.

## Prerequisites

- Phases 0–4 accepted and green; app installed (`composer install`, `npm install`).
- `config/identity-verification.php` present with:
  `default_government_adapter=mock`, `default_face_adapter=mock`,
  `residency=on_premise` (cross-border transfer off).
- Migrations + `PermissionSeeder` run so `identity.configure`, `identity.review`,
  `identity.data.view`, `identity.data.manage` exist.
- Test users: an Organizer (has `identity.configure`), a Reviewer (has
  `identity.review`), a Compliance admin (`identity.data.view` + `.manage`), and an
  Attendee with a valid order access token.

## Setup

```powershell
php artisan migrate --seed
php artisan db:seed --class=Database\Seeders\PermissionSeeder
npm run build   # or: npm run dev
```

## Scenario 1 — Configure a requirement (US1)

1. As Organizer, open `/tenant/events/{event}/identity`.
2. Set event level to `required_before_gate`; add a VVIP tier override
   `required_before_credential`; enable face fallback.
3. **Expect**: both rules persist and reload correctly; an unrelated tier resolves
   to the event default; a user without `identity.configure` sees no controls (403
   on the endpoint).

## Scenario 2 — Consent + government verification (US2)

1. As Attendee, open `/identity/{event_slug}/{order_token}`.
2. **Expect**: the consent notice lists what/why/retention/who/processing-mode/
   deletion in the active language before any capture.
3. Decline → **expect** status stays `pending`, no consent row, no sensitive data.
4. Consent → run the mock government check (success path) →
   **expect** status `gov_verified`, `verified_name`/`verified_nationality` set,
   `verified_at` set, consent stored, and an audit entry for the result.

## Scenario 3 — Enforcement blocks then allows (US3)

1. With `required_before_gate`, submit a gate authorization for an **unverified**
   attendee (Phase 4 path). **Expect** deny with reason `identity_not_verified`
   recorded on the access log.
2. Verify the attendee (Scenario 2), retry. **Expect** entry allowed.
3. With `required_before_credential`, attempt issuance for an unverified attendee.
   **Expect** no active credential until verified; attendee/credential detail shows
   the identity-pending banner.

## Scenario 4 — Face fallback + manual review (US4)

1. As a non-resident attendee (government unsupported/failed), submit a face
   capture. **Expect** a `pending` item in the reviewer queue; only a minimized
   template is stored (encrypted, with `retention_until`).
2. As Reviewer at `/tenant/events/{event}/identity/review`: reject one **without a
   reason** → **expect** validation error; reject **with** a reason → status
   `rejected`, reason stored, audited. Approve another → status `face_verified`,
   reviewer + timestamp recorded, audited.

## Scenario 5 — Retention, deletion, audit, residency (US5)

1. Set a short retention window; run the purge job
   (`php artisan schedule:test` or the job directly). **Expect** expired sensitive
   artifacts removed (`purged_at` set) while status/audit metadata remains.
2. As Compliance admin, open a sensitive verification detail. **Expect** the access
   is written to the audit log (actor/tenant/target/outcome).
3. Issue a permitted deletion. **Expect** sensitive data removed and audited.
4. Confirm no sensitive data is emitted cross-border and that raw biometrics / raw
   government payloads never appear in any API response.

## Automated gates

```powershell
composer test         # Feature/IdentityVerification/** incl. isolation, RBAC, audit, adapters, retention
composer quality      # OpenAPI sync/lint, docs check, phase-boundary check
npm run lint; npm run typecheck; npm run test
```

**Done when**: all five scenarios pass, both adapter contract suites pass with the
mocks, cross-tenant isolation and RBAC tests deny correctly, sensitive-data access
and every verification decision are audited, and the quality gates are green.
Production government-provider integration remains a tracked blocking assumption
(see `research.md` open questions) and is out of scope for this phase.
