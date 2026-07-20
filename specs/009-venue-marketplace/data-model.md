# Data Model: Venue Marketplace (Phase 6)

## Conventions

- Internal primary keys are unsigned big integers. Any identifier exposed across
  tenant boundaries is an opaque ULID public_id; catalog and participant APIs do
  not expose owner-local numeric IDs.
- Every marketplace-owned table has non-null tenant_id, the venue-owner tenant.
  Shared rental/delegation/statement/dispute tables also have non-null
  organizer_tenant_id.
- Tenant-scoped parent tables expose unique (tenant_id, id) keys. Shared parent
  tables expose unique (tenant_id, organizer_tenant_id, id) keys so children can
  carry both participant scopes in composite foreign keys.
- Monetary values are non-negative integer minor units with uppercase ISO 4217
  currency. One rental and statement use one currency.
- Persist instants as UTC timestamps with microseconds. Store the IANA venue
  timezone snapshot used for display and per-day pricing.
- Bilingual owner-authored text uses explicit name_en/name_ar and
  description_en/description_ar fields. Status, reason and capability codes are
  language-neutral enums.
- JSON is allowed only for validated, bounded capability or immutable snapshot
  data. Searchable catalog facets are normal columns/child rows.
- Mutable rows use timestamps and, where concurrency matters, an integer version.
  Statements, rental line snapshots, dispute events and audit records are
  append-only after issue/creation.

## Existing Entity Extension

### Tenant (Tenancy-owned)

Add one field:

| Field | Type | Rules |
|---|---|---|
| organization_type | string(24) | organizer, venue_owner, hybrid; non-null default organizer; existing tenants backfilled organizer |

Tenancy exposes OrganizationEligibility rather than allowing Marketplace to
read Tenant persistence. Only venue_owner/hybrid tenants may manage venues;
organizer/hybrid tenants may request rentals.

## VenueMarketplace-Owned Entities

### 1. Venue

Owner-private source record for a physical venue.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id | bigint | venue-owner tenant; non-null |
| public_id | ULID | globally unique opaque identifier |
| name_en / name_ar | string(160) | both required |
| description_en / description_ar | text | nullable, bounded |
| address_en / address_ar | string(500) | both required before activation |
| country_code | char(2) | uppercase ISO 3166-1; validated via geography contract |
| city_code | string(80) | validated platform city code |
| timezone | string(64) | valid IANA timezone |
| business_contact_name | string(160) | nullable confidential contact |
| business_contact_email | string(254) | nullable valid email |
| business_contact_phone | string(32) | nullable normalized regional phone |
| publish_contact | boolean | default false; contact omitted from publication unless true |
| status | string(24) | draft, active, suspended, archived |
| version | unsigned int | incremented on marketplace-relevant mutation |
| activated_at / suspended_at / archived_at | timestamp | nullable; consistent with status |
| created_by_user_id / updated_by_user_id | bigint | actor references |
| created_at / updated_at | timestamp | microseconds |

Keys and indexes:

- unique public_id; unique (tenant_id, id); index (tenant_id, status, id)
- index (tenant_id, country_code, city_code, status, id)
- archive is soft lifecycle, never physical deletion while referenced

### 2. Venue Asset

Owner-private fixed infrastructure inventory.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id | bigint | owner scope |
| venue_id | bigint | composite FK to Venue (tenant_id, id) |
| public_id | ULID | globally unique opaque identifier |
| asset_type | string(32) | turnstile, security_gate, camera, kiosk, printer, scanner, access_lane, access_zone |
| name_en / name_ar | string(160) | required |
| description_en / description_ar | text | nullable, bounded |
| location_en / location_ar | string(240) | required before publication |
| capabilities | JSON array | bounded allowlisted codes for asset_type; no commands or secrets |
| capacity_per_minute | unsigned int | nullable; positive when present and required for throughput-filtered types |
| operational_status | string(24) | draft, active, maintenance, offline, retired |
| pricing_model | string(24) | per_hour, per_day, per_rental |
| price_minor | unsigned bigint | non-negative; required before publication |
| currency | char(3) | uppercase enabled ISO currency; required before publication |
| version | unsigned int | incremented on catalog-relevant mutation |
| retired_at | timestamp | nullable; required for retired |
| created_by_user_id / updated_by_user_id | bigint | actor references |
| created_at / updated_at | timestamp | microseconds |

