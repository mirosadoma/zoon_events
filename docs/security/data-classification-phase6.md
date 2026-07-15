# Phase 6 venue marketplace data classification

Owner: Security Engineering  
Last reviewed: 2026-07-15

This document classifies all Phase 6 venue marketplace data by sensitivity,
exposure, storage, retention, and redaction rules. Prior phase classifications
remain in their respective phase docs.

## Classification tiers

| Tier | Label | Description |
| --- | --- | --- |
| 1 | **Private** | Owner-only data; never exposed to organizers, catalog, or platform |
| 2 | **Participant** | Scoped by owner/organizer role; visible to the rental counterparty |
| 3 | **Public catalog** | Published safe fields projected to all authenticated organizers |
| 4 | **Operational** | Internal system bindings; opaque to all users |
| 5 | **Immutable** | Snapshot data frozen at creation; not editable after commit |
| 6 | **Audit** | System-generated evidence; read-only after creation |

## Private venue data (Tier 1 — owner-only)

| Data element | Storage | Exposure | Retention | Redaction |
| --- | --- | --- | --- | --- |
| `business_contact_name` | `venues` table | Owner tenant UI only (unless `publish_contact = true`) | Venue lifetime + retention window | Nulled on venue deletion |
| `business_contact_email` | `venues` table | Owner tenant UI only (unless `publish_contact = true`) | Venue lifetime + retention window | Nulled on venue deletion |
| `business_contact_phone` | `venues` table | Owner tenant UI only (unless `publish_contact = true`) | Venue lifetime + retention window | Nulled on venue deletion |
| `description_en`, `description_ar` (draft) | `venues` table | Owner tenant UI only while venue is in `draft` | Venue lifetime | Cleared on archive |
| Internal notes / decision reasons | `rental_requests.decision_reason` | Never in API responses; never in audit | Rental lifetime | Nulled after retention |
| Asset pricing configuration | `venue_assets` table | Owner tenant UI; projected as published price in catalog | Venue lifetime | — |
| Venue `version` (internal counter) | `venues` table | Owner tenant API only | Venue lifetime | — |

Contact fields are conditionally published: when `publish_contact = true` on the
venue profile, the contact name, email, and phone are included in the public catalog
projection. Default is `false`.

## Public catalog projection (Tier 3)

The marketplace catalog exposes only these fields from published assets:

| Field | Source | Notes |
| --- | --- | --- |
| `publication_public_id` | `marketplace_catalog_publications.public_id` | Opaque ULID |
| `asset_public_id` | `venue_assets.public_id` | Opaque ULID |
| `venue_public_id` | `venues.public_id` | Opaque ULID |
| `name_en`, `name_ar` | `venue_assets` | Localized display name |
| `asset_type` | `venue_assets.asset_type` | Enum value |
| `venue_name_en`, `venue_name_ar` | `venues` | Localized venue name |
| `description_en`, `description_ar` | `venue_assets` | Asset description (not venue description) |
| `country_code`, `city_code` | `venues` | Geographic location |
| `timezone` | `venues` | Venue timezone |
| `pricing_model` | `marketplace_catalog_publications` | `per_hour`, `per_day`, `per_rental` |
| `published_price_minor` | `marketplace_catalog_publications` | Integer minor-currency amount |
| `currency` | `marketplace_catalog_publications` | ISO 4217 code |
| `publication_version` | `marketplace_catalog_publications` | Monotonic version for concurrency |
| `selected_capabilities` | `marketplace_catalog_publications` | Capability list (e.g. `['authorize', 'event.ingest']`) |
| Contact fields | `venues` | Only when `publish_contact = true` |

The catalog projection **never** includes:

- Internal database IDs (`id`, `tenant_id`)
- Venue status history or draft data
- Asset operational status details (maintenance logs)
- Owner tenant identity beyond the venue public ID
- Financial configuration beyond published price

The catalog is served from `MarketplaceCatalogCache` and invalidated on
publication/withdrawal/status-change events.

## Operational bindings (Tier 4)

