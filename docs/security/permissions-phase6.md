# Phase 6 venue marketplace permissions

Owner: Security Engineering  
Last reviewed: 2026-07-15

Executable source: `Database\Seeders\PermissionSeeder::definitions()`. CI compares
documentation with the seeder. Phase 0–5 keys remain in
`docs/standards/permission-catalog.md` and prior phase docs.

## Organization eligibility

Marketplace participation requires a tenant organization type set at creation.

| Organization type | May own venues | May request rentals | Marketplace visibility |
| --- | --- | --- | --- |
| `venue_owner` | yes | no | Owner-side only |
| `organizer` | no | yes | Organizer-side only |
| `hybrid` | yes | yes | Both sides |

Eligibility is checked by `OrganizationEligibility::check()` before every venue
creation, rental submission, and approval action. Ineligible types receive
`422 organization_type_not_eligible`.

## Tenant permissions

| Key | Module | Scope | Risk | Description | Primary enforcement |
| --- | --- | --- | --- | --- | --- |
| `venue.manage` | venue-marketplace | tenant | sensitive | Manage venue profiles, assets, availability, pricing, and publication. | `permission:venue.manage,tenant` on owner venue and asset routes |
| `marketplace.manage` | venue-marketplace | tenant | sensitive | Browse marketplace catalog, request quotes, and submit rental requests. | `permission:marketplace.manage,tenant` on catalog, quote, and rental routes |
| `rentals.approve` | venue-marketplace | tenant | sensitive | Approve, reject, and revoke marketplace rental requests. | `permission:rentals.approve,tenant` on rental decision routes |
| `reports.view` | venue-marketplace | tenant | standard | View and export marketplace settlement statements and disputes. | `permission:reports.view,tenant` on statement and dispute routes |

## Platform permissions

| Key | Module | Scope | Risk | Description | Primary enforcement |
| --- | --- | --- | --- | --- | --- |
| `platform.marketplace.view` | venue-marketplace | platform | standard | View cross-participant marketplace oversight (rentals, statements). | `permission:platform.marketplace.view,platform` on platform marketplace routes |
| `platform.marketplace.disputes.manage` | venue-marketplace | platform | privileged | Review and resolve marketplace statement disputes; revise statements. | `permission:platform.marketplace.disputes.manage,platform` on dispute management and statement revision routes |

## System role defaults

| System role | Phase 6 grants |
| --- | --- |
| Tenant Administrator | `venue.manage`, `marketplace.manage`, `rentals.approve`, `reports.view` |
| Platform Administrator | `platform.marketplace.view`, `platform.marketplace.disputes.manage` |
| Custom roles | empty until explicitly granted |

Organizer tenants typically grant `marketplace.manage` and `reports.view` to their
event operations roles. Venue-owner tenants grant `venue.manage`, `rentals.approve`,
and `reports.view` to their venue management roles. Hybrid tenants may grant all four
tenant keys to appropriate roles.

## Delegated base-permission composition

Delegation is a marketplace concept; it never grants the underlying module base
permissions. An organizer tenant whose rental is approved receives a time-bound
`ControlDelegation` record with `DelegatedAssetResource` rows, each carrying a
`granted_capabilities` list derived from the asset's `selected_capabilities`.

Delegation **does not** grant any of these base permissions:

| Base permission | Module | Remains with |
| --- | --- | --- |
| `acs.configure` | access-control | Owner tenant only |
| `kiosk.manage` | kiosk | Owner tenant only |
| `badge.print` | badge-printing | Owner tenant only |
| `badge.reprint` | badge-printing | Owner tenant only |
| `checkin.scan.submit` | scanning | Owner tenant only |

When an organizer invokes a delegated resource (e.g. a rented kiosk or scanner),
`DatabaseDelegatedControlGuard` enforces:

1. The organizer's existing base permission (`existingPermissionAllowed`)
2. The delegation's time window (`starts_at` / `ends_at`)
3. The delegation is active or degraded (not revoked, pending, or expired)
4. The delegation is scoped to the correct event
5. The specific asset resource is provisioned for this delegation
6. The requested capability is within the asset's `granted_capabilities`

Any check failure returns a `DelegatedControlDecision(allowed: false)` with a
stable problem code. The organizer's own RBAC permission must already be present;
delegation adds capability-scoped resource access on top.

## Deny-by-default behavior

All marketplace API routes require `auth:sanctum` and a `permission:*` middleware
guard. Routes without a matching permission key return `403`. Tenant context
middleware ensures the user belongs to the acting tenant.

Platform marketplace routes require platform-scoped permissions; a tenant user
cannot access platform marketplace endpoints even if they hold tenant-level
marketplace permissions.

Unrecognized asset types in provisioning produce `422 marketplace_adapter_unavailable`
rather than silently granting access.

## Route/action mapping table

### Owner venue management (`permission:venue.manage,tenant`)

| Route | Method | Controller | Action |
| --- | --- | --- | --- |
| `api/v1/tenant/venues` | GET | `OwnerVenueController@index` | List owner venues |
| `api/v1/tenant/venues` | POST | `OwnerVenueController@store` | Create venue |
| `api/v1/tenant/venues/{id}` | GET | `OwnerVenueController@show` | View venue detail |
| `api/v1/tenant/venues/{id}` | PATCH | `OwnerVenueController@update` | Update venue |
| `api/v1/tenant/venues/{id}/archive` | POST | `OwnerVenueController@archive` | Archive venue |
| `api/v1/tenant/venues/{id}/status` | POST | `OwnerVenueController@changeStatus` | Change venue status |
| `api/v1/tenant/venues/{id}/assets` | GET | `OwnerVenueAssetController@index` | List venue assets |
| `api/v1/tenant/venues/{id}/assets` | POST | `OwnerVenueAssetController@store` | Create venue asset |
| `api/v1/tenant/venues/{id}/assets/{aid}` | GET | `OwnerVenueAssetController@show` | View asset detail |
| `api/v1/tenant/venues/{id}/assets/{aid}` | PATCH | `OwnerVenueAssetController@update` | Update asset |
| `api/v1/tenant/venues/{id}/assets/{aid}/availability` | PUT | `OwnerVenueAssetController@replaceAvailability` | Replace availability windows |
| `api/v1/tenant/venues/{id}/assets/{aid}/publication` | POST | `OwnerVenueAssetController@publish` | Publish asset to catalog |
| `api/v1/tenant/venues/{id}/assets/{aid}/publication` | DELETE | `OwnerVenueAssetController@withdraw` | Withdraw publication |

### Organizer marketplace (`permission:marketplace.manage,tenant`)

| Route | Method | Controller | Action |
| --- | --- | --- | --- |
| `api/v1/tenant/marketplace/catalog` | GET | `MarketplaceCatalogController@index` | Browse published catalog |
| `api/v1/tenant/marketplace/catalog/{id}` | GET | `MarketplaceCatalogController@show` | View catalog entry |
| `api/v1/tenant/marketplace/quotes` | POST | `MarketplaceQuoteController@store` | Request a pricing quote |
| `api/v1/tenant/marketplace/rentals` | GET | `ParticipantRentalController@index` | List rentals (participant-scoped) |
| `api/v1/tenant/marketplace/rentals` | POST | `ParticipantRentalController@store` | Submit rental request |
| `api/v1/tenant/marketplace/rentals/{id}` | GET | `ParticipantRentalController@show` | View rental detail |
| `api/v1/tenant/marketplace/rentals/{id}/cancel` | POST | `ParticipantRentalController@cancel` | Cancel rental (organizer) |
| `api/v1/tenant/marketplace/rentals/{id}/delegation` | GET | `ParticipantDelegationController@show` | View delegation status |

### Rental decisions (`permission:rentals.approve,tenant`)

