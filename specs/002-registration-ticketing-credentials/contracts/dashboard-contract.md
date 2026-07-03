# Phase 1 Dashboard and Public Experience Contract

## Organizer Navigation

Tenant navigation adds only:

- Events
- Event overview
- Branding
- Registration form
- Ticket types and price tiers
- Orders
- Attendees
- Credentials

Wallet, scanning, check-in, kiosk, badge, ACS, identity, marketplace, and
hardware pages or placeholders are forbidden in Phase 1.

## Page Authorization

| Page | Required permission |
|---|---|
| Event list/show | `event.view` |
| Create/update event | `event.manage` |
| Publish/cancel event | `event.publish` / `event.cancel` |
| Registration form | `registration.manage` |
| Ticket types/price tiers | `ticketing.manage` |
| Orders | `order.view` |
| Refund action | `payment.refund` |
| Attendees | `attendee.view` |
| Attendee correction | `attendee.manage` |
| Credential lifecycle | `credential.view`, `credential.revoke`, `credential.reissue` |

Navigation visibility is only a convenience. Server authorization is
authoritative, and unauthorized/cross-tenant records appear in zero page props.

## Required States

Every list/form/detail surface has loading, empty, validation error, dependency
unavailable, forbidden, not found, conflict, and success states. Payment and
notification screens additionally show pending reconciliation and queued
delivery without exposing provider payloads.

## Public Registration

- Host plus event slug resolves the tenant/event.
- Branding, content, form, availability, checkout, and confirmation support
  Arabic RTL and English LTR.
- Form errors preserve safe attendee input but never render internal-only
  fields or another attendee's data.
- The public order access token is returned once and stored in the browser only
  as needed for the journey; it is never placed in analytics or logs.
- Checkout shows immutable subtotal, tax, fees, total, and currency before
  redirecting or initiating provider action.

## Accessibility and Privacy

- All fields have programmatic labels, descriptions, error associations, and
  keyboard-operable controls.
- Direction changes use logical layout properties; emails, phone numbers,
  identifiers, and currency remain bidi-isolated.
- Sensitive attendee and buyer data is omitted from list props unless required
  and permitted; detail props explicitly allow-list fields.
- No raw QR token appears in organizer list pages, logs, telemetry, or HTML
  unrelated to initial issue/reissue.

## Parity

Dashboard mutations invoke the same application actions, validators, policies,
idempotency rules, audited transactions, and events as versioned API operations.
Public pages use the same public registration contracts. No presentation layer
queries module persistence directly.