Keys and indexes:

- unique public_id; unique (tenant_id, id)
- unique (tenant_id, venue_id, id)
- index (tenant_id, venue_id, operational_status, asset_type, id)
- an asset with future reserved/active use cannot be retired or have an
  incompatible price/availability/publication change until resolved

### 3. Venue Asset Binding

Owner-secret association between a marketplace asset and its physical/module
resource. Never part of catalog or participant responses.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id | bigint | owner scope |
| venue_asset_id | bigint | composite FK to Venue Asset |
| control_family | string(24) | acs, kiosk, printer, scanner, catalog_only |
| adapter_key | string(80) | validated registered adapter; nullable for catalog_only |
| secret_reference | string(255) | nullable reference to existing secret store; encrypted at rest if local |
| external_reference_ciphertext | text | nullable encrypted external resource reference |
| binding_metadata | encrypted JSON | bounded provider-neutral metadata; no credentials |
| status | string(20) | active, disabled, invalid |
| verified_at / disabled_at | timestamp | nullable |
| created_at / updated_at | timestamp | microseconds |

Rules:

- unique (tenant_id, venue_asset_id) for Phase 6
- camera MUST use catalog_only; Phase 6 has no feed/recording binding
- asset type maps to a permitted control family; invalid mappings cannot publish
- secrets and decrypted references are redacted from errors, logs, audit and APIs

### 4. Asset Availability Window

Owner-declared interval during which an asset may be requested.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id | bigint | owner scope |
| venue_asset_id | bigint | composite FK to Venue Asset |
| public_id | ULID | opaque owner-facing identifier; unique |
| available_from / available_until | timestamp | until > from |
| status | string(20) | available, blocked, retired |
| reason_code | string(80) | nullable stable code; no confidential free text in catalog |
| created_by_user_id / updated_by_user_id | bigint | actor references |
| created_at / updated_at | timestamp | microseconds |

Indexes and rules:

- index (tenant_id, venue_asset_id, status, available_from, available_until)
- available windows for one asset may touch but MUST NOT overlap; mutations lock
  the Venue Asset row before validation
- blocked/retired windows are never returned as bookable
- a request window must be fully contained in at least one available interval

### 5. Marketplace Catalog Publication

Allowlisted, cross-tenant-readable projection generated from an active Venue and
Venue Asset. It contains no binding or unapproved contact field.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id | bigint | source owner scope |
| public_id | ULID | publication ID; unique |
| venue_id / venue_asset_id | bigint | composite owner references |
| venue_public_id / asset_public_id | ULID | stable opaque source references |
| publication_version | unsigned int | monotonically increasing per asset |
| venue_version / asset_version | unsigned int | source versions captured |
| venue_name_en / venue_name_ar | string(160) | allowlisted snapshot |
| venue_description_en / venue_description_ar | text | allowlisted snapshot |
| asset_name_en / asset_name_ar | string(160) | allowlisted snapshot |
| asset_description_en / asset_description_ar | text | allowlisted snapshot |
| address_en / address_ar | string(500) | published business address |
| country_code / city_code | strings | searchable location facets |
| timezone | string(64) | venue timezone snapshot |
| asset_type | string(32) | searchable type |
| location_en / location_ar | string(240) | published asset location |
| capacity_per_minute | unsigned int | nullable searchable capacity |
| pricing_model / price_minor / currency | fields | published current price |
| public_contact | JSON object | nullable strict name/email/phone allowlist only when publish_contact=true |
| status | string(20) | active, withdrawn |
| published_at / withdrawn_at | timestamp | lifecycle evidence |
| created_at / updated_at | timestamp | microseconds |

Keys and indexes:

- unique (tenant_id, venue_asset_id, publication_version)
- at most one active publication per (tenant_id, venue_asset_id)
- index (status, country_code, city_code, asset_type, currency, price_minor, id)
- index (status, capacity_per_minute, id)
- CatalogReader serializes only this table plus Publication Capability rows and
  allowlisted availability/reservation existence checks