| Route | Method | Controller | Action |
| --- | --- | --- | --- |
| `api/v1/tenant/marketplace/rentals/{id}/approve` | POST | `ParticipantRentalController@approve` | Approve rental (owner) |
| `api/v1/tenant/marketplace/rentals/{id}/reject` | POST | `ParticipantRentalController@reject` | Reject rental (owner) |
| `api/v1/tenant/marketplace/rentals/{id}/revoke` | POST | `ParticipantRentalController@revoke` | Revoke active rental (owner) |

### Statements and disputes (`permission:reports.view,tenant`)

| Route | Method | Controller | Action |
| --- | --- | --- | --- |
| `api/v1/tenant/marketplace/statements` | GET | `ParticipantStatementController@index` | List statements |
| `api/v1/tenant/marketplace/statements/{id}` | GET | `ParticipantStatementController@show` | View statement detail |
| `api/v1/tenant/marketplace/statements/{id}/export` | GET | `ParticipantStatementController@export` | Export statement CSV |
| `api/v1/tenant/marketplace/statements/{id}/disputes` | POST | `ParticipantStatementController@openDispute` | Open dispute |
| `api/v1/tenant/marketplace/disputes/{id}` | GET | `ParticipantStatementController@showDispute` | View dispute detail |

### Platform marketplace oversight (`permission:platform.marketplace.view,platform`)

| Route | Method | Controller | Action |
| --- | --- | --- | --- |
| `api/v1/platform/marketplace/rentals` | GET | `PlatformMarketplaceController@listRentals` | List all rentals |
| `api/v1/platform/marketplace/statements` | GET | `PlatformMarketplaceController@listStatements` | List all statements |

### Platform dispute management (`permission:platform.marketplace.disputes.manage,platform`)

| Route | Method | Controller | Action |
| --- | --- | --- | --- |
| `api/v1/platform/marketplace/statements/{id}/revisions` | POST | `PlatformMarketplaceController@reviseStatement` | Revise statement |
| `api/v1/platform/marketplace/disputes` | GET | `PlatformMarketplaceController@listDisputes` | List all disputes |
| `api/v1/platform/marketplace/disputes/{id}` | GET | `PlatformMarketplaceController@showDispute` | View dispute detail |
| `api/v1/platform/marketplace/disputes/{id}/review` | POST | `PlatformMarketplaceController@startReview` | Start dispute review |
| `api/v1/platform/marketplace/disputes/{id}/notes` | POST | `PlatformMarketplaceController@addNote` | Add internal note |
| `api/v1/platform/marketplace/disputes/{id}/resolution` | POST | `PlatformMarketplaceController@resolve` | Resolve dispute |

### Web (Inertia) pages

| Route name | Permission gate | Page |
| --- | --- | --- |
| `tenant.venues.index` | `venue.manage` (view-model) | Venue list |
| `tenant.venues.show` | `venue.manage` (view-model) | Venue detail |
| `tenant.marketplace.index` | `marketplace.manage` (view-model) | Catalog browse |
| `tenant.marketplace.rentals.index` | `marketplace.manage` (view-model) | Rental list |
| `tenant.marketplace.rentals.show` | `marketplace.manage` (view-model) | Rental detail |
| `tenant.marketplace.statements.index` | `reports.view` (view-model) | Statement list |
| `tenant.marketplace.statements.show` | `reports.view` (view-model) | Statement detail |
| `platform.marketplace.index` | `platform.marketplace.view` (view-model) | Platform marketplace |
| `platform.marketplace.disputes.show` | `platform.marketplace.disputes.manage` (view-model) | Platform dispute detail |

## Test references

- `tests/Feature/VenueMarketplace/Phase6FoundationSmokeTest.php` — permission enforcement smoke tests
- `tests/Contract/VenueMarketplace/MarketplaceBoundaryContractsTest.php` — cross-tenant boundary and permission boundary contracts
- `PermissionSeeder::definitions()` — CI-validated canonical permission list
