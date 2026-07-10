# Zonetec AI Implementation Master Plan

Source PRD reference: 

## 0. Purpose Of This Document

This document is the master implementation plan for building **Zonetec**, an event management and access platform, using an AI-assisted spec-driven workflow such as **Spec-Kit**.

The goal is to give the AI enough business, product, backend, architecture, and delivery context to generate:

* Product specifications
* Technical plans
* Architecture decisions
* Data models
* API contracts
* Backend implementation tasks
* Testing plans
* Deployment plans
* Phase-by-phase delivery tickets
* Acceptance criteria
* Risk handling
* Open-question tracking

This document should be treated as the main project brief from start to finish.

---

# 1. Product Overview

## 1.1 Product Name

**Zonetec**

## 1.2 Product Type

Multi-tenant event management, ticketing, credentialing, wallet pass, kiosk, badge-printing, identity verification, access-control, and venue-infrastructure-rental platform.

## 1.3 Primary Market

GCC market, with Saudi Arabia as the primary launch market.

## 1.4 Delivery Models

Zonetec must support two deployment modes:

1. **Multi-tenant SaaS**

   * Default commercial model.
   * Hosted and managed centrally.
   * Multiple organizers and venues use isolated tenants.

2. **On-premise deployment**

   * For government, high-security, enterprise, and data-residency-sensitive clients.
   * Must support core functionality inside the customer environment.
   * Must support local processing for sensitive identity and biometric data where required.

---

# 2. Business Vision

Zonetec should become the unified event operations platform for organizers and venues in the GCC.

The platform should allow an organizer to run the full event lifecycle from:

1. Event creation
2. Registration
3. Ticketing
4. Payment
5. Credential generation
6. Wallet pass delivery
7. Identity verification
8. Kiosk check-in
9. Badge printing
10. Gate scanning
11. Turnstile / ACS access
12. Live monitoring
13. Reporting
14. Venue infrastructure rental
15. Marketplace settlement

The platform must reduce the need for organizers to integrate multiple vendors for ticketing, badges, scanning, identity, and physical access.

---

# 3. Core Product Positioning

Zonetec is **not** a public consumer marketplace like Ticketmaster.

Zonetec is an **organizer-owned, white-label, API-first event operations platform**.

The organizer controls:

* Branding
* Registration forms
* Ticketing
* Attendee data
* Payment ownership
* Access rules
* Event configuration
* On-site workflows

Zonetec provides:

* Event setup
* Registration
* Ticketing
* Credentials
* Wallet passes
* QR scanning
* Kiosk check-in
* Badge printing
* Manual desk operations
* Identity verification
* ACS integration
* Venue-owner infrastructure marketplace
* SaaS and on-premise delivery

---

# 4. Main Personas

## 4.1 Platform Admin

Runa / Zonetec internal administrator.

Responsibilities:

* Manage tenants
* Manage SaaS deployments
* Manage on-premise clients
* Manage billing configuration
* Manage compliance settings
* Manage platform-wide roles and permissions
* View system health
* View audit logs
* Manage marketplace disputes
* Manage deployment configurations

## 4.2 Event Organizer

The company, government body, agency, or person creating and running an event.

Needs:

* Create events quickly
* Configure event tiers
* Configure registration forms
* Sell tickets
* Issue passes
* Track attendees
* Enable check-in
* Enable badge printing
* Enable ID verification
* Enable access control
* Rent venue infrastructure
* Monitor live event operations

## 4.3 Attendee

The person registering for or attending the event.

Needs:

* Self-register easily
* Pay if required
* Receive QR code
* Add pass to Apple Wallet or Google Wallet
* Complete identity verification if required
* Check in quickly
* Print badge if needed
* Enter through gate or turnstile with minimal friction
* Understand what personal data is collected and why

## 4.4 Venue Owner

The owner or operator of a physical venue.

Needs:

* Register venue profile
* Register fixed infrastructure
* List gates, turnstiles, cameras, kiosks, printers, and access zones
* Set availability windows
* Set rental pricing
* Approve or reject rental requests
* Delegate time-boxed control to organizers
* Receive settlement statements

## 4.5 On-site Staff

Staff working during the event.

Needs:

* Search attendees
* Check in attendees
* Print badges
* Reprint badges
* Handle walk-ups
* Handle exceptions
* Manually override when allowed
* Assist VIP and VVIP attendees
* View real-time status

## 4.6 Security / ACS Operator

Person responsible for gates, zones, lanes, and access control.

Needs:

* Map credentials to zones
* Configure lane permissions
* Monitor gate health
* View entry and exit logs
* Handle anti-passback
* Handle emergency egress
* Manage fallback flows for failed scans or face no-match

---

# 5. Event Tiers

Zonetec must support different event tiers. Each tier controls default requirements for registration, identity assurance, and access.

## 5.1 Corporate

Typical use:

* Company events
* Internal events
* Conferences
* Private corporate gatherings

Default features:

* Self-registration
* Optional approval
* Domain allow-list
* Email or OTP verification
* QR credential
* Wallet pass
* Optional kiosk badge
* Optional turnstile access

## 5.2 Public

Typical use:

* Public events
* Paid events
* Exhibitions
* Conferences

Default features:

* Open registration
* Paid ticketing
* Ticket types
* Price tiers
* QR or wallet pass
* High-throughput scan lanes
* Optional ID for restricted events

## 5.3 VIP

Typical use:

* Invite-only sections
* Premium guests
* Reserved allocations
* Sponsor guests

Default features:

* Invite code or approval
* Optional government ID verification
* Optional face enrollment
* Dedicated lane
* Wallet or NFC fast-track where supported
* Manual concierge fallback

## 5.4 VVIP

Typical use:

* Government guests
* Royal / diplomatic / executive guests
* High-security events

Default features:

* Invite-only
* Host approval
* Allocation caps
* Required identity verification where possible
* Government verification through supported services
* Face capture fallback
* Biometric or face lane through ACS
* Manual concierge fallback
* Strong audit trail
* Strict PDPL handling
* Possible on-premise deployment

---

# 6. Product Scope

## 6.1 In Scope For The Full Product

Zonetec must eventually support:

* Multi-tenant SaaS
* Organizer accounts
* Venue-owner accounts
* Platform admin accounts
* Event creation
* Event tiers
* Event branding
* White-label registration pages
* Custom registration forms
* Ticket types
* Ticket inventory
* Scheduled price tiers
* Capacity-based price tiers
* Orders
* Payments
* Refunds
* Order edits
* Attendees
* Unique credentials
* QR codes
* Credential revocation
* Credential reissue
* Apple Wallet passes
* Google Wallet passes
* Wallet update
* Wallet revocation
* QR scanning
* Offline-tolerant scanning
* Kiosk check-in
* Badge printing
* Manual desk
* Badge designer
* Reprints
* Kiosk 2FA
* Kiosk/printer health monitoring
* ACS integration
* Gate authorization
* Zone mapping
* Lane mapping
* Entry logs
* Exit logs
* Anti-passback
* Emergency egress
* Identity verification
* Government identity verification where available
* Face capture fallback
* Manual review
* Biometric data handling
* Venue infrastructure marketplace
* Venue asset listing
* Asset availability
* Rental pricing
* Booking approval
* Time-boxed delegated control
* Settlement statements
* On-premise deployment
* Data residency
* Retention policies
* Audit logs
* Reporting
* Notifications
* Compliance controls

## 6.2 Out Of Scope For V1

Do not build these in the first version unless explicitly requested later:

* Public discovery marketplace for attendees
* Consumer event search
* Social networking features
* Full event mobile app
* Agenda builder
* Sponsor marketplace
* Event chat
* Attendee networking
* Real-time demand-based dynamic pricing engine
* Building new biometric matching engine from scratch
* Manufacturing hardware
* Hardware shipping logistics
* Full CRM
* Full accounting system
* Advanced reseller marketplace

---

# 7. Product Priorities

## 7.1 P0 Must-Have

The first working product must include:

