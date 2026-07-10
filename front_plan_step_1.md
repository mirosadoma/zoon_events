# Frontend Phase — Control Dashboard For Completed Core Phases

## 1. Phase Goal

Build a complete React frontend dashboard for the already completed backend phases:

1. Phase 0 — Foundation and Constitution
2. Phase 1 — Registration, Ticketing, Orders, and Credentials
3. Phase 2 — Wallet Passes and QR Scanning
4. Phase 3 — Kiosk, Badge Printing, and Manual Desk
5. Phase 4 — ACS and Access Control

The goal is not to build new backend business features.

The goal is to expose, test, manage, and visually validate all completed backend functionality through a clean admin/operator dashboard.

This phase should make the completed backend work visible and usable from the browser before starting Phase 5 or any new product phase.

---

## 2. Important Rule

Do not start Phase 5 before this frontend phase is complete.

This phase is considered a frontend consolidation phase.

It should focus on:

* React pages
* Dashboard UX
* API integration
* RBAC-based navigation
* Forms
* Tables
* Detail pages
* Status badges
* Actions
* Loaders
* Error states
* Empty states
* Responsive UI
* Arabic/English readiness
* RTL readiness

---

## 3. Technical Stack

Use the existing project stack.

Assume:

* Frontend: React
* Backend: Laravel
* Database: MySQL
* API style: REST API
* Authentication: existing auth from Phase 0
* Authorization: existing RBAC from Phase 0
* Tenant isolation: existing tenant foundation from Phase 0
* Do not introduce Docker
* Do not rebuild backend modules that already exist
* Use existing APIs where available
* If an API is missing, document it clearly and create a frontend placeholder or API adapter layer

---

## 4. Main Frontend Sections

The dashboard must include these main sections:

1. Main Dashboard Overview
2. Platform Foundation
3. Events Management
4. Registration Forms
5. Ticketing
6. Orders and Payments
7. Attendees
8. Credentials
9. Wallet Passes
10. QR Scanning and Check-in
11. Kiosks
12. Badge Printing
13. Manual Desk
14. ACS Access Control
15. Audit Logs
16. Reports and Monitoring

---

# 5. Dashboard Layout

## 5.1 Main Layout

Create a professional dashboard layout with:

* Sidebar navigation
* Top navigation bar
* User profile menu
* Tenant indicator
* Current role indicator
* Breadcrumbs
* Page title
* Main content area
* Global page loader
* Toast notifications
* Confirmation modals
* Error boundaries

## 5.2 Sidebar Menu

Sidebar should include:

* Dashboard
* Events
* Registrations
* Ticketing
* Orders
* Attendees
* Credentials
* Wallet Passes
* Scanning
* Kiosks
* Badges
* Manual Desk
* ACS
* Reports
* Audit Logs
* Settings

## 5.3 RBAC-Based Menu Visibility

Menu items must appear based on user permissions.

Examples:

* User without `events.create` should not see create event button.
* User without `credentials.revoke` should not see revoke credential action.
* User without `badge.print` should not see print badge button.
* User without `acs.manage` should not see ACS configuration pages.
* User without `audit.view` should not see audit logs.

---

# 6. Phase 0 Frontend — Foundation, Auth, RBAC, Tenants, Audit

## 6.1 Goal

Expose the foundation work in the frontend.

## 6.2 Required Pages

### 6.2.1 Login Page

Route:

`/login`

Requirements:

* Email field
* Password field
* Submit button
* Form loader on login
* Error message on invalid login
* Redirect to dashboard after success

### 6.2.2 Main Dashboard

Route:

`/dashboard`

Show overview cards:

* Total events
* Published events
* Total attendees
* Total orders
* Total credentials issued
* Today’s check-ins
* Active kiosks
* Active gates
* Failed scans
* Recent audit events

### 6.2.3 User Profile Page

Route:

`/profile`

Show:

* Name
* Email
* Phone
* Role
* Tenant
* Last login date if available

### 6.2.4 Users Management Page

Route:

`/admin/users`

Features:

* List users
* Search users
* Filter by status
* Filter by role
* Create user if permission exists
* Edit user if permission exists
* Activate/deactivate user

### 6.2.5 Roles and Permissions Page

