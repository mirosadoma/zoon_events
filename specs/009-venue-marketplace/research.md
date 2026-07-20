# Research: Venue Marketplace (Phase 6)

All Technical Context unknowns are resolved. Research used the current
repository, accepted Phase 0–5 artifacts, all_plan.md, the constitution, locked
dependencies, schema conventions, API/adapter standards, and existing tenant,
audit, idempotency, queue and module-boundary code. No external provider or legal
dependency is needed for the defined statement-only Phase 6 scope.

## Decision 1 — Add one VenueMarketplace module

- **Decision**: Create app/Modules/VenueMarketplace as the owner of venue
  profiles, fixed-asset inventory, availability/pricing, allowlisted catalog
  publications, rental/reservation lifecycle, control delegations, settlement
  statements and disputes. Keep it inside the existing modular monolith.
- **Rationale**: These concepts form one cohesive B2B marketplace lifecycle and
  are named as one module in all_plan.md. A single owner makes reservation
  transactions and shared participant projections auditable without distributing
  writes across services. It matches current module/provider/routes/testing
  conventions.
- **Alternatives considered**: Put venues in Events and settlements in Payments —
  rejected because Events does not own rentable infrastructure and Payments must
  not imply fund movement. Split catalog, rental and settlement into services —
  rejected because current scale and transaction requirements do not justify
  distributed consistency or operational overhead.

## Decision 2 — Classify organizations in Tenancy, not users

- **Decision**: Add organization_type to tenants with organizer, venue_owner and
  hybrid values; backfill existing tenants as organizer. Venue-owner eligibility
  is exposed through a Tenancy contract. Human access continues through existing
  tenant memberships and roles.
- **Rationale**: A venue owner is a business organization, not a different login
  credential. The classification lets controlled onboarding and navigation
  distinguish capabilities while allowing a real business to both own a venue
  and organize events. It avoids duplicate user/auth systems.
- **Alternatives considered**: A new venue-owner user table — rejected because it
  forks authentication and RBAC. A boolean on users — rejected because ownership
  belongs to the tenant. Marketplace-specific participant rows only — rejected
  because onboarding, platform administration and navigation need an authoritative
  organization classification.

## Decision 3 — Publish an allowlisted catalog snapshot

- **Decision**: Publishing an asset creates or replaces an active
  MarketplaceCatalogPublication containing opaque public IDs, publication
  version, explicitly allowed bilingual venue/asset fields, supported
  capabilities, capacity, price/currency and bookable-window summary. Catalog
  queries read this projection only. Resource bindings, contacts not marked
  public, internal status and source row IDs are never present.
- **Rationale**: Organizer discovery intentionally spans venue-owner tenants, so
  the ordinary tenant global scope cannot serve it. A dedicated projection makes
  the security boundary structural: serializers cannot accidentally expose
  columns that do not exist in the projection, withdrawn publications disappear
  immediately, and cache invalidation can key on publication version.
- **Alternatives considered**: Query venue_assets with where published — rejected
  because a future select/serializer change could leak owner-private fields.
  Duplicate the whole venue/asset records into each organizer tenant — rejected
  because fan-out, deletion and staleness are unnecessary. Public unauthenticated
  catalog — rejected because Phase 6 is an authenticated B2B marketplace, not a
  consumer marketplace.

## Decision 4 — Canonical rental is owner-scoped with an explicit organizer participant

- **Decision**: Every marketplace row has tenant_id for the venue-owner tenant.
  Shared top-level rental, delegation, statement and dispute rows also store
  organizer_tenant_id; the rental has a composite reference to the organizer's
  event. A RentalParticipantScope returns a role-specific projection only when
  the trusted current tenant is the owner or organizer. Platform oversight uses
  a separate platform-scoped query and permission.
- **Rationale**: The venue owner makes the reservation decision and owns the
  physical asset, so it is the canonical scope. The organizer is a named
  counterparty, not an arbitrary tenant exception. Explicit participant checks
  are testable across HTTP, jobs, events, notifications and exports and avoid
  treating an absent tenant filter as platform access.
- **Alternatives considered**: Store the rental under the organizer tenant —
  rejected because owner approval and asset locking would cross into owner data.
  Duplicate owner and organizer rental records — rejected because approval,
  revocation and disputes would become eventually consistent. Disable tenant
  scopes globally — rejected as a constitutional violation.

## Decision 5 — Use immutable quote snapshots and minor-unit money

- **Decision**: Store active asset prices as integer minor units plus ISO 4217
  currency and pricing model. Quote produces a digest/version over publication
  versions, assets, requested UTC window, venue timezone and line calculations.
  Submission recomputes and rejects quote_changed if the digest no longer matches,
  then stores immutable line snapshots. Per-hour bills every started hour;
  per-day bills every started venue-local calendar day; per-rental bills once.