* Multi-tenant SaaS foundation
* Tenant isolation
* Organizer account
* Event creation
* Event tier selection
* White-labeled registration
* Configurable registration form
* Ticket types
* Ticket inventory
* Scheduled price tiers
* Orders
* Regional payment gateway integration placeholder/adaptor
* Confirmation email/SMS
* Unique QR credential
* Credential revocation
* Credential reissue
* Apple Wallet pass
* Google Wallet pass
* QR scanning
* Basic attendee dashboard
* Basic check-in status
* Audit logging
* RBAC foundation
* API-first backend
* Production-grade database schema
* Automated tests

## 7.2 P1 Fast-Follow

Build after P0 is stable:

* Approval workflows
* Invite-only allocation
* Group registration
* Bulk registration
* Kiosk check-in
* Badge printing
* Badge designer
* Manual desk
* Reprints
* Kiosk 2FA
* Printer health monitoring
* ACS integration
* Zone mapping
* Lane mapping
* Anti-passback
* Identity verification add-on
* Face capture fallback
* Manual identity review
* Venue-owner account
* Venue asset listing
* Rental request flow

## 7.3 P2 Future / Design-For

Design the system so these can be added later:

* Dynamic pricing engine
* NFC tap-to-enter
* Face lane
* Camera feed access
* Tawakkalna-style temporary data-sharing code
* Full settlement automation
* Hybrid sync for on-prem
* Advanced analytics
* Event app
* Public discovery marketplace

---

# 8. Spec-Kit Execution Strategy

## 8.1 Global Instruction To Spec-Kit

Spec-Kit must treat this document as the root product and engineering brief.

Spec-Kit must generate separate specifications, plans, tasks, and implementation steps per phase.

Each phase must be independently shippable.

Each phase must include:

* Product requirements
* User stories
* Acceptance criteria
* Data model
* API contract
* Backend modules
* Frontend surfaces if applicable
* Background jobs
* Notifications
* Security rules
* RBAC rules
* Audit events
* Edge cases
* Test plan
* Migration plan
* Deployment notes
* Observability requirements
* Definition of Done

## 8.2 Required Spec-Kit Artifacts

Create the following structure:

```text
/specs
  /000-constitution
    constitution.md

  /001-platform-foundation
    spec.md
    plan.md
    data-model.md
    api-contracts.md
    tasks.md
    test-plan.md

  /002-registration-ticketing-credentials
    spec.md
    plan.md
    data-model.md
    api-contracts.md
    tasks.md
    test-plan.md

  /003-wallet-passes-scanning
    spec.md
    plan.md
    data-model.md
    api-contracts.md
    tasks.md
    test-plan.md

  /004-kiosk-badge-printing-manual-desk
    spec.md
    plan.md
    data-model.md
    api-contracts.md
    tasks.md
    test-plan.md

  /005-acs-access-control
    spec.md
    plan.md
    data-model.md
    api-contracts.md
    tasks.md
    test-plan.md

  /006-identity-verification
    spec.md
    plan.md
    data-model.md
    api-contracts.md
    tasks.md
    test-plan.md

  /007-venue-marketplace
    spec.md
    plan.md
    data-model.md
    api-contracts.md
    tasks.md
    test-plan.md

  /008-onprem-enterprise-compliance
    spec.md
    plan.md
    data-model.md
    api-contracts.md
    tasks.md
    test-plan.md

  /009-pilot-launch-hardening
    spec.md
    plan.md
    tasks.md
    test-plan.md
    release-checklist.md
```

## 8.3 Suggested Spec-Kit Command Flow

Use the equivalent of this flow for every phase:

```text
1. constitution
2. specify
3. plan
4. tasks
5. implement
6. test
7. review
8. harden
9. document
10. release
```

If Spec-Kit has different command names, use the equivalent command that creates:

* Constitution
* Product spec
* Technical plan
* Task breakdown
* Implementation
* Validation checklist

---

# 9. Engineering Constitution

## 9.1 Core Principles

The project must follow these rules:

1. **API-first**

   * Every backend capability must be available through documented APIs.
   * APIs must be versioned.

2. **Tenant isolation**

   * Tenant data must never leak across tenants.
   * Every tenant-scoped table must include `tenant_id`.

3. **Auditability**

   * Security-sensitive actions must create audit logs.
   * Credential actions, payment actions, identity actions, ACS actions, and admin actions must be auditable.

4. **Security by default**

   * Use RBAC.
   * Use least privilege.
   * Encrypt sensitive data.
   * Never expose raw secrets.
   * Use signed credentials and signed wallet pass payloads.

5. **Compliance by design**

   * PDPL-sensitive data must have retention rules.
   * Identity and biometric data must be minimized.
   * Store templates instead of raw images where feasible.
   * Support data residency configuration.

6. **Modular architecture**

   * Build as a modular monolith first unless the existing repo already uses another architecture.
   * Boundaries must be clear enough to split into services later.

7. **Event-driven where needed**

   * Use domain events for actions such as registration completed, credential issued, payment confirmed, pass revoked, scan accepted, scan rejected, identity verified, rental approved.

8. **Testability**

   * Every P0 requirement must have automated tests.
   * Integration points must have contract tests.
   * Hardware and external services must have mock adapters.

9. **Operational readiness**

   * Logs, metrics, traces, alerts, health checks, and runbooks are required before production.

10. **No fake production integrations**

* External integrations may be mocked in dev/test, but production must use real adapters behind stable interfaces.

---

# 10. Recommended Backend Architecture

## 10.1 Architecture Style

Use a **modular monolith with clean module boundaries** for the first production version.

Reasons:

* Faster delivery
* Easier local development
* Easier transaction management
* Easier AI-assisted implementation
* Easier deployment for SaaS and on-premise
* Can later split high-load modules into services

The architecture must still use service boundaries internally.

## 10.2 Main Backend Modules

### 10.2.1 Identity & Access Module

Handles:

* Users
* Roles
* Permissions
* Sessions
* MFA if required
* API keys
* Service tokens
* Tenant membership

### 10.2.2 Tenant Module

Handles:

* Tenant creation
* Tenant configuration
* Tenant branding
* Tenant domains
* Tenant data-residency settings
* Tenant retention settings

### 10.2.3 Organizer Module

Handles:

* Organizer profile
* Organizer users
* Organizer billing configuration
* Organizer event ownership

### 10.2.4 Event Module

Handles:

* Events
* Event lifecycle
* Event tiers
* Event settings
* Branding
* Capacity
* Location
* Dates
* Time zones
* Publishing

### 10.2.5 Registration Form Module

Handles:

* Form builder
* Field definitions
* Validation rules
* Required fields
* Conditional fields
* Form versions
* Submission storage

### 10.2.6 Ticketing Module

Handles:

* Ticket types
* Ticket inventory
* Ticket allocations
* Price tiers
* Holds
* Sold-out state
* Waitlist state
* Atomic inventory decrement

### 10.2.7 Order & Payment Module

Handles:

* Carts
* Orders
* Payment intents
* Payment callbacks
* Refunds
* Order edits
* Receipts
* Payment gateway adapters

### 10.2.8 Attendee Module

Handles:

* Attendee records
* Attendee profile fields
* Group registration
* Attendee status
* Attendee check-in state

### 10.2.9 Credential Module

Handles:

* Credential generation
* QR payload
* Credential signing
* Credential revocation
* Credential reissue
* Credential expiry
* Scan validation

### 10.2.10 Wallet Pass Module

Handles:

* Apple Wallet pass generation
* Google Wallet pass generation
* Wallet update
* Wallet revocation
* Wallet push notifications
* Pass templates

### 10.2.11 Notification Module

Handles:

* Email
* SMS
* WhatsApp if later needed
* Confirmation messages
* Wallet pass links
* Identity verification reminders
* Event updates

### 10.2.12 Check-In & Scanning Module

Handles:

* QR scan
* Offline scan sync
* First-scan validation
* Duplicate scan prevention
* Entry and exit events
* Staff scanner flow

### 10.2.13 Kiosk Module