Route:

`/admin/roles`

Features:

* List roles
* View permissions
* Create role
* Edit role
* Assign permissions
* Prevent editing protected system roles unless allowed

### 6.2.6 Tenant Settings Page

Route:

`/admin/tenant-settings`

Show:

* Tenant name
* Tenant slug
* Branding config
* Default language
* Default timezone
* Data residency if available
* Retention settings if available

### 6.2.7 Audit Logs Page

Route:

`/admin/audit-logs`

Features:

* List audit logs
* Filter by actor
* Filter by action
* Filter by entity type
* Filter by date
* View before/after details
* Export later if API exists

---

# 7. Phase 1 Frontend — Events, Registration, Ticketing, Orders, Credentials

## 7.1 Goal

Allow organizer/admin users to manage events from the dashboard.

## 7.2 Events List Page

Route:

`/events`

Features:

* List all events for current tenant
* Search by name
* Filter by status
* Filter by tier
* Filter by date
* Create event button
* View event details
* Edit event
* Publish event if allowed

Table columns:

* Event name
* Tier
* Status
* Start date
* End date
* Capacity
* Registrations count
* Actions

## 7.3 Create Event Page

Route:

`/events/create`

Fields:

* Event name
* Slug
* Description
* Tier
* Start date
* End date
* Timezone
* Location name
* Location address
* Capacity
* Branding options if available

Actions:

* Save as draft
* Save and continue

## 7.4 Event Details Page

Route:

`/events/:eventId`

Show tabs:

1. Overview
2. Registration Form
3. Ticket Types
4. Price Tiers
5. Orders
6. Attendees
7. Credentials
8. Wallet Passes
9. Scanning
10. Badges
11. ACS
12. Audit Logs

## 7.5 Registration Form Builder Page

Route:

`/events/:eventId/registration-form`

Features:

* View form fields
* Add field
* Edit field
* Delete field
* Reorder fields
* Mark field required/optional
* Configure validation rules
* Preview registration form

Supported field types:

* Text
* Email
* Phone
* Number
* Date
* Dropdown
* Multi-select
* Checkbox
* Hidden field
* Consent field

## 7.6 Public Registration Preview

Route:

`/events/:eventId/registration-preview`

Features:

* Show event branding
* Show registration fields
* Show ticket selection if event has tickets
* Submit test registration if allowed
* Show confirmation state

## 7.7 Ticket Types Page

Route:

`/events/:eventId/ticket-types`

Features:

* List ticket types
* Create ticket type
* Edit ticket type
* Disable ticket type
* View capacity and remaining quantity

Fields:

* Name
* Description
* Price
* Currency
* Quantity
* Sale start date
* Sale end date
* Attendee type
* Ticket tier
* Status

## 7.8 Price Tiers Page

Route:

`/events/:eventId/price-tiers`

Features:

* List price tiers
* Create price tier
* Edit price tier
* Delete/disable price tier
* Show active tier based on date

Fields:

* Name
* Ticket type
* Price
* Currency
* Starts at
* Ends at
* Capacity threshold
* Priority
* Status

## 7.9 Orders Page

Route:

`/events/:eventId/orders`

Features:

* List orders
* Search by buyer name/email
* Filter by payment status
* Filter by order status
* View order details
* Show payment reference
* Show linked attendees

Columns:

* Order number
* Buyer name
* Buyer email
* Total
* Currency
* Payment status
* Order status
* Created at
* Actions

## 7.10 Order Details Page

Route:

`/events/:eventId/orders/:orderId`

Show:

* Buyer details
* Payment details
* Order items
* Linked attendees
* Credential status
* Audit history

## 7.11 Attendees Page

Route:

`/events/:eventId/attendees`

Features:

* List attendees
* Search by name/email/phone
* Filter by ticket type
* Filter by registration status
* Filter by check-in status
* Filter by credential status
* View attendee details

Columns:

* Name
* Email
* Phone
* Ticket type
* Registration status
* Check-in status
* Credential status
* Actions

## 7.12 Attendee Details Page

Route:

`/events/:eventId/attendees/:attendeeId`

Show:

* Personal info
* Custom registration fields
* Ticket type
* Order details
* Credential details
* Wallet pass status
* Check-in history
* Badge print history
* Audit logs

Actions:

* Reissue credential
* Revoke credential
* Print badge if allowed
* Check in manually if allowed

## 7.13 Credentials Page

Route:

`/events/:eventId/credentials`

Features:

* List credentials
* Filter by status
* Search by attendee
* View QR payload status
* Revoke credential
* Reissue credential
* View scan history

Credential statuses:

* pending
* active
* revoked
* expired
* reissued

## 7.14 Credential Details Page

Route:

`/events/:eventId/credentials/:credentialId`

Show:

* Credential code
* Attendee
* Ticket type
* Status
* Issued at
* Expires at
* Revoked at
* Revoke reason
* Reissued from credential
* Scan history
* Audit logs

Actions:

* Revoke
* Reissue

---

# 8. Phase 2 Frontend — Wallet Passes, QR Scanning, Check-In

## 8.1 Goal

Expose wallet pass generation and QR scanning/check-in flows.

## 8.2 Wallet Passes Page

Route:

`/events/:eventId/wallet-passes`

Features:

* List wallet passes
* Filter by provider
* Filter by status
* Search by attendee
* View pass details
* Trigger update if API exists
* Revoke pass if allowed

Providers:

* Apple
* Google

Statuses:

* created
* active
* updated
* revoked
* expired
* failed

## 8.3 Wallet Pass Details Page

Route:

`/events/:eventId/wallet-passes/:passId`

Show:

* Provider
* Pass serial number
* Attendee
* Credential
* Status
* Last pushed at
* Pass URL if available
* Audit logs

## 8.4 Scanner Page

Route:

`/events/:eventId/scanner`

Purpose:

Allow staff to scan QR credentials from browser.

Requirements:

* Camera scanner if available
* Manual QR/code input fallback
* Large accepted/rejected result screen
* Clear reason for rejection
* Sound/vibration optional if supported
* Prevent duplicate scan submissions
* Form/button loader while validating scan

Accepted result should show:

* Attendee name
* Ticket type
* Status
* Entry allowed message

Rejected result should show:

* Reason
* Suggested next action

## 8.5 Check-In Dashboard

Route:

`/events/:eventId/check-in-dashboard`

Show:

* Total registered attendees
* Total checked in
* Not checked in
* Accepted scans
* Rejected scans
* Duplicate scans
* Revoked credential attempts
* Check-ins per hour
* Latest scan events

## 8.6 Scan Events Page

Route:

`/events/:eventId/scan-events`

Features:

* List scan events
* Filter by result
* Filter by scanner type
* Filter by gate/zone if available
* Filter by offline mode
* Search by attendee
* View scan details

Scan results:

* accepted
* rejected
* duplicate
* revoked
* expired
* unauthorized_zone
* anti_passback_rejected
* manual_override

---

# 9. Phase 3 Frontend — Kiosk, Badge Printing, Manual Desk

## 9.1 Goal

Create the frontend surfaces needed for kiosk management, badge printing, and manual desk operations.

## 9.2 Kiosks Page

Route:

`/events/:eventId/kiosks`

Features:

* List kiosks
* Register kiosk
* View kiosk status
* View last seen time
* View printer status
* View scanner status
* View app version
* Activate/deactivate kiosk

Columns:

* Device name
* Location
* Status
* Last seen
* Printer status
* Scanner status
* App version
* Actions

## 9.3 Kiosk Details Page

Route:

`/events/:eventId/kiosks/:kioskId`

Show:

* Device info
* Device code
* Location
* Status
* Last seen
* Printer health
* Scanner health
* Recent check-ins
* Recent print jobs
* Audit logs

## 9.4 Kiosk Mode Page

Route:

`/kiosk/:deviceCode`

Purpose:

Self-service check-in and badge printing screen.

Features:

* Event branding
* QR scan
* Name/email/phone lookup fallback
* Attendee confirmation
* Print badge button
* Clear success screen
* Clear error screen
* Reset after completion
* Fullscreen-friendly design

## 9.5 Badge Templates Page

Route:

`/events/:eventId/badge-templates`

Features:

* List badge templates
* Create template
* Edit template
* Preview template
* Activate/deactivate template

