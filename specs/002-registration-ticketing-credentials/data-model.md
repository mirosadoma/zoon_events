# Phase 1 Data Model

**Feature**: Registration, Ticketing, Orders, and Credentials  
**Database**: MySQL 8.4, shared schema, tenant-first ownership

## Conventions

- Externally visible identifiers are 26-character ULIDs.
- Every product record carries non-null `tenant_id`; event-owned records also
  carry non-null `event_id`.
- Composite foreign keys enforce that referenced event, form, ticket, order,
  attendee, and credential records belong to the same tenant.
- Timestamps are UTC with microsecond precision. Event timezone is an IANA name
  used only for schedule evaluation and presentation.
- Money is stored as signed 64-bit minor units plus three-letter currency.
- Encrypted fields include a `*_key_id`; exact lookup uses HMAC blind indexes.
- Status values are constrained by database checks and application enums.
- Mutable tables use `created_at` and `updated_at`; immutable evidence and
  version rows use `created_at` only.

## Entity Relationships

```text
Tenant
  └─ Event
      ├─ EventBranding
      ├─ RegistrationForm
      │   └─ RegistrationFormVersion
      │       └─ RegistrationSubmission
      ├─ TicketType
      │   ├─ TicketInventory
      │   └─ PriceTier
      ├─ InventoryHold
      ├─ Order
      │   ├─ OrderItem
      │   ├─ PaymentAttempt
      │   └─ Refund
      ├─ Attendee
      │   └─ Credential (self-reference: supersedes)
      └─ Notification
```

## Event

Represents the organizer-owned lifecycle and public registration boundary.

| Field | Rules |
|---|---|
| `id`, `tenant_id` | Required; unique `(tenant_id,id)` |
| `slug` | Lowercase URL-safe; unique per tenant |
| `name`, `description` | Localized content references; bounded |
| `tier` | `corporate`, `public`, `vip`, `vvip` |
| `status` | Event lifecycle enum |
| `timezone` | Valid IANA timezone |
| `start_at`, `end_at` | End after start |
| `registration_opens_at`, `registration_closes_at` | Close after open and no later than event policy permits |
| `location_name`, `location_address` | Bounded localized values |
| `capacity` | Positive integer; nullable only for explicitly unlimited draft events |
| `active_form_version_id` | Same-tenant/event form version; required to publish |
| `created_by_user_id`, `published_by_user_id` | Tenant actor references |
| `published_at`, `cancelled_at`, `archived_at` | Must agree with lifecycle |

Indexes: `(tenant_id,status,start_at,id)`, `(tenant_id,slug)`, and
`(tenant_id,registration_opens_at,registration_closes_at)`.

Lifecycle:

```text
draft -> configured -> published -> registration_open -> registration_closed
published/registration_open/registration_closed -> live -> completed -> archived
draft/configured/published/registration_open/registration_closed/live -> cancelled
```

Backward transitions require an explicit allowed action; completed, cancelled,
and archived are terminal except approved archival administration.

## Event Branding

Stores event presentation references within tenant-approved branding.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | One active branding record per event |
| `brand_reference` | Must resolve to current tenant configuration |
| `domain_reference` | Must resolve to an approved tenant domain |
| `content_en`, `content_ar` | Bounded localized title/summary/policy references |
| `sender_name_en`, `sender_name_ar` | Validated display names |
| `status` | `draft`, `active`, `retired` |

No arbitrary CSS, script, uploaded asset, or provider secret is stored here.

## Registration Form

Stable form identity for an event.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required and same scope |
| `name` | Unique within event |
| `status` | `draft`, `active`, `retired` |
| `created_by_user_id` | Required tenant actor |

## Registration Form Version

