# Venue Marketplace Dashboard Contract

All pages use the existing DashboardLayout, shared navigation, DataTable,
StatusBadge, skeleton, toast, confirmation/reason modal and route error boundary.
Navigation visibility is convenience only; server-side tenant/platform context,
participant scope and permission checks remain authoritative.

## Tenant Navigation

| Area | Web route | Eligibility / permission | Primary projection |
|---|---|---|---|
| Venues | /tenant/venues | venue_owner or hybrid + venue.manage | owner-private venues/assets |
| Venue detail | /tenant/venues/{venue_public_id} | venue.manage | owner-private profile, inventory, availability, price, publication readiness |
| Marketplace | /tenant/marketplace | organizer or hybrid + marketplace.manage | allowlisted active catalog publications |
| My rentals | /tenant/marketplace/rentals | marketplace.manage, rentals.approve, or reports.view; actions vary | participant rental list |
| Rental detail | /tenant/marketplace/rentals/{rental_public_id} | participant + matching operation permission | participant-specific rental/delegation timeline |
| Statements | /tenant/marketplace/statements | reports.view | participant statement list |
| Statement detail | /tenant/marketplace/statements/{statement_public_id} | participant + reports.view | immutable statement/revisions/dispute status |

## Platform Navigation

| Area | Web route | Permission | Projection |
|---|---|---|---|
| Marketplace oversight | /platform/marketplace | platform.marketplace.view | minimized cross-participant activity |
| Dispute detail | /platform/marketplace/disputes/{dispute_public_id} | platform.marketplace.disputes.manage | participant facts, participant-visible timeline, platform-only notes/actions |

Platform pages never expose resource bindings or provide venue-asset control.
Every detail view/search/export is audited.

## Owner Venue Journey

### Venue index

- filters: status, country/city, publication readiness; bounded cursor
- columns: localized name, city/country, status, active/published asset counts,
  future reservation warning, updated time
- actions: create, open; archive only from detail with reason/confirmation
- states: skeleton, no venues with create CTA, filtered empty, forbidden, error

### Venue create/edit

- bilingual name/description/address; platform country/city; IANA timezone;
  confidential business contact and explicit publish-contact toggle
- field errors are equivalent in Arabic/English and focus the first invalid field
- save is idempotent; archived venue is read-only

### Asset inventory

- type-specific capability template; no arbitrary capability text/commands
- location, throughput/capacity, operational status, price model/minor-unit-safe
  currency input, availability windows and binding status
- secret/external binding value is write-only/masked; never rendered back
- publication readiness checklist lists missing fields without revealing secrets
- publish/unpublish uses confirmation; future approved rental conflicts disable the
  action and link to the owner's participant rental view
- concurrent/stale version returns a conflict banner and reload action

## Organizer Catalog and Request Journey

### Catalog

- filters: venue, country/city, asset type, capability, minimum capacity,
  price/currency and required start/end
- results contain only published fields and opaque IDs
- required-window mode labels availability as advisory until owner approval
- states: skeleton, no published assets, no filter match, catalog unavailable,
  retry, forbidden; no owner-private fallback
- keyboard order and result announcements work in RTL/LTR

### Quote/request drawer or page

- organizer selects one owned eligible event and assets from one venue/currency
- shows venue timezone and requested UTC-equivalent window
- line table shows model, unit price, billable units, line total, currency/total
- quote digest/version remains opaque; quote_changed returns a review-new-quote
  state and never submits silently
- mixed venue/currency, event ownership, invalid window and asset unavailable are
  field/list errors
- successful submission redirects to rental detail and prevents duplicate submit

## Shared Rental Journey

### List

- filters: role (owner/organizer), status, date, venue, event, dispute; bounded
  cursor
- each row identifies viewer role, localized event/venue, window, currency/total,
  lifecycle, delegation and dispute status
- unrelated participant IDs resolve as not found, never forbidden-with-existence

### Detail

- immutable facts: parties' business display names, event snapshot, venue, lines,
  quote snapshot, requested window/timezone, total