Fields:

* Template name
* Attendee type
* Paper size
* Printer type
* Layout JSON editor or visual builder placeholder
* Status

## 9.6 Badge Print Jobs Page

Route:

`/events/:eventId/badge-print-jobs`

Features:

* List print jobs
* Filter by status
* Search by attendee
* View reprint reason
* View printed by
* View printed at

Print job statuses:

* pending
* printing
* printed
* failed
* cancelled

## 9.7 Manual Desk Page

Route:

`/events/:eventId/manual-desk`

Purpose:

Allow staff to handle attendee exceptions.

Features:

* Search attendee by name/email/phone
* View attendee status
* Check in attendee
* Print badge
* Reprint badge
* Register walk-up attendee if allowed
* Manual override if allowed
* Require reason for override/reprint
* Create audit logs through backend

## 9.8 Walk-Up Registration Form

Route:

Inside manual desk page or:

`/events/:eventId/manual-desk/walk-up`

Fields:

* First name
* Last name
* Email
* Phone
* Company
* Job title
* Ticket type
* Notes

Actions:

* Register attendee
* Issue credential if allowed
* Print badge if allowed

---

# 10. Phase 4 Frontend — ACS and Access Control

## 10.1 Goal

Expose ACS configuration, zone/lane management, authorization rules, access logs, and gate health.

## 10.2 ACS Overview Page

Route:

`/events/:eventId/acs`

Show:

* Total zones
* Total lanes
* Active gates
* Offline gates
* Accepted entries
* Rejected entries
* Anti-passback rejections
* Emergency events
* Latest gate events

## 10.3 ACS Zones Page

Route:

`/events/:eventId/acs/zones`

Features:

* List zones
* Create zone
* Edit zone
* Disable zone

Fields:

* Zone name
* External ACS zone ID
* Status

## 10.4 ACS Lanes Page

Route:

`/events/:eventId/acs/lanes`

Features:

* List lanes
* Create lane
* Edit lane
* Disable lane
* Assign lane to zone

Fields:

* Lane name
* Zone
* External ACS lane ID
* Gate type
* Status

## 10.5 ACS Authorization Rules Page

Route:

`/events/:eventId/acs/rules`

Features:

* List access rules
* Create rule
* Edit rule
* Disable rule

Fields:

* Ticket type
* Attendee type
* Zone
* Lane
* Access direction
* Valid from
* Valid until
* Status

## 10.6 ACS Access Logs Page

Route:

`/events/:eventId/acs/access-logs`

Features:

* List entry/exit logs
* Filter by result
* Filter by zone
* Filter by lane
* Filter by direction
* Search by attendee/credential
* View rejection reason

## 10.7 Gate Health Page

Route:

`/events/:eventId/acs/gate-health`

Show:

* Gate/lane name
* Status
* Last heartbeat
* Last event
* Error state if any
* Emergency mode status

## 10.8 Emergency Egress UI

If backend supports it, add:

* Emergency event list
* Emergency status indicator
* Manual emergency test action only for authorized users
* Confirmation modal before triggering any emergency-related action

---

# 11. Shared Frontend Components

Build reusable components:

## 11.1 Layout Components

* DashboardLayout
* Sidebar
* Topbar
* Breadcrumbs
* PageHeader
* PageContent
* ProtectedRoute
* PermissionGate

## 11.2 Data Components

* DataTable
* FiltersBar
* SearchInput
* StatusBadge
* EmptyState
* ErrorState
* Pagination
* DetailsCard
* Timeline
* AuditTimeline

## 11.3 Form Components

* TextInput
* SelectInput
* DateTimeInput
* CheckboxInput
* TextareaInput
* SubmitButtonWithLoader
* FormSection
* FormActions
* ConfirmModal
* ReasonModal

## 11.4 Loading Components

* GlobalRouteLoader
* PageSkeleton
* TableSkeleton
* CardSkeleton
* FormSubmitLoader
* ButtonSpinner

## 11.5 Status Badge Standards

Use consistent badges for:

* Event status
* Order status
* Payment status
* Credential status
* Wallet status
* Scan result
* Kiosk status
* Badge print status
* ACS lane status
* Application status if later added

---