### 6. Marketplace Publication Capability

Normalized searchable capability facet generated with a publication.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id | bigint | source owner scope |
| catalog_publication_id | bigint | composite FK to Catalog Publication |
| capability_code | string(80) | validated stable code |

- unique (tenant_id, catalog_publication_id, capability_code)
- index (capability_code, catalog_publication_id)

### 7. Rental Request

Canonical shared aggregate owned by the venue tenant and naming one organizer
tenant/event counterparty.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id | bigint | venue-owner tenant |
| organizer_tenant_id | bigint | organizer counterparty; not equal to tenant_id unless hybrid self-rental is explicitly permitted by policy (default denied) |
| public_id | ULID | cross-participant identifier |
| event_id | bigint | organizer event; composite FK (organizer_tenant_id, event_id) to Events |
| venue_id | bigint | composite FK to owner Venue |
| status | string(24) | requested, approved, rejected, active, completed, cancelled, revoked |
| dispute_status | string(20) | none, open, under_review, resolved |
| requested_start_at / requested_end_at | timestamp | end > start |
| venue_timezone | string(64) | immutable snapshot |
| quote_digest | char(64) | server-calculated digest |
| quote_version | unsigned int | request contract version |
| event_snapshot | JSON | minimized immutable name/schedule/timezone/public reference |
| currency | char(3) | same across lines |
| total_minor | unsigned bigint | exact sum of line_total_minor |
| submitted_by_user_id | bigint | organizer actor |
| owner_decided_by_user_id | bigint | nullable owner actor |
| decision_reason | text | confidential; required on reject/revoke |
| submitted_at / approved_at / rejected_at | timestamp | nullable lifecycle evidence |
| activated_at / completed_at / cancelled_at / revoked_at | timestamp | nullable lifecycle evidence |
| created_at / updated_at | timestamp | microseconds |

Keys and indexes:

- unique public_id; unique (tenant_id, organizer_tenant_id, id)
- unique (organizer_tenant_id, event_id, quote_digest, submitted_by_user_id,
  submitted_at) is NOT used for idempotency; the shared hashed idempotency record
  is authoritative
- index owner view: (tenant_id, status, requested_start_at, id)
- index organizer view: (organizer_tenant_id, status, requested_start_at, id)
- index platform view: (dispute_status, status, created_at, id)
- state transitions are action-controlled; no direct generic update

### 8. Rental Asset

Immutable submitted/approved line snapshot.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id / organizer_tenant_id | bigint | participant scopes |
| rental_request_id | bigint | composite FK to Rental Request |
| venue_asset_id | bigint | composite FK to owner Venue Asset |
| asset_public_id | ULID | snapshot public reference |
| catalog_publication_id / publication_version | bigint/int | submitted publication snapshot |
| asset_type | string(32) | immutable snapshot |
| name_en / name_ar | string(160) | immutable snapshot |
| capabilities | JSON array | bounded immutable grant ceiling |
| pricing_model | string(24) | per_hour, per_day, per_rental |
| unit_price_minor | unsigned bigint | immutable |
| billable_units | unsigned int | >= 1 |
| line_total_minor | unsigned bigint | unit price × units with overflow checks |
| currency | char(3) | equals rental currency |
| created_at | timestamp | immutable creation time |

- unique (tenant_id, organizer_tenant_id, rental_request_id, venue_asset_id)
- sum line_total_minor MUST equal Rental Request total_minor
- lines are never edited; a changed request is rejected/cancelled and resubmitted

### 9. Asset Reservation

Conflict-control interval created only by successful approval.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id / organizer_tenant_id | bigint | participant scopes |
| rental_request_id / rental_asset_id | bigint | composite rental references |
| venue_asset_id | bigint | owner asset |
| reserved_from / reserved_until | timestamp | until > from; equals approved window |
| status | string(20) | reserved, active, completed, released |
| release_reason_code | string(80) | nullable stable code |
| activated_at / completed_at / released_at | timestamp | nullable |
| created_at / updated_at | timestamp | microseconds |

Indexes and invariant:

- unique (tenant_id, rental_asset_id)
- index (tenant_id, venue_asset_id, status, reserved_from, reserved_until)
- no two rows for one asset with status reserved/active may overlap. MySQL cannot
  express this as a CHECK; ApproveRentalAction enforces it while holding the
  Venue Asset row lock. Tests prove concurrent safety.

### 10. Control Delegation

One time-bound authorization aggregate per approved rental.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id / organizer_tenant_id | bigint | participant scopes |
| public_id | ULID | opaque participant identifier |
| rental_request_id | bigint | one-to-one composite FK |
| event_id | bigint | organizer event snapshot/reference |
| status | string(20) | pending, active, degraded, revoked, expired, completed |
| starts_at / ends_at | timestamp | copied approved window; ends > starts |
| revoked_at / expired_at / completed_at | timestamp | nullable |
| revoked_by_user_id | bigint | nullable owner actor |
| revocation_reason | text | nullable confidential; required when revoked |
| degraded_reason_code | string(80) | nullable stable non-sensitive code |
| provision_attempts | unsigned int | default 0 |
| last_provision_attempt_at | timestamp | nullable |
| created_at / updated_at | timestamp | microseconds |

- unique (tenant_id, organizer_tenant_id, rental_request_id)
- time and revoked_at are authoritative even if status materialization is stale
- status is never sufficient on its own to authorize control

### 11. Delegated Asset Resource

Per-rental-line provisioning/link result owned by Marketplace; operational
modules retain ownership of the actual event-scoped resources.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id / organizer_tenant_id | bigint | participant scopes |
| control_delegation_id | bigint | composite FK |
| rental_asset_id / venue_asset_id | bigint | owner/rental references |
| resource_module | string(32) | access_control, kiosk, badge_printing, scanning, catalog_only |
| resource_type | string(48) | provider-neutral type code |
| resource_public_reference | ULID/string | nullable opaque reference returned by owning module; no cross-module FK |
| granted_capabilities | JSON array | subset of rental asset capability snapshot |
| provisioning_status | string(20) | pending, provisioned, degraded, released, not_applicable |
| failure_reason_code | string(80) | nullable stable code |
| provisioned_at / released_at | timestamp | nullable |
| created_at / updated_at | timestamp | microseconds |

- unique (tenant_id, organizer_tenant_id, control_delegation_id, rental_asset_id)
- raw external IDs, adapter credentials and secrets are forbidden
- operational resources store this delegation public ID as opaque metadata and
  consult DelegatedControlGuard on every delegated management action

### 12. Settlement Statement

Immutable commercial facts for one finalized rental. It does not represent
payment, payout, tax invoice, refund, penalty or proration.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id / organizer_tenant_id | bigint | participant scopes |
| public_id | ULID | opaque participant identifier |
| rental_request_id | bigint | composite rental FK |
| statement_number | string(64) | unique human reference |
| revision | unsigned int | starts at 1 |
| supersedes_statement_id | bigint | nullable self-reference within same participants |
| status | string(20) | issued, superseded |
| dispute_status | string(20) | none, open, under_review, resolved |
| rental_outcome | string(20) | completed, cancelled, revoked |
| venue_timezone | string(64) | immutable snapshot |
| agreed_start_at / agreed_end_at | timestamp | immutable |
| currency | char(3) | immutable |
| agreed_total_minor | unsigned bigint | equals line sum; does not imply payable/paid |
| issued_at | timestamp | required |
| generated_by | string(20) | system or actor |
| created_at | timestamp | immutable |

- unique public_id; unique (tenant_id, organizer_tenant_id, rental_request_id,
  revision); at most one revision 1
- issued fields cannot be updated; correction inserts a higher linked revision
- status of prior revision may change only from issued to superseded in the same
  audited revision transaction

### 13. Settlement Statement Line

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id / organizer_tenant_id | bigint | participant scopes |
| settlement_statement_id | bigint | composite statement FK |
| rental_asset_id | bigint | snapshot source reference |
| publication_public_id / publication_version | ULID/int | submitted catalog snapshot reference |
| asset_public_id / asset_type | fields | immutable facts |
| name_en / name_ar | string(160) | immutable |
| pricing_model | string(24) | immutable |
| unit_price_minor / billable_units / line_total_minor | integers | immutable |
| currency | char(3) | equals statement |
| created_at | timestamp | immutable |

