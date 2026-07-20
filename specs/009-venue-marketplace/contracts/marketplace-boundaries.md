# Marketplace Module and Control Boundaries

This review contract defines the only permitted cross-module and cross-tenant
paths for Phase 6. Method names are design-level and may be adjusted during
implementation only if their inputs, outputs, authorization and failure
semantics remain equivalent and the OpenAPI contract is updated first.

## Boundary Rules

1. VenueMarketplace owns every marketplace table and never imports another
   module's Infrastructure namespace.
2. Existing modules never import VenueMarketplace Infrastructure. Operational
   modules depend on Authorization's DelegatedControlGuard or expose their own
   public provisioner contract.
3. A trusted TenantContext or PlatformContext is required at the application
   boundary. tenant_id/organizer_tenant_id from request bodies never establish
   scope.
4. Cross-tenant catalog reads return only MarketplaceCatalogPublication fields.
   Participant reads require an owner/organizer match. Platform reads use a
   separate permission and are audited.
5. Every write accepts the shared request/correlation identity, is safe to retry,
   and returns a stable provider-neutral result/reason code.
6. No contract accepts or returns device credentials, ACS secrets, scanner/kiosk
   session tokens, printer credentials, camera feeds or attendee credential
   payloads.

## Tenancy Contract

### OrganizationEligibility

Owner: Tenancy. Consumer: VenueMarketplace.

Inputs:

- trusted tenant ID
- required capability: own_venues or request_rentals

Output:

- eligible boolean
- organization_type: organizer, venue_owner or hybrid
- stable denial reason: organization_type_not_eligible, tenant_inactive or
  tenant_not_found

Rules:

- venue_owner/hybrid may own venues
- organizer/hybrid may request rentals
- suspended/deactivated tenants are denied
- the contract does not expose Tenant persistence or membership data

## Events Contract

### MarketplaceEventReader

Owner: Events. Consumer: VenueMarketplace.

Operation: readOwnedEvent(organizerTenantId, eventId)

Returns a minimized immutable result:

- event ID and opaque/public reference where available
- tenant ID (must equal organizerTenantId)
- bilingual name
- status
- timezone
- start/end instants
- creator/owner eligibility indicator needed by marketplace authorization

Failure reasons:

- marketplace_event_not_found (also used for another tenant's event)
- marketplace_event_ineligible_status
- marketplace_event_window_invalid

The result contains no attendee, ticket, order, payment, identity or credential
data. VenueMarketplace stores a snapshot at request submission and does not read
Events persistence directly.

## Catalog Publication Contract

### MarketplaceCatalogReader

Owner/implementation: VenueMarketplace. Consumers: tenant API and AdminConsole.

Inputs:

- trusted organizer tenant context
- optional venue/country/city/type/capability/capacity/price/currency filters
- required start/end window for bookable-only search, or an explicit browse mode
- signed cursor and bounded page size (default 25, maximum 100)
- locale for selecting bilingual display projection

Output per item:

- publication, venue and asset opaque public IDs
- bilingual/localized names/descriptions and published location
- country/city/timezone
- asset type, published capability codes and capacity
- pricing model, minor-unit price and currency
- requested-window availability boolean
- publication version and published_at

Forbidden output:

- owner-local IDs; tenant IDs; source row versions other than publication
  version; unpublished contacts; bindings; adapter keys/references; operational
  internal notes; reservations/rentals from other organizers; credentials

The implementation starts from active publication rows and uses only bounded,
allowlisted availability/reservation predicates. It cannot expose arbitrary
Venue/VenueAsset model resources.

## Participant Scope Contract

### RentalParticipantScope

Owner/implementation: VenueMarketplace. Consumers: tenant API/AdminConsole,
Notifications and Audit adapters.

Inputs:

- trusted actor tenant ID and user ID
- rental/statement/dispute opaque public ID
- operation: view, request_action, owner_decision, statement_view,
  statement_export, dispute_create

Decision:

- owner when actorTenantId equals tenant_id
- organizer when actorTenantId equals organizer_tenant_id
- deny marketplace_resource_not_found for every unrelated tenant; do not reveal
  that the resource exists

Projection rules:

- both participants receive immutable event/venue/asset/window/price facts,
  shared lifecycle, decision reason, delegation status, statements and
  participant-visible dispute events
- owner receives owner actions and private operational warning fields
- organizer receives organizer actions and provisioned resource public links only
  while authorized
- neither receives binding data or platform-only dispute notes
- permissions are checked after participant match; tenant membership alone is
  insufficient

## Quote Contract

### MarketplaceQuoteService

Owner: VenueMarketplace.

Inputs:

- trusted organizer tenant/user
- owned event ID
- one or more publication public IDs from one venue
- requested start/end instants

Output:

- quote_digest and quote_version
- venue/asset/publication versions
- venue timezone
- line items: asset public ID, pricing model, unit price minor, billable units,
  line total minor, currency
- total minor and currency
- expires/invalidates semantics: digest is valid only while source publication,
  price and input window remain unchanged; search availability is advisory

Pricing:

- per_hour: ceiling(duration seconds / 3600), minimum 1
- per_day: count each venue-local calendar date touched by [start,end), minimum 1
- per_rental: 1
- all lines must use one currency; integer arithmetic with overflow protection

Failure reasons:

- marketplace_event_not_found
- marketplace_asset_not_found
- marketplace_asset_unavailable
- marketplace_mixed_venue
- marketplace_mixed_currency
- marketplace_window_invalid
- marketplace_quote_changed (submission only)

## Reservation Contract

### ApproveRental

Owner: VenueMarketplace.

Preconditions:

- trusted owner tenant and rentals.approve permission
- rental participant role is owner; status requested
- every selected asset still belongs to venue/owner and is active/published
- one available window contains the complete request interval

Atomic algorithm:

1. sort source asset IDs ascending
2. lock each source Venue Asset row for update
3. re-run publication, operational and availability checks
4. reject if any reserved/active interval overlaps: existing.start < requested.end
   AND existing.end > requested.start
5. insert all reservation rows
6. create pending delegation and delegated-resource rows
7. transition rental to approved
8. write correlated owner and organizer audit evidence
9. commit; publish after-commit notifications/jobs

Any failure rolls back every row. Repeating the same idempotency key returns the
first result. A concurrent conflict returns 409 marketplace_reservation_conflict
and identifies only requested line public IDs, never another organizer.

## Delegated Authorization Contract

### DelegatedControlGuard

Owner: Authorization. Implementation for delegated resources: VenueMarketplace.
Consumers: AccessControl, Kiosk, BadgePrinting, Scanning.

Inputs:

- trusted organizer tenant/user context
- event ID
- operational resource module/type/public reference
- requested capability code
- current instant supplied by the application clock
- existing permission decision supplied/verified by the owning module

Output:

- allowed boolean
- rental/delegation public reference when allowed
- stable reason when denied
- grant start/end for audit context, never for client-side trust

Decision order (fail closed):

1. existing operation permission must allow
2. resource must carry a recognized delegation public reference
3. organizer tenant and event must match the delegation
4. rental must be approved or active
5. revoked_at must be null
6. starts_at <= now < ends_at
7. delegated resource must be provisioned (or action explicitly supports the
   documented degraded mode)
8. requested capability must be in both rental snapshot and provisioned grant

Stable denial reasons:

- marketplace_permission_denied
- marketplace_delegation_not_found
- marketplace_delegation_not_started
- marketplace_delegation_expired
- marketplace_delegation_revoked
- marketplace_delegation_degraded
- marketplace_event_scope_denied
- marketplace_asset_scope_denied
- marketplace_capability_denied

Emergency egress and existing safety/credential decisions retain their defined
precedence. Marketplace denial never suppresses an emergency action.

## Operational Provisioner Contracts

All provisioners share these semantics:

- inputs: organizer tenant, event ID, delegation public ID, venue asset public ID,
  validated asset type/capabilities, encrypted binding resolved inside the
  trusted server boundary, starts/ends, correlation and idempotency keys
- output: provisioned/degraded/not_applicable status, owning module name,
  resource type, opaque resource public reference, accepted capability subset,
  stable reason code
- release input: same scope and resource public reference; release is idempotent
- no provider credential or raw external resource ID is returned
- timeout-before-send may retry; unknown outcome must reconcile before retry;
  bounded attempts and telemetry use both participant/rental correlation without
  secrets

### DelegatedAcsAssetPort

Owner: AccessControl. Handles turnstile, security_gate, access_lane and
access_zone. Creates/links organizer event-scoped ACS resources through existing
AccessControl actions. It cannot change emergency or credential policy.

### DelegatedKioskAssetPort

Owner: Kiosk. Handles kiosk assets. Creates/links event-scoped kiosk allocation;
device pairing/session credentials remain Kiosk-owned and are never returned to
Marketplace.

### DelegatedPrinterAssetPort

Owner: BadgePrinting. Handles printer assets. Links an allowed printer binding
to the event through existing PrinterAdapter configuration; attendee print
permission and payload rules remain BadgePrinting-owned.

### DelegatedScannerAssetPort

Owner: Scanning. Handles persisted physical scanner allocation metadata where
supported. Credential/scan decision logic remains unchanged.

### Camera behavior

Camera returns not_applicable with catalog_only. No provision, feed, recording,
identity, face or biometric contract exists in Phase 6.

## Lifecycle Commands

### ActivateMarketplaceRentals

- runs at least every minute without overlapping
- selects approved rentals where starts_at <= now and revoked_at is null
- restores owner context, provisions each line idempotently, materializes active
  or degraded, activates reservations, writes participant audit, notifies after
  commit
- authorization remains time-aware even if this command is late

### ExpireMarketplaceRentals

- runs at least every minute without overlapping
- selects approved/active/degraded grants where ends_at <= now or revoked_at set
- denial is already synchronous; command releases resources idempotently,
  expires/completes/revokes materialized state and reservations, audits/notifies
- cannot alter ends_at or recreate a grant

### FinalizeMarketplaceStatements

- consumes finalized completed/cancelled/revoked rental events or runs a bounded
  recovery scan
- unique rental + revision 1 makes creation idempotent
- copies line snapshots; writes no payment/payout status
- must create the initial statement within five minutes under normal worker load

## Audit and Notification Contract

Required marketplace audit actions:

- venue.created|updated|activated|suspended|archived
- venue_asset.created|updated|retired
- marketplace_publication.published|withdrawn
- asset_availability.created|updated|retired
- asset_price.updated
- rental.requested|approved|rejected|cancelled|revoked|activated|completed
- rental.reservation_conflict
- delegation.provisioned|degraded|control_allowed|control_denied|revoked|expired
- settlement_statement.issued|viewed|exported|revised
- marketplace_dispute.opened|review_started|note_added|resolved|rejected
- platform_marketplace.viewed

Audit records include stable IDs, actor/scope, outcome, reason code and
correlation. They exclude contacts, decision/dispute free text, binding values,
adapter credentials, statement descriptions and all credential payloads.

Notifications are after-commit and retryable. Recipient rendering uses the
recipient tenant's branding/locale. Payloads carry public IDs and stable state
only; each recipient fetches its authorized projection.

## Degraded and Recovery Behavior

- Catalog unavailable: return 503 marketplace_catalog_unavailable with retry
  guidance; no private-source fallback.
- Provisioner unavailable: delegation is degraded; no successful control claim;
  bounded retry never changes starts/ends.
- Queue stopped: synchronous time/revocation guard still denies outside window;
  recovery commands reconcile materialized state and statements idempotently.
- Notification unavailable: committed state remains authoritative; delivery
  retries without duplicating the transition.
- Audit unavailable: required state-changing or privileged read action fails and
  is not reported complete.
- On-premise remote disconnect: local catalog/participants/grants work; remote
  federation operations are unavailable because federation is outside Phase 6.