| Data element | Storage | Exposure | Notes |
| --- | --- | --- | --- |
| `control_delegation_id` | `delegated_asset_resources` | Never in API responses | Internal FK |
| `resource_public_reference` | `delegated_asset_resources` | Opaque reference in delegation API | Maps to module-specific resource |
| `resource_module` | `delegated_asset_resources` | Delegation API response | `access_control`, `kiosk`, `badge_printing`, `scanning`, `catalog_only` |
| `provisioning_status` | `delegated_asset_resources` | Delegation API response | `pending`, `provisioned`, `degraded`, `released`, `not_applicable` |
| `idempotency_key_hash` | multiple tables | Never in API responses | SHA-256 hash; raw key is not stored |
| `rental_request_id` | FK columns | Never in API responses | Internal FK binding |
| `event_id` | `control_delegations` | Never directly; scoped by delegation window | Internal FK |

All internal IDs are opaque. API responses use only `public_id` (ULID) values.
The `resource_public_reference` is an opaque identifier meaningful only to the
owning module's provisioner; the marketplace does not interpret its contents.

## Immutable snapshots (Tier 5)

| Data element | Storage | Frozen at | Notes |
| --- | --- | --- | --- |
| Rental line items | `rental_request_assets` | Rental submission | Asset type, quantity, pricing model, unit price, capabilities |
| Rental total | `rental_requests.total_minor` | Rental submission | Computed from line items; immutable |
| Rental window | `rental_requests.requested_start_at/end_at` | Rental submission | Cannot be modified after submission |
| Settlement statement lines | `settlement_statement_lines` | Statement generation | `asset_public_id`, `asset_type`, `name_en/ar`, `pricing_model`, `unit_price_minor`, `billable_units`, `line_total_minor`, `currency` |
| Statement revision number | `settlement_statements.revision` | Statement generation | Monotonically increasing; prior revisions are `superseded`, not deleted |
| Statement agreed total | `settlement_statements.agreed_total_minor` | Statement generation | Frozen financial fact |
| Publication version snapshot | `rental_request_assets.publication_version` | Rental submission | Records exact catalog version organizer agreed to |

Immutable records are never updated or soft-deleted. Statement revisions create
new rows with incremented `revision`; the prior revision's status changes to
`superseded` but its data is preserved.

## Participant data (Tier 2)

| Data element | Visible to owner | Visible to organizer | Visible to platform | Notes |
| --- | --- | --- | --- | --- |
| Rental request details | Yes (full) | Yes (full, own requests) | Yes (oversight) | Scoped by tenant context |
| Rental status / transitions | Yes | Yes | Yes | Status enum only; no free-text |
| Delegation status | Yes | Yes (own delegation) | No | Owner sees all; organizer sees own |
| Settlement statement | Yes | Yes | Yes (oversight) | Both participants see same immutable facts |
| Dispute status / reason code | Yes | Yes | Yes | Structured code only; free-text reason not exposed in API |
| Counterparty tenant identity | Organizer `public_id` visible | Owner venue `public_id` visible | Both visible | No internal tenant IDs |

Owner and organizer see the same rental/statement data but through different
query scopes (`RentalParticipantScope`). Neither participant can see the other's
internal user details, role assignments, or organizational configuration.

## Dispute notes (Tier 2 — restricted)

| Note type | Visible to owner | Visible to organizer | Visible to platform | In audit metadata |
| --- | --- | --- | --- | --- |
| Participant note | Yes (own) | Yes (own) | Yes | **No** (forbidden fragment `note`) |
| Platform internal note | No | No | Yes | **No** (forbidden fragment `note`) |
| Resolution summary | Yes (code only) | Yes (code only) | Yes (full) | `resolution_code` only |
| Dispute reason | Yes (code only) | Yes (code only) | Yes (full) | `reason_code` only |

Notes are stored in `marketplace_dispute_notes` with a `visibility` column
(`participant` or `internal`). Internal notes are visible only to platform
operators. No note content enters audit metadata due to the `note` forbidden
key fragment in `MarketplaceAuditEvent`.

## Audit metadata (Tier 6)