- unique (tenant_id, organizer_tenant_id, settlement_statement_id,
  rental_asset_id)
- line sum equals statement agreed_total_minor

### 14. Marketplace Dispute

Participant-reported challenge to a rental/statement. The original statement is
never rewritten.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id / organizer_tenant_id | bigint | participant scopes |
| public_id | ULID | opaque participant/platform identifier |
| rental_request_id / settlement_statement_id | bigint | composite references |
| reported_by_tenant_id / reported_by_user_id | bigint | reporter must be a participant |
| status | string(24) | open, under_review, resolved, rejected |
| reason_code | string(80) | stable category |
| reason | text | confidential, bounded, required |
| assigned_platform_user_id | bigint | nullable; platform scope |
| resolution_code | string(80) | nullable; required on resolution/rejection |
| resolution_summary | text | nullable confidential participant-visible summary |
| opened_at / review_started_at / resolved_at | timestamp | lifecycle |
| created_at / updated_at | timestamp | microseconds |

- unique public_id; one open/under_review dispute per statement
- index (status, opened_at, id) for platform queue
- a dispute changes dispute_status projections but not rental operational state or
  statement commercial fields

### 15. Marketplace Dispute Event

Append-only review timeline, including platform-only internal notes.

| Field | Type | Rules |
|---|---|---|
| id | bigint | primary key |
| tenant_id / organizer_tenant_id | bigint | participant scopes |
| marketplace_dispute_id | bigint | composite FK |
| event_type | string(32) | opened, review_started, note_added, resolved, rejected |
| actor_scope | string(20) | owner, organizer, platform, system |
| actor_user_id | bigint | nullable only for system |
| visibility | string(20) | participants, platform_only |
| reason_code | string(80) | nullable stable code |
| note | text | nullable confidential; bounded |
| created_at | timestamp | immutable |

- index (tenant_id, organizer_tenant_id, marketplace_dispute_id, created_at, id)
- participant projections exclude platform_only events
- audit metadata records event code and IDs, never note text

## Existing Audit Records

No separate marketplace audit table is added. Phase 6 domain events are consumed
by the existing Audit module:

- owner-only venue/asset/publication changes write one owner tenant audit row
- shared rental/reservation/delegation/statement/dispute changes write correlated
  owner and organizer audit rows in the state transaction
- platform oversight and dispute actions write platform audit evidence and the
  appropriate sanitized participant evidence
- audit payloads exclude business contact values, request/decision/dispute free
  text, statement line descriptions, secret references and resource bindings

## State Transitions

### Venue

~~~text
draft -> active -> suspended -> active
  |         |           |
  └-------> archived <--┘
~~~

- active requires complete bilingual/location/timezone data
- suspended is removed from new publication/request activity
- archived is terminal; referenced history remains readable to participants

### Venue Asset and Publication

~~~text
asset: draft -> active <-> maintenance/offline -> retired
publication: absent -> active -> withdrawn -> active(new version)
~~~

- publish requires active venue/asset, valid binding (except catalog-only camera),
  valid availability, price/currency and capability schema
- maintenance/offline blocks new request/approval and shows operational warning
- publication withdrawal is blocked when it would invalidate a future approved
  rental; a versioned republish replaces the active projection

### Rental Request

~~~text
requested -> approved -> active -> completed
    |           |          |
    |           |          └-> revoked
    |           ├-> cancelled
    |           └-> revoked
    ├-> rejected
    └-> cancelled
~~~

- only the owner can approve/reject/revoke; rejection/revocation requires reason
- organizer may cancel requested or approved before activation
- approval is all-or-nothing and creates reservations + delegation
- active is materialized at start after provisioning attempt; authorization still
  checks time directly
- dispute_status is orthogonal and never reopens operational control

### Asset Reservation

~~~text
reserved -> active -> completed
   |          |
   └--------> released
~~~

- cancel/reject-before-approval creates no reservation
- cancellation/revocation releases; completion preserves completed history
- released/completed intervals do not block future approval

### Control Delegation

~~~text
pending -> active <-> degraded -> completed
   |         |          |
   |         ├---------> revoked
   |         └---------> expired
   ├-------------------> revoked
   └-------------------> expired
