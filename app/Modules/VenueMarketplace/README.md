# VenueMarketplace (Phase 6)

VenueMarketplace owns marketplace catalog publication, rental lifecycle, delegated
operational grants, settlement statements, and participant disputes. It never
imports another module's `Infrastructure` namespace directly.

## Namespaces

| Layer | Namespace |
| --- | --- |
| Domain | `App\Modules\VenueMarketplace\Domain` |
| Application | `App\Modules\VenueMarketplace\Application` |
| Infrastructure | `App\Modules\VenueMarketplace\Infrastructure` |
| Http | `App\Modules\VenueMarketplace\Http` |
| Routes | `app/Modules/VenueMarketplace/Routes` |
| Providers | `App\Modules\VenueMarketplace\Providers` |
| Testing | `App\Modules\VenueMarketplace\Testing` |

## Boundary rule

VenueMarketplace depends on **application contracts (ports)** declared by owning
modules. Operational persistence models, adapters, device credentials, and
infrastructure classes from AccessControl, Kiosk, BadgePrinting, Scanning, Events,
Tenancy, or Authorization must never be imported directly.

## Allowed ports

Ports are defined in `specs/009-venue-marketplace/contracts/marketplace-boundaries.md`.

| Port | Owner | Consumer |
| --- | --- | --- |
| `OrganizationEligibility` | Tenancy | VenueMarketplace |
| `MarketplaceEventReader` | Events | VenueMarketplace |
| `MarketplaceCatalogReader` | VenueMarketplace | tenant API, AdminConsole |
| `RentalParticipantScope` | VenueMarketplace | tenant API, AdminConsole, Notifications, Audit |
| `MarketplaceQuoteService` | VenueMarketplace | tenant API |
| `ApproveRental` | VenueMarketplace | tenant API |
| `DelegatedControlGuard` | Authorization | AccessControl, Kiosk, BadgePrinting, Scanning |
| `DelegatedAcsAssetPort` | AccessControl | VenueMarketplace |
| `DelegatedKioskAssetPort` | Kiosk | VenueMarketplace |
| `DelegatedPrinterAssetPort` | BadgePrinting | VenueMarketplace |
| `DelegatedScannerAssetPort` | Scanning | VenueMarketplace |

### Camera behavior

Camera assets return `catalog_only` / `not_applicable`. No provision, feed,
recording, identity, face, or biometric contract exists in Phase 6.

## Lifecycle commands

| Command | Purpose |
| --- | --- |
| `ActivateMarketplaceRentals` | Promote approved rentals to active/degraded and provision delegated resources |
| `ExpireMarketplaceRentals` | Release grants and expire reservations when windows end or revocation occurs |
| `FinalizeMarketplaceStatements` | Issue immutable settlement statements for completed rentals |

Commands are registered from `VenueMarketplaceServiceProvider` once lifecycle
workers are implemented.