| Data element | Storage | Exposure | Retention |
| --- | --- | --- | --- |
| Action, outcome | `audit_entries` | Audit log viewers (`audit.view` / `platform.audit.view`) | 2,555 days (~7 years) |
| Correlation ID | `audit_entries` metadata | Audit log viewers | 2,555 days |
| `marketplace_scope` | `audit_entries` metadata | Audit log viewers | 2,555 days |
| `counterparty_tenant_id` | `audit_entries` metadata | Audit log viewers (platform only) | 2,555 days |
| Sanitized payload | `audit_entries` metadata | Audit log viewers | 2,555 days |
| HMAC, key ID, algorithm | `audit_entries` | Audit verification (`audit.verify`) | 2,555 days |

Audit entries are append-only and integrity-protected. The marketplace audit
writer adds `marketplace_scope` and `counterparty_tenant_id` to the standard
audit metadata envelope.

## Exports

### CSV statement export

| Aspect | Rule |
| --- | --- |
| Format | UTF-8 CSV with BOM (`\xEF\xBB\xBF`) |
| Delivery | `StreamedResponse` — no server-side file storage |
| Localization | Headers in English or Arabic based on request locale |
| Formula injection | All string fields prefixed with `'` if starting with `=`, `+`, `-`, `@`, tab, or carriage return |
| Audit | `statement.exported` event recorded before streaming begins |
| Cache headers | `Cache-Control: no-store, no-cache` |
| File naming | `statement-{statement_number}.csv` |

Exported fields are limited to the immutable statement line item data: asset
public ID, asset type, name, pricing model, unit price, billable units, line
total, and currency. No internal IDs, tenant identifiers, contact information,
or dispute details appear in exports.

## Prohibited data — never collected or stored

The marketplace module explicitly does **not** collect, store, process, or expose:

| Category | Examples | Enforcement |
| --- | --- | --- |
| Payment instruments | Credit card numbers, bank accounts, payment tokens | No payment fields in any marketplace model |
| Payout details | Bank routing, beneficiary accounts | Not modeled; out of scope |
| Refund records | Refund amounts, refund reasons | Not modeled; marketplace tracks agreed totals only |
| Penalty calculations | Late fees, cancellation penalties | Not modeled; financial enforcement is external |
| Tax / VAT data | Tax rates, VAT registration numbers | Not modeled; tax computation is external |
| PII beyond contact | Government IDs, passport numbers, biometrics | Not collected by venue marketplace |
| Authentication material | Passwords, tokens, API keys | `MarketplaceAuditEvent` forbidden fragments enforce this |

## Collection, exposure, storage, retention, and redaction summary

| Data category | Collected | Exposed to | Stored in | Retention | Redaction |
| --- | --- | --- | --- | --- | --- |
| Venue profile | Yes | Owner (full), catalog (published subset) | `venues` | Venue lifetime + retention window | Contact nulled on deletion; draft data cleared on archive |
| Asset configuration | Yes | Owner (full), catalog (published subset) | `venue_assets` | Venue lifetime | Retired assets remain read-only |
| Catalog publications | Yes | All organizers (published only) | `marketplace_catalog_publications` | Publication lifetime | Withdrawn publications set to `withdrawn` status |
| Availability windows | Yes | Owner only | `asset_availability_windows` | Replaced atomically on update | Old windows deleted on replacement |
| Rental requests | Yes | Owner + organizer (participant-scoped) | `rental_requests` + `rental_request_assets` | 2,555 days | `decision_reason` nulled after retention |
| Reservations | Yes | Owner only (implicit via asset availability) | `asset_reservations` | Rental lifetime | Released on rental completion/revocation |
| Control delegations | Yes | Owner + organizer (participant-scoped) | `control_delegations` + `delegated_asset_resources` | 2,555 days | Bindings released on delegation expiry/revocation |
| Settlement statements | Yes | Owner + organizer + platform | `settlement_statements` + `settlement_statement_lines` | 2,555 days (issued always retained) | Superseded statements preserved, not deleted |
| Disputes | Yes | Owner + organizer + platform | `marketplace_disputes` + `marketplace_dispute_notes` | 2,555 days (active always retained) | Evidence minimized after retention period |
| Audit records | Yes | Audit viewers only | `audit_entries` | 2,555 days | Integrity-protected; not redacted |
| CSV exports | Generated | Requesting participant | Not stored server-side (streamed) | N/A | Formula-injection escaped |