Handles:

* Kiosk registration
* Kiosk sessions
* Kiosk login
* Device health
* Printer health
* Kiosk check-in
* Kiosk 2FA

### 10.2.14 Badge Printing Module

Handles:

* Badge templates
* Badge designer
* Print payload generation
* Printer adapter
* Badge reprint
* Badge print audit

### 10.2.15 ACS Integration Module

Handles:

* ACS authorization contract
* Zone mapping
* Lane mapping
* Gate release request
* Gate event logs
* Anti-passback
* Emergency egress events
* ACS health

### 10.2.16 Identity Verification Module

Handles:

* Verification requirements
* Consent
* Government verification adapters
* Face capture fallback
* Manual review
* Verification status
* Retention enforcement

### 10.2.17 Venue Marketplace Module

Handles:

* Venue-owner account
* Venue profile
* Asset inventory
* Asset availability
* Asset pricing
* Rental request
* Rental approval
* Time-boxed delegated control
* Settlement statement

### 10.2.18 Reporting Module

Handles:

* Event dashboards
* Registration counts
* Sales reports
* Scan reports
* Check-in throughput
* Gate throughput
* VVIP arrival alerts
* Marketplace usage reports

### 10.2.19 Audit Module

Handles:

* Immutable audit logs
* Security events
* Credential events
* Identity events
* Payment events
* ACS events
* Admin changes

### 10.2.20 Deployment & Ops Module

Handles:

* Health checks
* Version info
* Feature flags
* License checks for on-prem
* Sync status
* Backup status

---

# 11. Recommended Technical Foundation

## 11.1 Backend

Use the existing repository stack if one already exists.

If starting from zero, use one of these backend stacks:

### Preferred Option A

* TypeScript
* NestJS
* PostgreSQL
* Prisma or TypeORM
* Redis
* BullMQ or similar background job processor
* OpenAPI / Swagger
* Docker
* Kubernetes-ready deployment

### Preferred Option B

* .NET
* ASP.NET Core
* PostgreSQL
* Entity Framework Core
* Redis
* Hangfire or MassTransit
* OpenAPI / Swagger
* Docker
* Kubernetes-ready deployment

The AI must ask for confirmation before locking the final stack if the repository is empty.

## 11.2 Database

Primary database:

* PostgreSQL

Use PostgreSQL for:

* Tenants
* Users
* Events
* Orders
* Tickets
* Attendees
* Credentials
* Wallet passes
* Scans
* Identity status
* Marketplace
* Audit logs

Use Redis for:

* Rate limiting
* Short-lived sessions
* OTP
* Scan cache
* Offline sync tokens
* Distributed locks for inventory

Use object storage for:

* Logos
* Badge assets
* Export files
* Non-sensitive documents

Do not store sensitive face images in object storage unless explicitly allowed by legal/compliance configuration.

## 11.3 Messaging / Background Jobs

Use background jobs for:

* Sending emails
* Sending SMS
* Creating wallet passes
* Updating wallet passes
* Payment callback processing
* Export generation
* Offline scan reconciliation
* ACS event processing
* Retention cleanup
* Settlement statement generation

## 11.4 Observability

Implement:

* Structured logs
* Request IDs
* Tenant IDs in logs
* Metrics
* Distributed tracing
* Health checks
* Error tracking
* Audit logs
* Admin operational dashboard

---

# 12. Core Data Model

## 12.1 Tenant

Fields:

* id
* name
* slug
* type
* status
* default_locale
* default_timezone
* branding_config
* domain_config
* data_residency_region
* retention_policy_id
* created_at
* updated_at

## 12.2 User

Fields:

* id
* tenant_id
* name
* email
* phone
* status
* auth_provider
* last_login_at
* created_at
* updated_at

## 12.3 Role

Fields:

* id
* tenant_id
* name
* description
* permissions
* created_at
* updated_at

## 12.4 Event

Fields:

* id
* tenant_id
* organizer_id
* venue_id
* name
* slug
* description
* tier
* status
* start_at
* end_at
* timezone
* location_name
* location_address
* capacity
* branding_config
* registration_config
* identity_config
* access_config
* wallet_config
* created_at
* updated_at

## 12.5 Registration Form

Fields:

* id
* tenant_id
* event_id
* name
* version
* status
* fields_json
* validation_rules_json
* created_at
* updated_at

## 12.6 Ticket Type

Fields:

* id
* tenant_id
* event_id
* name
* description
* attendee_type
* tier
* capacity
* remaining_quantity
* price
* currency
* status
* sale_start_at
* sale_end_at
* created_at
* updated_at

## 12.7 Price Tier

Fields:

* id
* tenant_id
* event_id
* ticket_type_id
* name
* price
* currency
* starts_at
* ends_at
* capacity_threshold
* priority
* status
* created_at
* updated_at

## 12.8 Order

Fields:

* id
* tenant_id
* event_id
* buyer_name
* buyer_email
* buyer_phone
* status
* subtotal
* tax
* fees
* total
* currency
* payment_status
* payment_gateway
* payment_reference
* created_at
* updated_at

## 12.9 Order Item

Fields:

* id
* tenant_id
* order_id
* ticket_type_id
* attendee_id
* quantity
* unit_price
* total_price
* created_at
* updated_at

## 12.10 Attendee

Fields:

* id
* tenant_id
* event_id
* order_id
* ticket_type_id
* first_name
* last_name
* email
* phone
* company
* job_title
* nationality
* attendee_type
* registration_status
* approval_status
* identity_status
* checkin_status
* custom_fields_json
* created_at
* updated_at

## 12.11 Credential

Fields:

* id
* tenant_id
* event_id
* attendee_id
* credential_code
* qr_payload
* qr_signature
* status
* issued_at
* expires_at
* revoked_at
* revoke_reason
* reissued_from_credential_id
* created_at
* updated_at

Credential statuses:

* pending
* active
* revoked
* expired
* reissued

## 12.12 Wallet Pass

Fields:

* id
* tenant_id
* event_id
* attendee_id
* credential_id
* provider
* pass_serial_number
* pass_url
* status
* last_pushed_at
* created_at
* updated_at

Providers:

* apple
* google

Statuses:

* created
* active
* updated
* revoked
* expired
* failed

## 12.13 Scan Event

Fields:

* id
* tenant_id
* event_id
* attendee_id
* credential_id
* scanner_type
* scanner_id
* gate_id
* zone_id
* direction
* result
* reason
* offline_mode
* scanned_at
* synced_at
* created_at

Results:

* accepted
* rejected
* duplicate
* revoked
* expired
* unauthorized_zone
* anti_passback_rejected
* manual_override

## 12.14 Badge Template

Fields:

* id
* tenant_id
* event_id
* name
* attendee_type
* layout_json
* paper_size
* printer_type
* status
* created_at
* updated_at

## 12.15 Badge Print Job

Fields:

* id
* tenant_id
* event_id
* attendee_id
* credential_id
* badge_template_id
* kiosk_id
* printer_id
* status
* printed_by_user_id
* printed_at
* reprint_reason
* created_at
* updated_at

## 12.16 Kiosk

Fields:

* id
* tenant_id
* event_id
* device_name
* device_code
* location
* status
* last_seen_at
* printer_status
* scanner_status
* app_version
* created_at
* updated_at

## 12.17 ACS Zone

Fields:

* id
* tenant_id
* event_id
* name
* external_acs_zone_id
* status
* created_at
* updated_at

## 12.18 ACS Lane

Fields:

* id
* tenant_id
* event_id
* zone_id
* name
* external_acs_lane_id
* gate_type
* status
* created_at
* updated_at

## 12.19 ACS Authorization Rule

Fields:

* id
* tenant_id
* event_id
* ticket_type_id
* attendee_type
* zone_id
* lane_id
* access_direction
* valid_from
* valid_until
* status
* created_at
* updated_at

## 12.20 Identity Verification

Fields:

* id
* tenant_id
* event_id
* attendee_id
* method
* status
* consent_id
* provider
* provider_reference
* verified_name
* verified_nationality
* verified_at
* manual_review_by
* manual_review_at
* rejection_reason
* retention_until
* created_at
* updated_at

Methods:

* email_otp
* phone_otp
* gov_identity
* face_capture
* manual_review

Statuses:

* not_required
* pending
* gov_verified
* face_verified
* manually_approved
* rejected
* expired

## 12.21 Venue

Fields:

* id
* tenant_id
* venue_owner_id
* name
* description
* address
* city
* country
* timezone
* status
* created_at
* updated_at

## 12.22 Venue Asset

Fields:

* id
* tenant_id
* venue_id
* asset_type
* name
* description
* capabilities_json
* capacity_per_minute
* location
* pricing_model
* price
* currency
* status
* created_at
* updated_at

Asset types:

* turnstile
* security_gate
* camera
* kiosk
* printer
* scanner
* access_zone

## 12.23 Asset Availability

Fields:

* id
* tenant_id
* venue_asset_id
* available_from
* available_until
* status
* created_at
* updated_at

## 12.24 Rental Request

Fields:

* id
* tenant_id
* event_id
* venue_id
* requested_by_organizer_id
* status
* requested_start_at
* requested_end_at
* total_price
* currency
* owner_response_at
* rejection_reason
* created_at
* updated_at

Statuses:

* draft
* requested
* approved
* rejected
* active
* completed
* cancelled
* disputed

## 12.25 Rental Asset

Fields:

* id
* tenant_id
* rental_request_id
* venue_asset_id
* price
* currency
* delegated_access_status
* access_starts_at
* access_ends_at
* created_at
* updated_at

## 12.26 Audit Log

Fields:

* id
* tenant_id
* actor_user_id
* actor_type
* action
* entity_type
* entity_id
* before_json
* after_json
* ip_address
* user_agent
* created_at

---

# 13. Event Lifecycle

## 13.1 Event Statuses

Events must support:

* draft
* configured
* published
* registration_open
* registration_closed
* live
* completed
* cancelled
* archived

## 13.2 Event Flow

1. Organizer creates event.
2. Organizer chooses event tier.
3. Organizer configures branding.
4. Organizer configures registration form.
5. Organizer configures ticket types.
6. Organizer configures price tiers.
7. Organizer configures payment settings.
8. Organizer configures identity requirements.
9. Organizer configures wallet pass template.
10. Organizer configures check-in method.
11. Organizer configures ACS rules if needed.
12. Organizer publishes event.
13. Attendees register.
14. Payment is processed if required.
15. Credential is issued.
16. Wallet pass is generated.
17. Confirmation is sent.
18. Attendee completes identity verification if required.
19. Event goes live.
20. Attendee scans at kiosk, manual desk, or gate.
21. Credential is validated.
22. Badge is printed or gate is released.
23. Logs and dashboards update in real time.
24. Event completes.
25. Reports are generated.
26. Retention and cleanup jobs run.

---

# 14. Credential Rules

## 14.1 Credential Generation

Every attendee must receive a unique credential.

Credential must include:

* Credential ID
* Event ID
* Attendee ID
* Ticket type
* Issued timestamp
* Expiry timestamp
* Signature

The QR payload must be signed.

The QR payload must not expose sensitive personal data.

## 14.2 Credential Validation

Validation must check:

* Credential exists
* Credential belongs to event
* Credential is active
* Credential is not expired
* Credential is not revoked
* Attendee is approved if approval is required
* Identity verification is complete if required
* Ticket type is allowed for the zone/lane
* Anti-passback rules if applicable

## 14.3 Credential Revocation

Organizer or authorized staff can revoke credentials.

Revocation must:

* Mark credential as revoked
* Store reason
* Reject future scans
* Update wallet pass
* Send notification if configured
* Create audit log

## 14.4 Credential Reissue

Credential reissue must:

* Revoke old credential
* Create new credential
* Update wallet pass
* Create audit log
* Prevent old QR from working

---

# 15. Registration And Ticketing Rules

## 15.1 Registration Form

Registration forms must be configurable per event.

Supported field types:

* Text
* Email
* Phone
* Number
* Date
* Dropdown
* Multi-select
* Checkbox
* File upload if later enabled
* Hidden field
* Consent field

Field configuration must support:

* Required / optional
* Validation rules
* Display order
* Conditional visibility
* Internal-only fields
* Public fields

## 15.2 Ticket Types

Ticket type must support:

* Name
* Description
* Price
* Currency
* Quantity
* Sale start
* Sale end
* Attendee type
* Access zone rules
* Identity requirement rules
* Badge template mapping

## 15.3 Inventory Rules

Inventory decrement must be atomic.

The system must prevent overselling.

Use database transactions and/or distributed locks.

Inventory states:

* available
* reserved
* sold
* sold_out
* waitlist

## 15.4 Orders

Order statuses:

* draft
* pending_payment
* paid
* failed
* cancelled
* refunded
* partially_refunded

Payment statuses:

* pending
* authorized
* captured
* failed
* refunded
* partially_refunded

## 15.5 Payment Gateway

Payment integration must be adapter-based.

Do not hardcode one payment provider deep inside business logic.

Payment adapter interface must support:

* Create payment intent
* Verify callback
* Capture payment
* Refund payment
* Get payment status

KSA payment gateway selection is an open question.

---

# 16. Wallet Pass Rules

## 16.1 Apple Wallet

System must support:

* Pass generation
* Pass signing
* QR code inside pass
* Event details
* Expiry
* Pass update
* Pass revocation

## 16.2 Google Wallet

System must support:

* Pass generation
* QR code inside pass
* Event details
* Expiry
* Pass update
* Pass revocation

## 16.3 Wallet Pass Data

Pass must include:

* Event name
* Event date
* Event location
* Attendee name
* Ticket type
* QR credential
* Optional zone label
* Optional tier label

Do not include sensitive national ID or biometric data in wallet passes.

---

# 17. Check-In And Scanning Rules

## 17.1 Scan Sources

System must support scans from:

* Staff phone
* Handheld scanner
* Kiosk camera
* Gate scanner
* ACS lane
* Manual desk

## 17.2 Online Scan

Online scan must:

* Validate credential in real time
* Return accepted or rejected result
* Record scan event
* Update attendee check-in status
* Trigger dashboard update

## 17.3 Offline-Tolerant Scan

Offline mode must be designed carefully.

Offline scanner can use a locally synced allowlist for a specific event and time window.

Offline scan must:

* Record local scan
* Prevent obvious duplicate scans locally
* Sync when online
* Resolve conflicts
* Mark suspicious conflicts for review

## 17.4 Duplicate Entry

System must prevent duplicate entry unless authorized.

Rules:

* First valid entry is accepted.
* Second entry is rejected if anti-passback or single-entry mode is enabled.
* Staff override requires permission and audit log.

---

# 18. Kiosk And Badge Printing Rules

## 18.1 Kiosk Check-In

Kiosk must support:

* QR scan
* Name lookup
* Email lookup
* Phone lookup
* Optional ID lookup
* Optional OTP confirmation
* Badge print
* Clear error messages
* Branded UI

## 18.2 Badge Printing

Badge must support:

* Name
* Company
* Job title
* QR
* Ticket type
* Tier
* Zone
* Sponsor logo
* Organizer logo
* Color coding
* Pre-printed shell support

## 18.3 Manual Desk

Manual desk must support:

* Search attendee
* View attendee status
* Check in attendee
* Print badge
* Reprint badge
* Register walk-up attendee
* Handle exceptions
* Override with permission

## 18.4 Reprint Rules

Reprint must:

* Require permission
* Ask for reason
* Create audit log
* Optionally revoke old badge QR
* Print new badge

---

# 19. ACS Integration Rules

## 19.1 ACS Contract

Zonetec must integrate with Runa ACS through a documented secured contract.

The ACS integration must answer:

* Is this credential valid?
* Is this credential allowed in this zone?
* Is this credential allowed in this lane?
* Is this credential allowed now?
* Should the gate release?
* Should the scan be rejected?
* What reason should be logged?