~~~

- denied whenever now is outside [starts_at, ends_at), revoked_at is set, rental
  is not approved/active, resource/event/capability does not match, or actor lacks
  the existing operation permission
- degraded never expands capability; retry is idempotent and bounded

### Statement and Dispute

~~~text
statement revision: issued -> superseded (only when a new revision is issued)
dispute: open -> under_review -> resolved
                       └-----> rejected
~~~

- statement data is immutable; dispute_status mirrors the current dispute only
- resolution records an outcome but does not mark payment/payout or edit facts

## Cross-Entity Invariants

1. **Owner scope**: tenant_id on Venue through Statement/Dispute always equals the
   venue owner. It is established from trusted source relationships, never request
   tenant input.
2. **Participant scope**: organizer_tenant_id and event_id are validated through
   MarketplaceEventReader and protected by composite integrity. Participant reads
   require current tenant equals owner or organizer.
3. **Catalog isolation**: CatalogReader can select only active Publication and
   Publication Capability fields plus boolean availability/conflict checks. It
   cannot serialize source Venue Asset Binding or owner-private fields.
4. **Single venue/currency**: every Rental Asset belongs to Rental Request venue;
   each line currency equals rental/statement currency.
5. **Quote integrity**: rental line/total snapshots reproduce the accepted quote
   digest. Later source changes never mutate them.
6. **No overlapping approval**: for one venue asset, reserved/active intervals
   cannot overlap. Approval locks source asset rows in deterministic order.
7. **Delegation intersection**: allowed operation equals rental asset capability
   snapshot ∩ provisioned resource capability ∩ current actor permission, and is
   also limited by tenant/event/asset/time/state.
8. **Synchronous expiry**: status/job delay cannot make a grant valid before
   starts_at, at/after ends_at, or after revoked_at.
9. **Audit atomicity**: a required owner/participant audit failure rolls back its
   state mutation. After-commit notification failure does not roll back state and
   is retryable without duplicate business effects.
10. **Statement uniqueness**: one revision 1 per rental; retries return the same
    statement. Revision N+1 links N and never deletes it.
11. **Secret containment**: bindings and credentials never appear in publication,
    participant/platform response, statement, notification, audit, log or metric.
12. **Retention**: source records referenced by retained rentals/statements/audit
    are archived/minimized, not physically deleted; public projection withdrawal
    remains immediate.

## Query and Cache Boundaries

- Owner queries always include active tenant_id and bounded cursor filters.
- Catalog queries start from active publications, apply country/city/type/
  capability/capacity/price filters, prove one containing availability interval
  and no overlapping blocking reservation, and return opaque IDs only.
- Participant queries require (tenant_id = actor tenant OR organizer_tenant_id =
  actor tenant) plus role-specific fields. No reusable withoutGlobalScope query is
  exposed outside VenueMarketplace.
- Platform queries require platform scope and platform.marketplace.view or
  platform.marketplace.disputes.manage; each privileged read is audited.
- Catalog cache key includes publication version/filter digest, actor tenant,
  locale and page cursor. Owner/participant records are never stored in a global
  cache entry.
- Lifecycle jobs carry owner tenant, organizer tenant, rental public ID and
  correlation ID; owner tenant context is restored before mutation.

## Retention, Deletion and Residency

- Active/withdrawn publications: remove from catalog immediately on withdrawal;
  retain private snapshot only while needed for retained rentals/audit.
- Venue/asset/contact: tenant policy; archive/minimize when deletion is permitted;
  business contacts are cleared when no active/held relationship needs them.
- Requests/rejections/disputes: contractual/support retention policy; free-text
  reasons are confidential and may be minimized after the policy window while
  stable status/reason codes remain.
- Statements/lines: approved contractual/accounting/legal hold. Corrections are
  revisions, not destructive updates.
- Audit: existing append-only retention/integrity policy.
- Bindings: encrypted/secret-store referenced; remove on retirement after active
  delegations are released, leaving non-secret lifecycle evidence.
- SaaS and on-premise use the same rules and local jobs. No cross-border transfer
  is introduced by catalog sharing inside an installation; any future federation
  requires a separate Phase 7 decision.