- timeline: submitted, decision, reservation, activation/degradation, revocation/
  expiry/completion, statement/dispute events visible to participant
- owner actions with rentals.approve: approve, reject with required reason, revoke
  approved/active with required reason
- organizer actions with marketplace.manage: cancel requested/approved before
  activation
- approval conflict shows requested lines affected but never the other organizer
- delegation panel shows not-started/active/degraded/revoked/expired/completed,
  local countdown and server timestamp; the client countdown is informational
- provisioned operational links appear only for organizer users who also have the
  existing module permission and only while the server projection permits
- polling/refresh cannot extend access; expired/revoked result replaces stale UI

## Statement and Dispute Journey

### Statement detail/export

- requires reports.view and participant scope
- shows revision, issued time, rental outcome, agreed window, itemized agreed
  amount and explicit statement-only notice: no payment/payout is asserted
- linked superseded/current revisions remain available
- CSV export is an audited server stream; error leaves page intact

### Participant dispute

- one open dispute per statement; reason category + bounded required reason
- shared timeline excludes platform-only notes
- participant can view but cannot set review/resolution status
- original statement remains visually immutable

### Platform dispute

- requires platform.marketplace.disputes.manage
- list filters: status, owner, organizer, venue, event, opened date
- detail separates shared facts, participant timeline and platform-only notes
- start review/add note/resolve/reject require confirmation; resolve/reject requires
  stable resolution code and participant-visible summary
- no action changes rental control or statement facts and no payment action exists

## Permission Matrix

| Action | Required conditions |
|---|---|
| Manage venue/asset/availability/price/publication | owner participant; venue_owner/hybrid; venue.manage |
| Browse catalog, quote, request, organizer cancel | organizer/hybrid; marketplace.manage; owned event for quote/request |
| Review/approve/reject/owner revoke | owner participant; rentals.approve |
| Configure provisioned ACS resource | organizer participant; active delegation; asset/capability/event match; acs.configure |
| Configure provisioned kiosk | organizer participant; active delegation; asset/capability/event match; kiosk.manage |
| Use provisioned printer | organizer participant; active delegation; capability/event match; badge.print or other existing operation key |
| Use provisioned scanner | organizer participant; active delegation; capability/event match; checkin.scan.submit or the matching existing operation key |
| View/export statement | participant; reports.view |
| Open dispute | participant; reports.view |
| View platform marketplace | platform context; platform.marketplace.view |
| Manage dispute | platform context; platform.marketplace.disputes.manage |
| View tenant audit evidence | participant's own tenant context; audit.view |

## Status Vocabulary

All labels and reason descriptions exist in locales/en.ts and locales/ar.ts;
codes are never localized in persistence/API.

- Venue: draft, active, suspended, archived
- Asset: draft, active, maintenance, offline, retired
- Publication: private, published, withdrawn
- Rental: requested, approved, rejected, active, completed, cancelled, revoked
- Delegation: pending, active, degraded, revoked, expired, completed
- Reservation: reserved, active, completed, released
- Statement: issued, superseded; dispute none/open/under_review/resolved
- Dispute: open, under_review, resolved, rejected

## Required Page States

Every page/action must demonstrate:

- loading skeleton that preserves layout and has an accessible name
- true empty and filtered empty states with relevant next action
- field validation and stale/conflict states
- forbidden state for known navigation; not-found for unrelated participant IDs
- degraded/unavailable state with stable retry behavior
- in-flight submit disabling and duplicate protection
- toast/inline success tied to server result, never optimistic fabrication for
  approval, delegation, statement or dispute
- route-level recovery that preserves safe filters/form values
- Arabic/RTL and English/LTR parity, logical spacing, locale-aware date/number/
  currency formatting, keyboard focus and screen-reader announcements

## Responsive Behavior

- desktop lists use shared DataTable; small screens use accessible stacked rows
  with the same fields/actions and no horizontal page overflow
- quote/statement line tables may scroll inside a labeled region, not the page
- availability/time inputs preserve timezone labels at every width
- destructive/privileged actions remain in explicit menus/buttons and never rely
  on swipe/hover only