Immutable validation contract presented to attendees.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id`, `registration_form_id` | Same scope |
| `version` | Positive integer; unique per form |
| `status` | `draft`, `published`, `retired` |
| `fields` | Bounded JSON array of supported typed field definitions |
| `schema_hash` | SHA-256 of canonical field definitions |
| `privacy_notice_version`, `terms_version` | Required to publish |
| `published_by_user_id`, `published_at` | Required only when published |
| `created_at` | Immutable evidence time |

Published rows cannot be updated or deleted through application paths.
Field keys are stable within a form and may not use reserved/internal names.
Conditional rules may reference only earlier fields and cannot form cycles.

## Registration Submission

Immutable answers and consent evidence for one attendee intent.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `form_version_id` | Exact immutable form version |
| `submission_key_hash` | Hash of public idempotency intent; unique per event |
| `answers_ciphertext`, `encryption_key_id` | Encrypted canonical answers |
| `consent_evidence` | Bounded JSON: notice versions, choices, timestamp, source fingerprint |
| `locale` | `en` or `ar` |
| `submitted_at` | Required |

No raw IP address, full user agent, hidden internal field, or card data is kept.

## Ticket Type

Defines an event ticket product.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `code` | Stable; unique per event |
| `name_en`, `name_ar`, `description_en`, `description_ar` | Bounded |
| `attendee_type` | Stable language-neutral key |
| `base_price_minor`, `currency` | Non-negative; currency immutable after first hold |
| `sale_starts_at`, `sale_ends_at` | Valid UTC window |
| `status` | `draft`, `active`, `paused`, `sold_out`, `retired` |
| `created_by_user_id` | Required tenant actor |

Index: `(tenant_id,event_id,status,sale_starts_at,sale_ends_at,id)`.

## Ticket Inventory

Authoritative counters locked for sales.

| Field | Rules |
|---|---|
| `tenant_id`, `event_id`, `ticket_type_id` | Primary scope; one row per ticket type |
| `capacity` | Positive integer |
| `held_quantity`, `sold_quantity` | Non-negative |
| `version` | Monotonic diagnostic counter |

Constraint: `held_quantity + sold_quantity <= capacity`.
Remaining quantity is derived, never independently writable.

## Price Tier

Deterministic scheduled or capacity-threshold price.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id`, `ticket_type_id` | Same scope |
| `name` | Bounded |
| `price_minor`, `currency` | Non-negative; ticket currency match |
| `starts_at`, `ends_at` | Optional half-open UTC interval |
| `remaining_at_most` | Optional positive threshold |
| `priority` | Unique active priority per ticket type |
| `status` | `draft`, `active`, `retired` |

At least one time or capacity selector is required. Active rules must not be
ambiguous at any boundary.

## Inventory Hold

Time-bounded reservation associated with checkout.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id`, `ticket_type_id` | Same scope |
| `order_id` | Unique and nullable only during atomic creation |
| `quantity` | Positive; Phase 1 normally one |
| `quoted_price_minor`, `currency`, `price_tier_id` | Immutable quote snapshot |
| `status` | `active`, `converted`, `released`, `expired`, `reconciliation` |
| `expires_at` | Default 15 minutes from creation |
| `released_reason_code` | Stable safe code |

Only one terminal transition is allowed. Conversion moves held to sold in the
same locked inventory transaction.

## Order

Buyer transaction and fulfillment aggregate.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `public_reference` | Opaque; unique per tenant |
| `status` | `draft`, `pending_payment`, `paid`, `failed`, `cancelled`, `refunded`, `partially_refunded` |
| `buyer_name_ciphertext`, `buyer_email_ciphertext`, `buyer_phone_ciphertext` | Confidential |
| `buyer_email_index`, `buyer_phone_index`, `encryption_key_id` | HMAC indexes/key ID |
| `subtotal_minor`, `tax_minor`, `fees_minor`, `total_minor`, `currency` | Immutable after payment intent |
| `inventory_hold_id` | Required |
| `locale` | `en` or `ar` |
| `paid_at`, `cancelled_at`, `refunded_at` | Lifecycle-consistent |

Indexes: `(tenant_id,event_id,status,created_at,id)`,
`(tenant_id,event_id,buyer_email_index,created_at,id)`.

Order transition highlights:

```text
draft -> pending_payment -> paid
draft/pending_payment -> failed|cancelled
paid -> partially_refunded -> refunded
paid -> refunded
```

Free orders move from draft to paid in the registration transaction with a
zero total and no provider payment.

## Order Item

Immutable ticket and amount snapshot.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id`, `order_id`, `ticket_type_id` | Same scope |
| `attendee_id` | Set when attendee is created; unique in Phase 1 |
| `quantity` | Positive; one for self-registration |
| `unit_price_minor`, `tax_minor`, `fees_minor`, `total_minor`, `currency` | Immutable |
| `price_tier_id`, `ticket_name_snapshot` | Historical context |

## Payment Account

Tenant mapping to a configured payment adapter.

| Field | Rules |
|---|---|
| `id`, `tenant_id` | Required |
| `adapter_key` | `fake` in test; `moyasar` first production candidate |
| `secret_reference` | Reference only; never secret material |
| `account_reference`, `webhook_route_token_hash` | Non-secret/hashed mapping |
| `mode` | `test`, `live` |
| `status` | `draft`, `active`, `disabled` |

Only one active account per tenant/currency/mode unless an approved routing
policy exists.

## Payment Attempt

