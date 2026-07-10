# Data Model: Identity Verification (Phase 5)

New tables are owned by the `IdentityVerification` module. All carry `tenant_id`
and, where event-owned, `event_id`; sensitive data is minimized, encrypted, and
retention-bound. Field lists follow `all_plan.md` §12.20 and the spec.

## Entities

### 1. `identity_verification_requirements`

Per-event (and optional per-tier) policy that determines whether/when an attendee
must verify.

| Field | Type | Notes |
|---|---|---|
| id | ULID | PK |
| tenant_id | ULID | tenant scope (composite FK) |
| event_id | ULID | owning event |
| ticket_type_id | ULID? | null = event default; set = tier override |
| level | enum | `not_required` / `optional` / `required_before_credential` / `required_before_gate` / `required_vip` / `required_vvip` |
| face_fallback_enabled | bool | offer face capture when gov unavailable/unsupported |
| created_at / updated_at | ts | |

- Unique: (`tenant_id`,`event_id`,`ticket_type_id`) — one rule per scope.

### 2. `identity_verifications`

Per-attendee verification record and status machine.

| Field | Type | Notes |
|---|---|---|
| id | ULID | PK |
| tenant_id | ULID | tenant scope |
| event_id | ULID | |
| attendee_id | ULID | subject |
| method | enum | `email_otp` / `phone_otp` / `gov_identity` / `face_capture` / `manual_review` |
| status | enum | `not_required` / `pending` / `gov_verified` / `face_verified` / `manually_approved` / `rejected` / `expired` |
| consent_id | ULID? | FK → `identity_consents` (required before any capture) |
| provider | string? | adapter/provider label (never a hardcoded brand in logic) |
| provider_reference | string? | opaque provider reference |
| verified_name | string? | minimized verified attribute |
| verified_nationality | string? | minimized verified attribute |
| verified_at | ts? | set on success |
| manual_review_by | ULID? | reviewer user id |
| manual_review_at | ts? | |
| rejection_reason | string? | required when `rejected` |
| retention_until | ts? | drives purge of linked artifacts |
| created_at / updated_at | ts | |

- Unique: (`tenant_id`,`event_id`,`attendee_id`) — one active record per attendee
  (reissue history via status transitions / audit, not duplicate rows).
- Index: (`tenant_id`,`event_id`,`status`) for the review queue.

### 3. `identity_consents`

Stored consent captured before any identity/biometric collection.

| Field | Type | Notes |
|---|---|---|
| id | ULID | PK |
| tenant_id | ULID | |
| event_id | ULID | |
| attendee_id | ULID | |
| notice_version | string | version of the shown notice |
| disclosures | json | what/why/retention/who/processing-mode/deletion (rendered bilingual) |
| residency_mode | enum | `on_premise` / `saas` |
| consented_at | ts | |
| withdrawn_at | ts? | set on withdrawal |

### 4. `identity_biometric_artifacts`

Encrypted, minimized, retention-bound capture references (template preferred over
raw image). Never returned raw through any API.

| Field | Type | Notes |
|---|---|---|
| id | ULID | PK |
| tenant_id | ULID | |
| verification_id | ULID | FK → `identity_verifications` |
| artifact_type | enum | `template` (preferred) / `image` |
| storage_reference | string | encrypted local/object reference |
| liveness_result | enum? | `passed` / `failed` / `unavailable` |
| retention_until | ts | purge boundary |
| created_at | ts | |
| purged_at | ts? | set by retention job |

## Relationships

- `Event` 1—* `identity_verification_requirements`; `TicketType` 0..1 override.
- `Attendee` 1—1 `identity_verifications` (active); 1—* `identity_consents` (history).
- `identity_verifications` 1—* `identity_biometric_artifacts`.
- `identity_verifications.status` is read (not written) by the published
  `IdentityGate` query consumed by `Credentials` issuance and `AccessControl` gate
  authorization.

## State transitions (`identity_verifications.status`)

```text
not_required            (event/tier does not require)
pending  → gov_verified            (government adapter success)
pending  → face_verified           (face capture approved by reviewer)
pending  → manually_approved       (pure manual review approved)
pending  → rejected                (reviewer rejects, reason required)
{gov_verified|face_verified|manually_approved|pending} → expired  (validity lapses)
rejected → pending                 (re-attempt allowed)
expired  → pending                 (re-verification)
```

- `rejected` and `expired` are treated as **not verified** at every enforcement
  point (FR-016).

## Invariants

1. **Tenant isolation**: every row carries `tenant_id`; every query, adapter call,
   cache key, and file path is tenant-scoped; composite FKs bind `event_id`/
   `attendee_id`/`ticket_type_id` to the same tenant. Negative isolation tests
   prove cross-tenant denial.
2. **Consent precondition**: no `identity_biometric_artifacts` row and no
   `provider_reference`/`verified_*` value may exist without a non-withdrawn
   `consent_id`.
3. **Minimization & secrecy**: only minimized verified attributes are stored; raw
   biometric data and raw government payloads are never persisted in returnable
   fields nor exposed by any API/resource.
4. **Retention**: every sensitive artifact has `retention_until`; the purge job
   sets `purged_at` and removes the sensitive reference after that time while
   preserving non-sensitive status/audit metadata.
5. **Residency**: sensitive processing honors `residency_mode`; cross-border
   transfer is disabled unless explicitly configured.
6. **Enforcement is single-path**: issuance/gate consult `IdentityGate` only; no
   module reads identity persistence directly and no second credential trust path
   is introduced.
7. **Audit atomicity**: each state change and each sensitive-data access writes its
   audit record in the same transaction; audit failure fails the action.

## Reason codes (language-neutral)

- `identity_not_verified` — enforcement block (issuance/gate).
- `identity_expired` — verification lapsed.
- `identity_rejected` — reviewer rejection.
- `identity_consent_missing` — capture attempted without consent.
- `identity_provider_unavailable` — adapter unreachable (offer fallback/retry).