# 12. Frontend State Management

Use the existing frontend state approach.

If no state approach exists, use a simple structure:

* API client layer
* React hooks per module
* Local component state for forms
* Query/cache library if already installed
* Avoid over-engineering

Recommended hooks:

* useEvents
* useEvent
* useTicketTypes
* useOrders
* useAttendees
* useCredentials
* useWalletPasses
* useScanEvents
* useKiosks
* useBadgeTemplates
* useBadgePrintJobs
* useACSZones
* useACSLanes
* useACSRules
* useAuditLogs

---

# 13. API Integration Requirements

Create a clean frontend API layer.

Suggested structure:

```text
/src/api
  authApi.ts
  usersApi.ts
  rolesApi.ts
  tenantsApi.ts
  eventsApi.ts
  registrationFormsApi.ts
  ticketTypesApi.ts
  priceTiersApi.ts
  ordersApi.ts
  attendeesApi.ts
  credentialsApi.ts
  walletPassesApi.ts
  scansApi.ts
  kiosksApi.ts
  badgesApi.ts
  acsApi.ts
  auditLogsApi.ts
```

Every API function should handle:

* Loading state
* Error response
* Validation errors
* Unauthorized response
* Forbidden response
* Tenant scope headers if required
* Request cancellation if needed

---

# 14. Frontend Routes

Required routes:

```text
/login
/dashboard
/profile

/admin/users
/admin/roles
/admin/tenant-settings
/admin/audit-logs

/events
/events/create
/events/:eventId
/events/:eventId/edit
/events/:eventId/registration-form
/events/:eventId/registration-preview
/events/:eventId/ticket-types
/events/:eventId/price-tiers
/events/:eventId/orders
/events/:eventId/orders/:orderId
/events/:eventId/attendees
/events/:eventId/attendees/:attendeeId
/events/:eventId/credentials
/events/:eventId/credentials/:credentialId

/events/:eventId/wallet-passes
/events/:eventId/wallet-passes/:passId
/events/:eventId/scanner
/events/:eventId/check-in-dashboard
/events/:eventId/scan-events

/events/:eventId/kiosks
/events/:eventId/kiosks/:kioskId
/kiosk/:deviceCode

/events/:eventId/badge-templates
/events/:eventId/badge-print-jobs
/events/:eventId/manual-desk

/events/:eventId/acs
/events/:eventId/acs/zones
/events/:eventId/acs/lanes
/events/:eventId/acs/rules
/events/:eventId/acs/access-logs
/events/:eventId/acs/gate-health
```

---

# 15. UX Requirements

The dashboard must be:

* Clean
* Professional
* Fast
* Easy to scan
* Built for daily event operations
* Suitable for enterprise clients
* Ready for Arabic and English
* Ready for RTL
* Responsive for desktop and tablet
* Mobile-friendly for scanner/manual desk where needed

## 15.1 Empty States

Every list page must have an empty state.

Examples:

* No events created yet
* No attendees registered yet
* No scan events yet
* No kiosks registered yet
* No ACS zones configured yet

## 15.2 Error States

Every API page must show clear error messages.

Examples:

* Failed to load events
* You do not have permission
* This credential has been revoked
* This scan was rejected
* Ticket inventory is sold out

## 15.3 Confirmation Modals

Use confirmation modals for sensitive actions:

* Publish event
* Cancel event
* Revoke credential
* Reissue credential
* Print badge
* Reprint badge
* Manual override
* Disable ACS rule
* Trigger emergency-related ACS action if supported

## 15.4 Required Reason Modals

Require reason field for:

* Credential revocation
* Badge reprint
* Manual override
* Emergency action if supported

---

# 16. Loaders

## 16.1 Global Route Loader

Show a global loader when navigating between major dashboard routes.

Requirements:

* Matches Zonetec branding
* Smooth fade in/out
* Does not feel heavy
* Should not block already loaded data unnecessarily

## 16.2 Page Data Loaders

Use skeleton loaders for pages that fetch data.

Required skeletons:

* Dashboard cards
* Event list table
* Order list table
* Attendee list table
* Credential list table
* Wallet pass list table
* Kiosk list table
* ACS list table
* Details page cards