## 19.2 Required ACS API Concepts

The integration must support:

* Credential validation
* Zone authorization
* Lane authorization
* Entry event callback
* Exit event callback
* Gate health status
* Emergency egress event
* Anti-passback decision
* Offline fallback behavior

## 19.3 Gate Authorization Flow

1. Attendee presents QR, NFC, RFID, or face match at lane.
2. ACS sends credential or identity reference to Zonetec.
3. Zonetec validates credential and event rules.
4. Zonetec returns allow or deny.
5. ACS releases gate if allowed.
6. ACS sends event log back to Zonetec.
7. Zonetec records scan and access event.
8. Dashboard updates.

## 19.4 Anti-Passback

Anti-passback must reject re-entry unless exit has been recorded.

Anti-passback rules must be configurable per event, zone, and ticket type.

## 19.5 Emergency Egress

System must support fire-alarm or emergency signal behavior.

Emergency egress must:

* Fail open where configured
* Record emergency event
* Notify dashboard
* Be testable

---

# 20. Identity Verification Rules

## 20.1 Identity Add-On

Identity verification must be configurable per event and tier.

Options:

* Not required
* Optional
* Required before credential issuance
* Required before gate entry
* Required only for VIP
* Required only for VVIP

## 20.2 Consent

Before identity capture, attendee must see:

* What data is collected
* Why it is collected
* How long it is stored
* Who can access it
* Whether it is processed on-premise or SaaS
* How to request deletion if applicable

Consent must be stored.

## 20.3 Government Verification

Where available, integrate with appropriate KSA government identity service.

Government API details are open questions and must be implemented behind adapters.

Do not hardcode Nafath, Absher, or Yaqeen directly into core business logic.

Use adapter interface:

* Start verification
* Verify callback
* Fetch verification result
* Map verified attributes
* Store verification status

## 20.4 Face Capture Fallback

Face capture fallback is required for:

* Non-residents
* Guests
* People without supported government verification
* Failed government verification
* Events where face verification is enabled

Face capture must support:

* Liveness check if available
* Manual review
* Approval / rejection
* Audit trail
* Retention policy

## 20.5 Biometric Data

Biometric data must follow strict rules:

* Minimize collection
* Prefer templates over raw images
* Encrypt sensitive data
* Apply retention window
* Support on-premise processing
* Restrict cross-border transfer
* Log access

---

# 21. Venue Marketplace Rules

## 21.1 Venue Owner Account

Venue owners must have a separate account type.

Venue owner can:

* Create venue profile
* Add assets
* Set capabilities
* Set availability
* Set prices
* Review rental requests
* Approve requests
* Reject requests
* View settlement statements

## 21.2 Asset Types

Supported asset types:

* Turnstile
* Security gate
* Camera
* Kiosk
* Printer
* Scanner
* Access lane
* Access zone

## 21.3 Rental Request Flow

1. Organizer browses venue assets.
2. Organizer selects assets for event dates.
3. Organizer submits rental request.
4. Venue owner receives request.
5. Venue owner approves or rejects.
6. If approved, asset control is delegated for the event window.
7. Organizer can configure assets for event use.
8. Access auto-revokes after event.
9. Settlement statement is generated.

## 21.4 Delegated Control

Delegated control must be:

* Scoped to event
* Scoped to assets
* Scoped to time window
* Revocable by venue owner
* Audited
* Automatically revoked at the end

---

# 22. SaaS And On-Premise Rules

## 22.1 SaaS

SaaS must support:

* Multi-tenancy
* Tenant isolation
* Central admin
* Central observability
* Tenant configuration
* Tenant branding
* Tenant domains

## 22.2 On-Premise

On-premise package must support:

* Registration
* Ticketing
* Credentials
* Wallet passes where network access allows
* Kiosk
* Badge printing
* ACS integration
* Identity verification adapters
* Local biometric processing
* Audit logs
* Local database
* Backup
* Updates
* License management

## 22.3 Hybrid Sync

Hybrid sync is P1/P2.

It may sync:

* Non-sensitive metrics
* System health
* Version info
* Aggregated operational reports

It must not sync sensitive identity or biometric data unless explicitly configured and legally approved.

---

# 23. RBAC Model

## 23.1 Roles

Initial roles:

* Platform Super Admin
* Platform Support
* Tenant Admin
* Organizer Admin
* Event Manager
* Ticketing Manager
* Check-In Staff
* Badge Desk Staff
* Security Operator
* ACS Operator
* Venue Owner Admin
* Venue Asset Manager
* Finance Manager
* Auditor
* Read-only Viewer

## 23.2 Permission Groups

Permission groups:

* tenant.manage
* users.manage
* roles.manage
* events.create
* events.update
* events.publish
* events.cancel
* registration.manage
* ticketing.manage
* orders.manage
* payments.refund
* attendees.view
* attendees.manage
* credentials.issue
* credentials.revoke
* credentials.reissue
* wallet.manage
* scan.perform
* checkin.perform
* badge.print
* badge.reprint
* kiosk.manage
* acs.manage
* identity.configure
* identity.review
* marketplace.manage
* venue.manage
* rentals.approve
* reports.view
* audit.view
* compliance.manage

---

# 24. API Guidelines

## 24.1 General Rules

All APIs must:

* Use versioning
* Use JSON
* Use consistent error format
* Include request ID
* Enforce tenant scope
* Enforce permissions
* Validate input
* Return predictable status codes
* Be documented in OpenAPI

## 24.2 Example API Groups

```text
/auth
/tenants
/users
/roles
/events
/events/{eventId}/branding
/events/{eventId}/registration-form
/events/{eventId}/ticket-types
/events/{eventId}/price-tiers
/events/{eventId}/orders
/events/{eventId}/attendees
/events/{eventId}/credentials
/events/{eventId}/wallet-passes
/events/{eventId}/scans
/events/{eventId}/checkins
/events/{eventId}/badges
/events/{eventId}/kiosks
/events/{eventId}/acs/zones
/events/{eventId}/acs/lanes
/events/{eventId}/acs/rules
/events/{eventId}/identity
/events/{eventId}/reports
/venues
/venues/{venueId}/assets
/venues/{venueId}/availability
/rentals
/audit-logs
/admin/health
/admin/jobs
```

## 24.3 Error Format

Use consistent error response:

```json
{
  "error": {
    "code": "CREDENTIAL_REVOKED",
    "message": "This credential has been revoked.",
    "details": {},
    "request_id": "req_123"
  }
}
```

---

# 25. Domain Events

The system must publish internal domain events for important actions.

Examples:

* tenant.created
* event.created
* event.published
* registration.submitted
* order.created
* payment.succeeded
* payment.failed
* attendee.created
* credential.issued
* credential.revoked
* credential.reissued
* wallet_pass.created
* wallet_pass.updated
* wallet_pass.revoked
* scan.accepted
* scan.rejected
* badge.printed
* badge.reprinted
* kiosk.offline
* kiosk.online
* acs.gate_released
* acs.access_denied
* identity.verification_started
* identity.verified
* identity.rejected
* rental.requested
* rental.approved
* rental.rejected
* rental.activated
* rental.completed
* audit.created

Use an outbox pattern if needed to prevent event loss.

---

# 26. Non-Functional Requirements

## 26.1 Performance

Targets:

* Median check-in time: under 10 seconds
* Stretch check-in time: under 5 seconds
* First-scan success rate: at least 95%
* QR validation API p95: under 500ms
* Gate authorization p95: under 300ms where infrastructure allows
* Registration page p95: under 1 second server response
* Payment callback processing: idempotent and near real-time

## 26.2 Availability

SaaS production target:

* 99.9% uptime for core registration and scanning APIs
* Graceful degradation for wallet updates and notifications
* Offline-tolerant scanning for live events

## 26.3 Scalability

System must support:

* Multiple tenants
* Multiple concurrent events
* High scan throughput during arrival peaks
* Large attendee lists
* High notification volume
* Kiosk and scanner concurrency