Append-oriented provider interaction state.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id`, `order_id`, `payment_account_id` | Same scope |
| `attempt_number` | Unique per order |
| `provider_payment_id` | Encrypted or bounded provider identifier; unique per account |
| `idempotency_key_hash` | Unique per account/operation |
| `status` | `pending`, `authorized`, `captured`, `failed`, `cancelled`, `refunded`, `partially_refunded`, `unknown` |
| `requested_minor`, `captured_minor`, `refunded_minor`, `currency` | Bounded by order total |
| `provider_reason_code` | Sanitized mapped category |
| `last_reconciled_at`, `next_reconcile_at` | Recovery scheduling |

Provider response bodies, card numbers, security codes, and authorization
secrets are never persisted.

## Payment Webhook Receipt

Deduplication and processing evidence.

| Field | Rules |
|---|---|
| `id`, `payment_account_id`, `provider_event_id` | Unique provider event |
| `payload_digest` | SHA-256 only |
| `status` | `received`, `verified`, `processed`, `ignored`, `failed` |
| `reason_code`, `received_at`, `processed_at` | Safe evidence |

The receipt does not become financial authority; processing fetches current
payment state through the adapter.

## Refund

Authorized full or partial refund intent.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id`, `order_id`, `payment_attempt_id` | Same scope |
| `amount_minor`, `currency` | Positive and cumulative total no greater than captured |
| `status` | `pending`, `succeeded`, `failed`, `unknown` |
| `reason`, `requested_by_user_id` | Required and bounded |
| `provider_refund_id`, `idempotency_key_hash` | Unique when present |
| `last_reconciled_at` | Recovery |

## Attendee

Event participant created only when free registration completes or paid payment
is confirmed.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id`, `order_id`, `order_item_id`, `ticket_type_id` | Same scope; one attendee per item |
| `first_name_ciphertext`, `last_name_ciphertext`, `email_ciphertext`, `phone_ciphertext` | Confidential |
| `email_index`, `phone_index`, `encryption_key_id` | Optional exact lookup |
| `submission_id` | Required immutable submission |
| `registration_status` | `registered`, `cancelled`, `anonymized` |
| `preferred_locale` | `en` or `ar` |
| `registered_at`, `cancelled_at`, `anonymized_at` | Lifecycle-consistent |

Phase 1 has no `identity_status` or `checkin_status`; those belong to later
phases.

## Credential

Signed event entitlement.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id`, `attendee_id`, `ticket_type_id` | Same scope |
| `status` | `active`, `revoked`, `expired`, `superseded` |
| `token_version` | `zt1` |
| `key_id` | Credential signing key identifier |
| `nonce_hash` | Unique random-token digest |
| `token_digest` | Unique digest; raw token is returned/generated, not stored |
| `issued_at`, `expires_at` | Expiry after issue and bounded by event policy |
| `revoked_at`, `revoked_by_user_id`, `revocation_reason` | Required when revoked |
| `superseded_by_id` | Same attendee/event; acyclic |

Constraint: at most one active credential per attendee. Reissue locks the
attendee's credential set, supersedes the old record, and inserts the new one
in the audited transaction.

## Credential Signing Key Metadata

Non-secret metadata for a versioned signing key.

| Field | Rules |
|---|---|
| `key_id` | Stable unique identifier |
| `public_key` | Ed25519 public key |
| `private_key_reference` | Secret-store reference only |
| `status` | `pending`, `active`, `verify_only`, `retired`, `compromised` |
| `not_before`, `verify_until` | Rotation windows |

Only `active` signs. `active` and `verify_only` may verify within policy.
Compromised keys fail closed and trigger controlled credential response.

## Notification

Durable localized delivery intent.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id`, `attendee_id`, `order_id`, `credential_id` | Same scope |
| `channel` | `email`, `sms` |
| `template_key`, `template_version`, `locale` | Immutable rendering identity |
| `destination_ciphertext`, `destination_index`, `encryption_key_id` | Confidential |
| `content_digest` | No content body retained |
| `adapter_key`, `provider_message_id` | Bounded |
| `status` | `pending`, `processing`, `sent`, `delivered`, `temporary_failure`, `permanent_failure` |
| `attempt_count`, `next_attempt_at`, `last_reason_code` | Bounded retry |

Unique `(tenant_id,order_id,channel,template_key,template_version)` prevents
duplicate confirmation intents.

## Cross-Entity Invariants

1. A record may reference only records sharing its `tenant_id`; event-owned
   records must also share `event_id`.
2. A paid order cannot become paid and an attendee/credential cannot be created
   until authoritative captured amount and currency equal the order total.
3. Free completion, paid completion, hold conversion, attendee creation,
   credential issuance, notification intent, and audit evidence are each
   transactionally consistent at their required boundary.
4. Held plus sold inventory never exceeds capacity.
5. Refund totals never exceed captured totals.
6. Published form versions, submissions, order items, financial histories, and
   credential token digests are application-immutable.
7. Cross-tenant identifiers and random identifiers produce identical
   not-found behavior.
8. Audit and telemetry receive identifiers, classifications, and redacted
   change markers, never decrypted personal answers or provider secrets.