## 16.3 Form Submit Loaders

Every form submit should show loader only on the form or button.

Apply to:

* Login
* Create event
* Edit event
* Save registration form
* Create ticket type
* Create price tier
* Revoke credential
* Reissue credential
* Scan QR
* Register kiosk
* Create badge template
* Print badge
* Reprint badge
* Manual desk check-in
* Create ACS zone
* Create ACS lane
* Create ACS rule

Rules:

* Disable submit button during request.
* Prevent duplicate clicks.
* Show success toast after success.
* Show validation errors under fields.
* Keep user on same page unless flow requires redirect.

---

# 17. Permissions Matrix

Implement frontend permission checks using backend-provided permissions.

Minimum permission checks:

```text
events.create
events.update
events.publish
events.cancel

registration.manage

ticketing.manage

orders.manage

attendees.view
attendees.manage

credentials.issue
credentials.revoke
credentials.reissue

wallet.manage

scan.perform
checkin.perform

badge.print
badge.reprint

kiosk.manage

acs.manage

reports.view

audit.view

users.manage
roles.manage
tenant.manage
```

Frontend must not rely only on hidden buttons for security.

Backend must still enforce permissions.

Frontend permission checks are for UX only.

---

# 18. Reports and Monitoring Frontend

Create basic operational reporting pages based on available APIs.

## 18.1 Event Report Page

Route:

`/events/:eventId/reports`

Show:

* Registration count
* Paid orders count
* Payment success rate
* Credentials issued
* Credentials revoked
* Wallet pass adoption
* Check-in count
* First scan success rate
* Badge print count
* ACS accepted entries
* ACS rejected entries

## 18.2 Dashboard Charts

If charts are already supported in frontend, add:

* Registrations over time
* Check-ins over time
* Ticket sales by type
* Scan results breakdown
* ACS entries by zone

If chart library does not exist, use simple cards and tables first.

---

# 19. Frontend Acceptance Criteria

This frontend phase is complete when:

1. User can log in from React frontend.
2. Dashboard layout exists with sidebar and topbar.
3. Navigation is permission-aware.
4. Main dashboard shows overview metrics.
5. Admin can view users, roles, tenant settings, and audit logs if APIs exist.
6. Organizer/admin can list events.
7. Organizer/admin can create event.
8. Organizer/admin can view event details.
9. Organizer/admin can manage registration form.
10. Organizer/admin can manage ticket types.
11. Organizer/admin can manage price tiers.
12. Organizer/admin can view orders.
13. Organizer/admin can view attendees.
14. Organizer/admin can view attendee details.
15. Organizer/admin can view credentials.
16. Authorized user can revoke credential.
17. Authorized user can reissue credential.
18. Wallet pass list and details pages exist.
19. Scanner page can validate QR/code using backend API.
20. Check-in dashboard shows event check-in status.
21. Scan events page exists.
22. Kiosk list and details pages exist.
23. Kiosk mode page exists.
24. Badge templates page exists.
25. Badge print jobs page exists.
26. Manual desk page can search and handle attendee actions.
27. ACS overview page exists.
28. ACS zones page exists.
29. ACS lanes page exists.
30. ACS authorization rules page exists.
31. ACS access logs page exists.
32. Gate health page exists.
33. All pages have loading states.
34. All forms have submit loaders.
35. All list pages have empty states.
36. All API errors are shown clearly.
37. Sensitive actions use confirmation modals.
38. Required reason actions use reason modals.
39. UI is responsive.
40. UI is Arabic/English ready.
41. RTL support is prepared.
42. Frontend routes are protected.
43. RBAC is respected in UI.
44. Documentation is updated.
45. Manual QA confirms every completed backend phase is visible from the frontend.

---

# 20. Testing Requirements

## 20.1 Frontend Unit Tests

Test:

* Sidebar renders correct links based on permissions
* StatusBadge renders correct statuses
* Form validation works
* Submit button loader appears
* Empty states render
* Error states render
* PermissionGate hides unauthorized actions

## 20.2 Integration Tests

Test:

* Login flow
* Events list loading
* Create event flow
* Ticket type creation flow
* Attendee details loading
* Credential revoke flow
* Credential reissue flow
* QR scan flow
* Manual desk search flow
* ACS rule creation flow