## 26.4 Security

Required:

* HTTPS everywhere
* Secure password hashing
* MFA-ready admin accounts
* RBAC
* Tenant isolation
* Encryption at rest
* Encryption in transit
* Signed QR credentials
* Signed webhook payloads
* Rate limiting
* Audit logs
* Secrets management
* Security headers
* Input validation
* SQL injection protection
* XSS protection
* CSRF protection where applicable

## 26.5 Compliance

Required:

* Consent records
* Data retention policies
* Data deletion workflows
* Sensitive data classification
* Biometric data minimization
* Cross-border transfer controls
* On-premise option
* Audit log export
* Access logs for sensitive records

## 26.6 Localization

Must support:

* Arabic
* English
* RTL layout for Arabic
* Gregorian calendar
* KSA timezone defaults
* Multi-currency design, with SAR as primary launch currency

---

# 27. Testing Strategy

## 27.1 Unit Tests

Required for:

* Ticket inventory logic
* Price tier selection
* Credential generation
* Credential validation
* QR signature verification
* Revocation logic
* Reissue logic
* RBAC checks
* Tenant scoping
* Identity status rules
* Anti-passback rules

## 27.2 Integration Tests

Required for:

* Registration flow
* Order and payment flow
* Credential issuance
* Wallet pass creation
* Scan validation
* Badge print job creation
* ACS authorization mock
* Identity verification mock
* Rental approval flow

## 27.3 Contract Tests

Required for external interfaces:

* Payment gateway adapter
* Apple Wallet adapter
* Google Wallet adapter
* ACS adapter
* Government identity adapter
* SMS provider
* Email provider
* Printer adapter

## 27.4 End-To-End Tests

Required flows:

* Organizer creates event and publishes registration
* Attendee registers and receives QR
* Attendee pays and receives credential
* Attendee adds wallet pass
* Staff scans QR and checks in attendee
* Revoked credential is rejected
* Reissued credential works and old credential fails
* Kiosk prints badge
* Manual desk reprints badge
* ACS gate authorization succeeds
* Unauthorized zone access fails
* Identity-required attendee cannot enter before verification
* Venue owner approves asset rental

## 27.5 Load Tests

Required scenarios:

* High registration traffic
* High payment callback traffic
* High scan throughput
* Multiple concurrent events
* Large attendee import
* Wallet update batch

## 27.6 Security Tests

Required:

* Tenant data isolation tests
* RBAC bypass tests
* API authentication tests
* Rate limit tests
* QR tampering tests
* Webhook signature tests
* Sensitive data access tests

---

# 28. Phase 0 — Project Foundation And Constitution

## 28.1 Goal

Create the project foundation before building product features.

## 28.2 Scope

* Confirm tech stack
* Create repository structure
* Create coding standards
* Create architecture standards
* Create database migration process
* Create environment configuration
* Create CI pipeline
* Create test framework
* Create API documentation framework
* Create logging framework
* Create audit logging foundation
* Create RBAC foundation
* Create tenant isolation foundation
* Create feature flag foundation
* Create external adapter pattern

## 28.3 Deliverables

* Constitution document
* Architecture decision records
* Local dev setup
* Docker setup
* Database migration setup
* CI pipeline
* Test runner
* Linter
* Formatter
* OpenAPI setup
* Health check endpoint
* Basic auth scaffold
* Tenant model
* User model
* Role model
* Permission model
* Audit log model

## 28.4 Acceptance Criteria

* Developer can run app locally
* Tests run successfully
* API docs are generated
* Database migrations run successfully
* Tenant-scoped request context exists
* RBAC middleware exists
* Audit logging service exists
* Health endpoint works
* CI fails on broken tests
* Secrets are not committed

---

# 29. Phase 1 — Registration, Ticketing, Orders, And Credentials

## 29.1 Goal

Build the v1 anchor: self-registration, ticketing, payment flow, attendee records, and unique credentials.

## 29.2 Scope

* Organizer can create event
* Organizer can configure event tier
* Organizer can configure event branding
* Organizer can create registration form
* Organizer can create ticket types
* Organizer can create scheduled price tiers
* Attendee can open registration link
* Attendee can submit registration
* Attendee can buy ticket if paid
* System creates order
* System integrates with payment adapter
* System creates attendee
* System issues unique credential
* System sends confirmation email/SMS
* Organizer can view attendees
* Organizer can revoke credential
* Organizer can reissue credential

## 29.3 Main Backend Modules

* Event Module
* Registration Form Module
* Ticketing Module
* Order Module
* Payment Module
* Attendee Module
* Credential Module
* Notification Module
* Audit Module

## 29.4 User Stories

### Organizer

As an organizer, I want to create an event so attendees can register.

As an organizer, I want to configure ticket types so I can control access, price, and capacity.

As an organizer, I want to configure scheduled price tiers so early-bird and regular pricing works automatically.

As an organizer, I want to see attendees and orders so I can manage the event.

As an organizer, I want to revoke and reissue credentials so invalid passes stop working.

### Attendee

As an attendee, I want to register myself so I can attend the event.

As an attendee, I want to pay online if the event is paid.

As an attendee, I want to receive a QR credential after successful registration.

## 29.5 Acceptance Criteria

* Event can be created and published.
* Registration form displays event branding.
* Form submission creates attendee record.
* Ticket inventory decrements atomically.
* Sold-out ticket cannot be purchased.
* Payment success creates paid order.
* Payment failure does not issue active credential.
* Paid attendee receives unique credential.
* Credential QR is signed.
* Revoked credential fails validation.
* Reissued credential creates new QR and invalidates old QR.
* Confirmation email/SMS is queued.
* All sensitive actions create audit logs.

## 29.6 Testing Requirements

* Unit tests for ticket inventory
* Unit tests for price tiers
* Unit tests for credential signing
* Integration test for free registration
* Integration test for paid registration
* Integration test for payment success
* Integration test for payment failure
* Integration test for credential revocation
* Integration test for credential reissue
* Tenant isolation tests

---

# 30. Phase 2 — Wallet Passes And QR Scanning

## 30.1 Goal

Allow attendees to add passes to Apple Wallet and Google Wallet, and allow staff/scanners to validate QR passes.

## 30.2 Scope

* Apple Wallet pass adapter
* Google Wallet pass adapter
* Wallet pass generation
* Wallet pass update
* Wallet pass revocation
* QR scan API
* Staff scanner API
* Check-in state
* Duplicate scan handling
* Offline-tolerant scanning design
* Scan logs
* Live basic dashboard

## 30.3 Main Backend Modules

* Wallet Pass Module
* Credential Module
* Scan Module
* Check-In Module
* Notification Module
* Reporting Module

## 30.4 User Stories

### Attendee

As an attendee, I want to add my pass to Apple Wallet or Google Wallet so I can enter easily.

As an attendee, I want my wallet pass to update when event details change.

### Staff

As staff, I want to scan an attendee QR and know if entry is allowed.

As staff, I want the system to reject duplicate, revoked, or expired credentials.

### Organizer

As an organizer, I want to see real-time check-in counts.

## 30.5 Acceptance Criteria

* Confirmation includes Add to Apple Wallet link.
* Confirmation includes Add to Google Wallet link.
* Wallet pass contains QR credential.
* Wallet pass does not expose sensitive data.
* Event detail update updates wallet pass.
* Credential revocation revokes or invalidates wallet pass.
* QR scan validates credential.
* Duplicate scan is rejected if single-entry mode is enabled.
* Scan event is logged.
* Dashboard shows registration and check-in counts.
* Offline scan design is documented and partially implemented if required for pilot.

## 30.6 Testing Requirements

* Wallet generation unit tests
* Wallet update tests
* Wallet revocation tests
* QR scan validation tests
* Duplicate scan tests
* Revoked credential scan tests
* Expired credential scan tests
* Offline scan sync tests if implemented

---

# 31. Phase 3 — Kiosk, Badge Printing, And Manual Desk

## 31.1 Goal

Enable on-site self-service and staff-operated credentialing.