- **Rationale**: Integer money avoids floating-point drift. Explicit time-unit
  rules make acceptance tests deterministic, including daylight changes. A quote
  digest prevents silent price changes between review and submission without
  creating a temporary reservation.
- **Alternatives considered**: Decimal/float amounts — rejected for rounding
  ambiguity. Store only current asset price and recalculate later — rejected
  because owner/organizer statements would change retroactively. Hold inventory
  at quote time — rejected because catalog browsing should not block real owner
  decisions and the specification permits overlapping pending requests.

## Decision 6 — Serialize approval on asset rows

- **Decision**: In one AuditedTransaction, sort selected asset IDs, lock each
  venue_asset row for update, revalidate source publication, operational status,
  complete availability and any overlapping reserved/active intervals, then
  insert all reservations and approve the rental. Any conflict, validation or
  audit failure rolls back the entire request. Use the existing transaction
  retry count for deadlocks and test with two independent MySQL connections.
- **Rationale**: MySQL does not provide PostgreSQL-style exclusion constraints
  for time ranges. Locking a stable asset row creates one serialization point per
  physical asset even when no prior reservation row exists, avoiding unsafe gap
  assumptions. Deterministic order minimizes deadlocks for multi-asset approval.
- **Alternatives considered**: Check then insert without locks — rejected because
  concurrent approvals can both pass. Lock only matching reservations — rejected
  because an empty result offers no stable row. A distributed lock — rejected
  because the database transaction remains the source of truth and a second lock
  system adds failure modes.

## Decision 7 — Time is authoritative; scheduled status is a materialized view

- **Decision**: Each control decision synchronously checks rental/delegation
  state, revoked_at, starts_at <= now < ends_at, asset/capability/event scope and
  the actor's existing operation permission. Minute schedulers materialize
  active/expired/completed display state, send notifications and trigger
  provisioning/release, but a delayed or stopped worker cannot extend access.
  Owner revocation writes revoked_at first in an audited transaction, so denial
  is immediate before asynchronous cleanup.
- **Rationale**: This is the only design that proves the no-earlier/no-later
  boundary under queue delay and on disconnected on-premise installations. It
  satisfies the 60-second UI/service target without making authorization depend
  on scheduler punctuality.
- **Alternatives considered**: Grant/revoke roles at start/end — rejected because
  role propagation can lag and grants are not asset/event scoped. Cache an active
  boolean — rejected because stale cache can extend access. Rely only on scheduled
  revocation — rejected as fail-open on worker failure.

## Decision 8 — Operational modules provision and guard their own resources

- **Decision**: Add public provision/release contracts to AccessControl, Kiosk,
  BadgePrinting and Scanning. Marketplace calls them with a sanitized,
  capability-bounded grant to create/link organizer event resources. The
  resulting resource stores only opaque venue-asset/delegation public references.
  Operational management actions call Authorization's DelegatedControlGuard;
  VenueMarketplace supplies its database-backed implementation. Camera remains
  catalog-only.
- **Rationale**: Each existing module understands its schema, safety invariants,
  adapter and event-scoped resource. Marketplace can coordinate a rental without
  mutating another module's tables or inventing a generic hardware path. A guard
  in the existing action path preserves device authentication, credential
  validation, anti-passback and emergency behavior.
- **Alternatives considered**: Marketplace writes ACS/kiosk tables directly —
  rejected by the module constitution. Put provider commands in asset capability
  JSON — rejected because untrusted arbitrary commands would bypass validation.
  Create a new universal hardware adapter — rejected because it would duplicate
  accepted module adapters and safety rules.

## Decision 9 — Compose marketplace grants with existing RBAC

- **Decision**: Add tenant permissions venue.manage, marketplace.manage,
  rentals.approve and reports.view (audit.view already exists), plus explicit
  platform.marketplace.view and platform.marketplace.disputes.manage. The seeded
  Venue Owner Admin and Venue Asset Manager roles receive owner permissions;
  organizer Event Manager receives marketplace.manage; finance/reporting roles
  receive reports.view. Delegated resource actions additionally require their
  existing key such as acs.configure, kiosk.manage or badge.print.
- **Rationale**: Rental participation answers which external asset may be used;
  RBAC answers which human may perform the operation. Requiring both prevents an
  approved rental from turning every organizer member into an operator. Platform
  oversight remains read/dispute-specific and never grants resource control.
- **Alternatives considered**: One broad marketplace.admin key — rejected for
  least privilege. Generate temporary roles per rental — rejected because role
  lifecycle is coarse, expensive and difficult to revoke safely. Trust frontend
  navigation — rejected because UI checks are not authorization.

## Decision 10 — Write correlated participant audit evidence