## 20.3 E2E Tests

Create E2E tests for:

1. Admin logs in and views dashboard.
2. Organizer creates event.
3. Organizer configures registration form.
4. Organizer creates ticket type.
5. Organizer views orders and attendees.
6. Organizer revokes credential.
7. Organizer reissues credential.
8. Staff scans valid QR.
9. Staff scans revoked QR and sees rejection.
10. Badge staff prints badge.
11. Manual desk searches attendee.
12. ACS operator creates zone, lane, and rule.

---

# 21. Implementation Tasks

## 21.1 Frontend Foundation

* Create dashboard layout.
* Create protected routes.
* Create API client.
* Create auth state.
* Create permission system.
* Create global loader.
* Create toast system.
* Create confirmation modal system.
* Create reusable table component.
* Create reusable form components.
* Create reusable status badges.
* Create empty/error/loading components.

## 21.2 Phase 0 UI

* Build login page.
* Build dashboard overview.
* Build profile page.
* Build users page.
* Build roles page.
* Build tenant settings page.
* Build audit logs page.

## 21.3 Phase 1 UI

* Build events list page.
* Build create event page.
* Build event details page with tabs.
* Build registration form builder.
* Build registration preview page.
* Build ticket types page.
* Build price tiers page.
* Build orders page.
* Build order details page.
* Build attendees page.
* Build attendee details page.
* Build credentials page.
* Build credential details page.
* Add revoke/reissue modals.

## 21.4 Phase 2 UI

* Build wallet passes page.
* Build wallet pass details page.
* Build scanner page.
* Build check-in dashboard.
* Build scan events page.

## 21.5 Phase 3 UI

* Build kiosks page.
* Build kiosk details page.
* Build kiosk mode page.
* Build badge templates page.
* Build badge print jobs page.
* Build manual desk page.
* Build walk-up registration form if backend supports it.

## 21.6 Phase 4 UI

* Build ACS overview page.
* Build ACS zones page.
* Build ACS lanes page.
* Build ACS authorization rules page.
* Build ACS access logs page.
* Build gate health page.
* Build emergency event UI if backend supports it.

## 21.7 QA and Hardening

* Test all routes.
* Test all permissions.
* Test all forms.
* Test all loaders.
* Test all sensitive actions.
* Test all empty states.
* Test all error states.
* Test responsive layout.
* Test RTL readiness.
* Fix UI inconsistencies.
* Update documentation.

---

# 22. Suggested Folder Structure

```text
/src
  /api
  /app
  /components
    /layout
    /forms
    /tables
    /feedback
    /status
    /modals
  /features
    /auth
    /dashboard
    /users
    /roles
    /tenants
    /events
    /registration
    /ticketing
    /orders
    /attendees
    /credentials
    /wallet
    /scanning
    /kiosks
    /badges
    /manual-desk
    /acs
    /audit
    /reports
  /hooks
  /routes
  /types
  /utils
```

---

# 23. Spec-Kit Instruction

Use this phase as a frontend implementation phase only.

Generate:

* spec.md
* plan.md
* frontend-routes.md
* component-map.md
* api-integration-map.md
* tasks.md
* test-plan.md

Do not create new backend business modules unless an endpoint is missing and required for the completed phases to be visible.

If an API endpoint is missing, document it under:

`Missing Backend API Requirements`

Then continue building the UI with mock-safe adapters or placeholders.

---

# 24. Definition Of Done

This frontend phase is done only when:

* All completed backend phases from Phase 0 to Phase 4 are visible in React.
* The dashboard can be used to operate basic event flows.
* The organizer can create and manage events.
* The organizer can view registration, ticketing, orders, attendees, and credentials.
* Staff can scan QR credentials.
* Staff can manage kiosk/check-in/badge/manual desk flows.
* ACS operator can manage zones, lanes, rules, and logs.
* Platform admin can view foundation/admin pages.
* All pages have proper loading, empty, and error states.
* All forms have submit loaders.
* RBAC controls frontend visibility.
* Routes are protected.
* UI is responsive.
* Arabic and RTL support are prepared.
* Tests are written for critical flows.
* Documentation is updated.