## 31.2 Scope

* Kiosk device registration
* Kiosk session management
* QR kiosk check-in
* Attendee lookup
* Optional OTP confirmation
* Badge template designer backend
* Badge print payload
* Printer adapter
* Print job tracking
* Manual desk
* Walk-up registration
* Badge reprints
* Kiosk/printer health monitoring

## 31.3 Main Backend Modules

* Kiosk Module
* Badge Printing Module
* Attendee Module
* Credential Module
* Check-In Module
* Audit Module

## 31.4 User Stories

### Attendee

As an attendee, I want to scan my QR at a kiosk and print my badge quickly.

### Staff

As staff, I want to search for attendees and print or reprint badges.

As staff, I want to register walk-up attendees if allowed.

### Organizer

As an organizer, I want to design badge templates without code.

### Operations

As operations, I want to monitor kiosk and printer health.

## 31.5 Acceptance Criteria

* Kiosk can be registered to an event.
* Kiosk can scan QR and retrieve attendee.
* Kiosk can look up attendee by name/email/phone.
* Kiosk can create badge print job.
* Badge payload contains configured fields.
* Manual desk can check in attendees.
* Manual desk can print and reprint badges.
* Reprint requires permission and reason.
* Kiosk health updates are visible.
* Printer status is visible where adapter supports it.
* All manual overrides create audit logs.

## 31.6 Testing Requirements

* Kiosk registration tests
* Kiosk scan tests
* Attendee lookup tests
* Badge template rendering tests
* Print job tests
* Reprint permission tests
* Manual desk flow tests
* Kiosk health tests

---

# 32. Phase 4 — ACS And Access Control

## 32.1 Goal

Integrate Zonetec credentials with Runa ACS for physical access control.

## 32.2 Scope

* Credential-to-ACS authorization contract
* ACS adapter
* Zone mapping
* Lane mapping
* Ticket type to zone rules
* Entry logs
* Exit logs
* Anti-passback
* Gate event callbacks
* Gate health dashboard
* Emergency egress events
* ACS audit logs

## 32.3 Main Backend Modules

* ACS Integration Module
* Credential Module
* Scan Module
* Event Module
* Reporting Module
* Audit Module

## 32.4 User Stories

### ACS Operator

As an ACS operator, I want to map ticket types to zones and lanes.

As an ACS operator, I want a gate to release only when the credential is authorized.

As an ACS operator, I want anti-passback to reject duplicate re-entry.

As an ACS operator, I want to view gate events and health.

## 32.5 Acceptance Criteria

* ACS adapter interface is documented.
* ACS can request credential authorization.
* Zonetec returns allow or deny decision.
* Decision includes reason.
* Zone rules are enforced.
* Lane rules are enforced.
* Entry event is logged.
* Exit event is logged.
* Anti-passback is enforced.
* Emergency egress event is logged and visible.
* Gate health is visible.
* All ACS actions are auditable.

## 32.6 Testing Requirements

* ACS contract tests
* Zone authorization tests
* Lane authorization tests
* Anti-passback tests
* Gate callback tests
* Emergency egress tests
* Unauthorized zone rejection tests
* ACS health tests

---

# 33. Phase 5 — Identity Verification

## 33.1 Goal

Allow organizers to require different identity assurance levels per event and tier.

## 33.2 Scope

* Identity verification configuration
* Consent capture
* Government identity adapter interface
* Mock government provider
* Face capture fallback interface
* Manual review
* Identity status on attendee
* Identity requirement enforcement before credential issuance or gate entry
* Retention policy enforcement
* Audit logs

## 33.3 Main Backend Modules

* Identity Verification Module
* Attendee Module
* Credential Module
* Event Module
* Audit Module
* Compliance Module

## 33.4 User Stories

### Organizer

As an organizer, I want to require identity verification for VIP or VVIP attendees.

### Attendee

As an attendee, I want to complete identity verification before the event.

As an attendee, I want to understand what data is collected and why.

### Reviewer

As a reviewer, I want to approve or reject fallback face/manual verification.

## 33.5 Acceptance Criteria

* Organizer can configure identity requirements per event/tier.
* Attendee sees consent notice before verification.
* Consent is stored.
* Identity verification status is tracked.
* Government provider is behind adapter.
* Face capture fallback is behind adapter.
* Manual reviewer can approve or reject.
* Rejection reason is stored.
* Verified status attaches to attendee and credential.
* Identity-required attendee cannot enter if not verified.
* Retention cleanup job exists.
* Sensitive data access is audited.

## 33.6 Testing Requirements

* Identity config tests
* Consent tests
* Mock gov verification tests
* Face fallback tests
* Manual review tests
* Gate enforcement tests
* Retention cleanup tests
* Sensitive audit tests

---

# 34. Phase 6 — Venue Marketplace

## 34.1 Goal

Allow venue owners to rent fixed infrastructure to event organizers.

## 34.2 Scope

* Venue-owner account type
* Venue profile
* Asset inventory
* Asset capabilities
* Asset availability
* Asset pricing
* Organizer discovery
* Rental request
* Venue approval
* Time-boxed delegated control
* Auto-revocation
* Settlement statement
* Marketplace audit logs

## 34.3 Main Backend Modules

* Venue Marketplace Module
* Tenant Module
* Event Module
* ACS Module
* Billing/Settlement Module
* Audit Module

## 34.4 User Stories

### Venue Owner

As a venue owner, I want to list my fixed infrastructure so organizers can rent it.

As a venue owner, I want to approve rental requests before control is delegated.

### Organizer

As an organizer, I want to rent venue assets for my event dates.

### Platform Admin

As platform admin, I want to view marketplace activity and settlement statements.

## 34.5 Acceptance Criteria

* Venue owner can create venue.
* Venue owner can add assets.
* Venue owner can define availability.
* Venue owner can define pricing.
* Organizer can request assets for event dates.
* Venue owner can approve or reject.
* Approved rental grants scoped control.
* Control starts at rental start time.
* Control ends automatically at rental end time.
* Owner can revoke delegated control.
* Settlement statement is generated.
* All marketplace actions are audited.

## 34.6 Testing Requirements

* Venue creation tests
* Asset listing tests
* Availability conflict tests
* Rental request tests
* Approval tests
* Rejection tests
* Delegated control tests
* Auto-revocation tests
* Settlement statement tests

---

# 35. Phase 7 — On-Premise, Enterprise, And Compliance

## 35.1 Goal

Prepare Zonetec for enterprise and government clients requiring local deployment and data residency.

## 35.2 Scope

* On-premise packaging
* Environment configuration
* Local database
* Local storage
* Local job worker
* Local ACS integration
* Local identity adapter configuration
* Backup and restore
* License activation
* Update process
* Data residency settings
* Retention rules
* Compliance reporting
* Audit export
* Optional hybrid sync

## 35.3 Main Backend Modules

* Deployment/Ops Module
* Compliance Module
* Audit Module
* Identity Module
* ACS Module
* Tenant Module

## 35.4 User Stories

### Enterprise Client

As an enterprise client, I want to run Zonetec inside my environment.

### Platform Admin

As platform admin, I want to manage on-premise licenses and update packages.

### Compliance Officer

As a compliance officer, I want to configure retention and export audit logs.

## 35.5 Acceptance Criteria

* App can run from containerized deployment.
* On-premise package includes app, workers, DB migrations, and required services.
* Local configuration supports data residency.
* Retention rules are configurable.
* Audit logs can be exported.
* Backup and restore process is documented.
* Update process is documented.
* Sensitive services can run without cloud dependency where required.
* Hybrid sync does not send sensitive data by default.

## 35.6 Testing Requirements

* On-premise deployment test
* Migration test
* Backup restore test
* License config test
* Retention test
* Audit export test
* Offline/local mode test
* Hybrid sync privacy test

---

# 36. Phase 8 — Pilot, Hardening, Launch, And Scale

## 36.1 Goal

Prepare the system for real events and production use.

## 36.2 Scope