- **Decision**: Owner-private changes create one owner-tenant audit record.
  Shared rental/decision/delegation/statement/dispute transitions create sanitized
  owner and organizer audit records with the same correlation ID in the same
  transaction. Platform oversight/dispute decisions also create platform audit
  evidence. Rejection/dispute text is not copied into audit metadata; only stable
  reason codes and changed field names are recorded.
- **Rationale**: Both contractual parties need traceability in their isolated
  audit views, while the platform needs proof of privileged action. Correlation
  preserves one logical event without exposing the other tenant's internal
  context. Synchronous writes satisfy the fail-closed audit rule.
- **Alternatives considered**: One audit row owned by the venue tenant — rejected
  because organizer auditors could not see their own actions. Share the venue's
  audit log — rejected because it exposes unrelated records. Async duplicate
  audit — rejected because state could commit without required evidence.

## Decision 11 — Statements record facts but do not move funds

- **Decision**: Generate one immutable initial statement per finalized rental
  with statement lines copied from approved rental snapshots, agreed total,
  currency, window and outcome. Cancellation/revocation does not invent a refund,
  penalty, proration, payable or payout. Corrections are linked revisions;
  disputes are append-only events. A requester-scoped CSV stream is audited and
  not stored as a shared file.
- **Rationale**: all_plan.md places full settlement automation in future work and
  explicitly leaves payout ownership open. Recording agreed facts supports
  reconciliation without making unsupported financial/legal claims. Immutable
  revisions preserve both parties' history.
- **Alternatives considered**: Reuse Payments and mark the statement paid —
  rejected because no money movement occurred. Calculate cancellation fees or
  prorated amounts — rejected because no commercial policy was specified. Allow
  editing the original statement — rejected because counterpart facts and audit
  evidence would diverge.

## Decision 12 — Use local lifecycle recovery for SaaS and on-premise

- **Decision**: The same schema, commands, queue jobs and contracts operate in
  both deployment modes. An isolated on-premise installation supports all local
  venue/organizer tenants. Remote catalog federation is not implemented. If a
  remote dependency or operational adapter is unavailable, discovery/control
  reports a stable degraded state; locally stored time/revocation checks still
  deny outside the window. Jobs are idempotent on recovery.
- **Rationale**: This preserves core behavior and safety without prematurely
  designing Phase 7 federation or offline synchronization. Local authorization
  does not need cloud connectivity, and explicit degradation is testable.
- **Alternatives considered**: SaaS-only marketplace — rejected by deployment
  parity. Cache remote grants indefinitely — rejected because it can extend
  control. Build cross-deployment replication now — rejected as Phase 7 scope and
  a substantial consistency/security expansion.

## Decision 13 — Keep localization and privacy in the domain contract

- **Decision**: Persist bilingual venue/asset text explicitly, keep lifecycle and
  commercial facts language-neutral, store UTC instants plus venue timezone
  snapshots, and format dates/numbers/currencies per viewer locale. Classify
  catalog fields as public only after explicit publication; business contacts,
  requests, statements and disputes are confidential; binding references are
  secret. Retention and residency are policy-driven, with legal/contractual holds
  on statements/audit and deletion/minimization for other data.
- **Rationale**: Translation cannot safely reconstruct owner-authored catalog
  copy, while statuses and money should remain identical across languages.
  Explicit classification controls publication, serialization, audit and
  retention. The feature handles no attendee identity/biometric/CCTV feed data.
- **Alternatives considered**: Store only one language — rejected by the
  constitution. Put all translated content in arbitrary JSON — rejected because
  validation/search become ambiguous. Treat every venue field as public —
  rejected because contacts and resource bindings may be confidential or secret.

## Decision 14 — Extend the current API and dashboard conventions

- **Decision**: Use authenticated /api/v1/tenant routes for owner and organizer
  flows and /api/v1/platform routes for oversight, Sanctum/session context,
  bounded signed cursors, Idempotency-Key on all writes, RFC Problem Details with
  stable Phase 6 reason codes, and opaque public IDs on cross-tenant resources.
  Add Inertia pages to DashboardLayout using current shared tables/status/modals,
  React Query-free repository conventions, react-i18next and axe-tested RTL.
- **Rationale**: The accepted API and dashboard foundations already provide the
  security, error, correlation, compatibility and UX patterns needed. Reuse
  reduces phase drift and lets the authoritative OpenAPI document be updated by
  the existing sync gate.
- **Alternatives considered**: GraphQL — rejected because it adds a second API
  governance and authorization model. A standalone marketplace SPA — rejected
  because it duplicates auth, branding and accessibility shell behavior. Numeric
  IDs in catalog URLs — rejected because they expose internal tenant-local
  identifiers and invite enumeration.