* Pilot event setup
* Production readiness review
* Load testing
* Security testing
* Monitoring
* Runbooks
* Incident response
* Support process
* UAT
* Launch checklist
* Post-event reporting
* Feedback loop

## 36.3 Pilot Criteria

Choose a pilot event with:

* Known attendee count
* Clear event tier
* Clear check-in method
* Controlled payment requirements
* Known hardware setup
* Known ACS requirements if used
* Defined success metrics

## 36.4 Launch Readiness Checklist

* All P0 tests pass
* No critical security issues
* Load test completed
* Payment integration verified
* Email/SMS verified
* Wallet pass verified
* QR scanning verified
* Credential revocation verified
* Backups verified
* Monitoring configured
* Incident runbook ready
* Support team trained
* Admin users configured
* Pilot event dry run completed
* Rollback plan ready

---

# 37. Success Metrics

## 37.1 Product Metrics

Track:

* Number of tenants
* Number of events created
* Number of published events
* Number of registrations
* Number of paid orders
* Payment success rate
* Wallet pass adoption rate
* Check-in success rate
* Median check-in time
* First-scan success rate
* Credential revocation count
* Reissue count
* Badge print count
* Reprint count
* ACS accepted entries
* ACS rejected entries
* Identity verification completion rate
* VVIP pre-event verification rate
* Marketplace rental usage
* Support tickets per event

## 37.2 Target Metrics

Initial targets:

* Self-service check-in share: at least 80%
* Median check-in time: under 10 seconds
* First-scan success rate: at least 95%
* Wallet adoption: at least 50%
* VVIP identity verification before event: at least 90%
* Corporate event setup time: under 1 day

---

# 38. Risk Register

## 38.1 Government Identity API Access

Risk:

* Nafath, Absher, or Yaqeen production access may not be available early.

Mitigation:

* Build adapter interface.
* Implement mock provider.
* Implement face/manual fallback.
* Do not block core registration and ticketing.

## 38.2 ACS Interface Unclear

Risk:

* Runa ACS protocol, latency, and offline behavior may not be fully documented.

Mitigation:

* Create ACS contract early.
* Build mock ACS.
* Run integration tests before Phase 4 starts.

## 38.3 Payment Gateway Selection

Risk:

* Gateway choice affects order and settlement flows.

Mitigation:

* Use payment adapter.
* Confirm KSA gateway before production.
* Keep payment core provider-neutral.

## 38.4 Badge Printer Compatibility

Risk:

* Printer models may differ across venues.

Mitigation:

* Define supported printer list.
* Build printer adapter.
* Start with one certified printer model.

## 38.5 Offline Scanning Conflicts

Risk:

* Offline mode can allow duplicate or invalid entries.

Mitigation:

* Use short-lived allowlists.
* Sync frequently.
* Flag conflicts.
* Use event-specific risk configuration.

## 38.6 Tenant Data Leakage

Risk:

* Multi-tenant mistakes can expose data.

Mitigation:

* Tenant ID on all scoped tables.
* Tenant middleware.
* Automated tenant isolation tests.
* DB constraints where possible.

## 38.7 Biometric Compliance

Risk:

* Face data handling may violate PDPL or client policies.

Mitigation:

* Minimize data.
* Store templates where feasible.
* Support on-premise.
* Add retention controls.
* Audit access.

---

# 39. Open Questions

Spec-Kit must track these questions as blockers or assumptions.

## 39.1 Identity

* Do we have production access to Nafath?
* Do we have production access to Absher digital ID?
* Do we have production access to Yaqeen?
* What are the commercial and legal terms?
* What exact attendee attributes can be verified?
* Can non-residents be verified?
* What is the required consent language?

## 39.2 ACS

* What is the exact ACS protocol?
* Is ACS REST, WebSocket, TCP, MQTT, or another protocol?
* What is the expected latency budget?
* What offline behavior is required?
* How are zones and lanes represented in ACS?
* How does emergency egress signal arrive?
* How is anti-passback currently implemented?

## 39.3 Payments

* Which KSA payment gateway should be used?
* Who is merchant of record?
* Are marketplace payouts handled by Zonetec or externally?
* Are refunds required in v1?
* Are taxes/VAT required in v1 invoices?

## 39.4 Wallet

* Do we already have Apple Wallet certificates?
* Do we already have Google Wallet issuer account?
* What pass design should be used?
* What languages should wallet passes support?

## 39.5 Kiosk And Badge

* Which kiosk OS is supported?
* Which printer models are supported?
* Is printing browser-based, local agent-based, or direct network printing?
* What badge sizes are required?
* Are pre-printed shells required in v1?

## 39.6 On-Premise

* Is on-premise required for first client?
* Should on-premise run via Docker Compose, Kubernetes, VM, or appliance?
* Should on-premise support air-gapped mode?
* How are updates delivered?
* How is license activation handled?

---

# 40. AI Implementation Rules

The AI must follow these rules while generating code or tasks:

1. Do not build everything at once.
2. Work phase by phase.
3. Each phase must be shippable.
4. Start with foundation and P0 flows.
5. Do not implement P2 before P0 and P1 foundations are stable.
6. Ask clarifying questions only for true blockers.
7. Otherwise, document assumptions and continue.
8. Generate migrations with every schema change.
9. Generate tests with every feature.
10. Generate API documentation with every endpoint.
11. Generate seed data for local testing.
12. Use adapters for external integrations.
13. Mock external providers in test environment.
14. Never store sensitive secrets in code.
15. Do not bypass tenant isolation.
16. Do not bypass RBAC.
17. Do not skip audit logs for sensitive actions.
18. Do not put sensitive personal data inside QR payloads.
19. Do not expose raw biometric data through APIs.
20. Maintain a changelog for every phase.

---

# 41. Definition Of Done

A phase is done only when:

* Product spec is complete
* Technical plan is complete
* Data model is complete
* API contracts are documented
* Backend implementation is complete
* Database migrations are complete
* Unit tests pass
* Integration tests pass
* Security checks pass
* RBAC is enforced
* Tenant isolation is tested
* Audit logs are implemented
* Observability is implemented
* Documentation is updated
* Acceptance criteria are verified
* Demo flow works locally
* Deployment notes are written

---

# 42. First Spec-Kit Task

Start by creating the constitution and Phase 0 foundation.

The first AI task should be:

```text
Create the Zonetec project constitution from this master plan.

The constitution must define:
- Product principles
- Backend architecture principles
- Security principles
- Tenant isolation rules
- Compliance rules
- Testing rules
- API rules
- Data model rules
- Integration adapter rules
- Spec-driven delivery rules
- Definition of Done

After the constitution, create Phase 0 specification, technical plan, data model, API contracts, task breakdown, and test plan.
```

---

# 43. Second Spec-Kit Task

After Phase 0 is complete, create Phase 1.

```text
Create Phase 1 for Registration, Ticketing, Orders, and Credentials.

Use this master plan as the source of truth.

Generate:
- spec.md
- plan.md
- data-model.md
- api-contracts.md
- tasks.md
- test-plan.md

Focus only on:
- Organizer event creation
- Event tier configuration
- White-label registration
- Configurable forms
- Ticket types
- Scheduled price tiers
- Orders
- Payment adapter
- Attendees
- Unique QR credentials
- Credential revocation
- Credential reissue
- Confirmation email/SMS
- Audit logs
- Tenant isolation
- RBAC
```

---

# 44. Final Delivery Order

The project must be delivered in this order:

1. Phase 0 — Foundation and Constitution
2. Phase 1 — Registration, Ticketing, Orders, and Credentials
3. Phase 2 — Wallet Passes and QR Scanning
4. Phase 3 — Kiosk, Badge Printing, and Manual Desk
5. Phase 4 — ACS and Access Control
6. Phase 5 — Identity Verification
7. Phase 6 — Venue Marketplace
8. Phase 7 — On-Premise, Enterprise, and Compliance
9. Phase 8 — Pilot, Hardening, Launch, and Scale

Do not move to the next phase until the previous phase has passed its Definition of Done.
