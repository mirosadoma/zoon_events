# Frontend UI Redesign Phase — Apply TailAdmin Design System to Zonetec Dashboard

## 1. Phase Goal

Redesign and rebuild the Zonetec React dashboard UI using TailAdmin as the visual and structural reference.

The goal is to apply a clean, professional, enterprise-grade dashboard design across the existing Zonetec frontend without changing the completed backend business logic.

This phase should focus on:

* Dashboard layout
* Sidebar navigation
* Topbar
* Tables
* Cards
* Forms
* Status badges
* Filters
* Pagination
* Dropdown menus
* Modals
* Loaders
* Empty states
* Error states
* Responsive behavior
* RTL and Arabic readiness

This phase must not introduce new product features or start a new backend phase.

---

## 2. Design Reference

Use TailAdmin dashboard style as the main UI reference.

Reference characteristics:

* Clean white/light dashboard interface
* Sidebar with grouped navigation sections
* Topbar with search, notifications, and user profile menu
* Card-based page sections
* Rounded tables with soft borders
* Status badges for active, pending, completed, failed, cancelled, and rejected states
* Action dropdowns inside tables
* Search and filter controls
* Pagination
* Skeleton loaders and spinners
* Professional enterprise admin layout

The implementation should follow the same design direction, spacing, component structure, and user experience pattern, but all content, labels, routes, and business logic must be adapted to Zonetec.

Do not blindly copy unrelated TailAdmin demo content such as e-commerce, stocks, campaigns, or sample users.

Replace all demo data with Zonetec domain data.

---

## 3. Important Rules

1. Use TailAdmin only as a UI/design reference or component foundation.
2. Keep Zonetec branding, naming, routes, permissions, and business logic.
3. Do not change backend business rules.
4. Do not rebuild completed backend phases.
5. Do not add Phase 5 features.
6. Do not introduce Docker.
7. Use the current project stack.
8. Use existing APIs where available.
9. If an API is missing, document it under `Missing Backend API Requirements`.
10. Keep all frontend pages protected by authentication and RBAC where needed.

---

## 4. Technical Stack

Use the existing project stack:

* Backend: Laravel
* Database: MySQL
* Frontend: React
* Styling: Tailwind CSS / existing styling system
* UI Reference: TailAdmin
* Authentication: existing Phase 0 authentication
* Authorization: existing RBAC
* Tenant isolation: existing Phase 0 tenant context
* API style: existing REST APIs
* Do not introduce Docker

---

## 5. Scope

This phase includes redesigning the existing frontend surfaces for completed Zonetec phases:

* Authentication screens
* Dashboard layout
* Events management
* Registration forms
* Ticketing
* Orders
* Attendees
* Credentials
* Wallet passes
* QR scanning
* Check-in dashboard
* Kiosks
* Badge printing
* Manual desk
* ACS access control
* Audit logs
* Reports
* Settings

---

# 6. TailAdmin Layout Adaptation

## 6.1 Main Dashboard Layout

Create a reusable dashboard layout inspired by TailAdmin.

Required layout components:

* Sidebar
* Topbar
* Main content area
* Breadcrumbs
* Page header
* User profile menu
* Notification dropdown
* Search input
* Theme-ready wrapper
* Responsive mobile sidebar
* Route transition loader

Suggested component names:

* `DashboardLayout`
* `Sidebar`
* `SidebarSection`
* `SidebarItem`
* `Topbar`
* `UserMenu`
* `NotificationDropdown`
* `SearchCommand`
* `Breadcrumbs`
* `PageHeader`
* `PageContent`

---

## 6.2 Sidebar Navigation

Build a TailAdmin-style grouped sidebar.

Sidebar sections:

### Main

* Dashboard
* Events
* Reports

### Event Operations

* Registration
* Ticketing
* Orders
* Attendees
* Credentials
* Wallet Passes
* Scanning
* Check-in

### On-site Operations

* Kiosks
* Badge Printing
* Manual Desk

### Access Control

* ACS Overview
* ACS Zones
* ACS Lanes
* ACS Rules
* Access Logs
* Gate Health

### Administration

* Users
* Roles
* Tenant Settings
* Audit Logs
* System Settings

Sidebar requirements:

* Active route highlighting
* Collapsible groups
* Icons for each item
* Permission-based visibility
* Mobile drawer support
* Compact/collapsed mode if needed

---

## 6.3 Topbar

Create a TailAdmin-style topbar.

Topbar must include:

* Sidebar toggle button
* Search input
* Notifications icon/dropdown
* User profile dropdown
* Current tenant indicator
* Current role indicator
* Optional language switcher
* Optional dark/light mode toggle if already supported

User menu items:

* Profile
* Account settings
* Support
* Sign out

---

# 7. Design System Components

Create reusable TailAdmin-style components for the whole project.

## 7.1 Cards

Components:

* `StatCard`
* `InfoCard`
* `ActionCard`
* `ProgressCard`
* `PhaseCard`
* `MetricCard`

Card requirements:

* Rounded corners
* Soft border
* Light shadow or subtle background
* Icon support
* Title
* Value
* Description
* Status indicator if needed

---

## 7.2 Tables

Create a reusable table system inspired by TailAdmin Basic Tables.

Components:

* `DataTable`
* `TableHeader`
* `TableToolbar`
* `TableFilters`
* `TableSearch`
* `TablePagination`
* `TableActionsDropdown`
* `TableEmptyState`
* `TableSkeleton`

Table requirements:

* Search
* Filter
* Sort if API supports it
* Pagination
* Status badges
* Action menu
* Responsive behavior
* Loading skeleton
* Empty state
* Error state

Use this table style across:

* Events
* Orders
* Attendees
* Credentials
* Wallet Passes
* Scan Events
* Kiosks
* Badge Print Jobs
* ACS Zones
* ACS Lanes
* ACS Rules
* ACS Logs
* Users
* Roles
* Audit Logs

---

## 7.3 Status Badges

Create one shared `StatusBadge` component.

Supported statuses:

### Event

* draft
* configured
* published
* registration_open
* registration_closed
* live
* completed
* cancelled
* archived

### Order

* draft
* pending_payment
* paid
* failed
* cancelled
* refunded
* partially_refunded

### Payment

* pending
* authorized
* captured
* failed
* refunded
* partially_refunded

### Credential

* pending
* active
* revoked
* expired
* reissued

### Wallet Pass

* created
* active
* updated
* revoked
* expired
* failed

### Scan Result

* accepted
* rejected
* duplicate
* revoked
* expired
* unauthorized_zone
* anti_passback_rejected
* manual_override

### Kiosk

* online
* offline
* inactive
* error

### Badge Print

* pending
* printing
* printed
* failed
* cancelled

### ACS

* active
* inactive
* offline
* error
* emergency

Badge requirements:

* Consistent color system
* Label mapping
* Arabic/English ready
* Small and medium sizes

---

## 7.4 Forms

Create TailAdmin-style form components.

Components:

* `TextInput`
* `EmailInput`
* `PhoneInput`
* `NumberInput`
* `Textarea`
* `Select`
* `MultiSelect`
* `Checkbox`
* `RadioGroup`
* `DateTimeInput`
* `FileInput`
* `FormSection`
* `FormActions`
* `SubmitButton`
* `SubmitButtonWithLoader`
* `ValidationError`
* `RequiredLabel`

Form requirements:

* Clear labels
* Required indicators
* Validation errors below fields
* Disabled state
* Loading state
* Submit loader only inside the form/button
* Prevent duplicate submissions
* Success/error toast after submit

---

## 7.5 Modals and Dropdowns

Create shared components:

* `ConfirmModal`
* `ReasonModal`
* `DetailsModal`
* `ActionDropdown`
* `FilterDropdown`
* `UserDropdown`
* `NotificationDropdown`

Use confirmation modal for:

* Publish event
* Cancel event
* Revoke credential
* Reissue credential
* Print badge
* Reprint badge
* Manual override
* Disable ACS rule
* Delete/disable any item

Use reason modal for:

* Credential revocation
* Badge reprint
* Manual override
* Application rejection if application workflow exists

---

# 8. Page Redesign Requirements

## 8.1 Login Page

Route:

`/login`

Apply TailAdmin-style authentication screen.

Requirements:

* Clean auth card
* Logo
* Email field
* Password field
* Remember me if supported
* Forgot password link if supported
* Submit loader
* Error messages
* Responsive design

---

## 8.2 Main Dashboard

Route:

`/dashboard`

Apply TailAdmin-style dashboard cards and tables.

Show:

* Total events
* Published events
* Live events
* Total attendees
* Total orders
* Total credentials issued
* Today’s check-ins
* Active kiosks
* Active ACS gates
* Failed scans

Sections:

* Overview metric cards
* Recent events table
* Recent orders table
* Latest scan events table
* Recent audit logs
* Operational alerts if available

---

## 8.3 Events Pages

Routes:

* `/events`
* `/events/create`
* `/events/:eventId`
* `/events/:eventId/edit`

Apply TailAdmin-style layout.

Events list table columns:

* Event name
* Tier
* Status
* Start date
* End date
* Capacity
* Registrations
* Actions

Event details should use tabs/cards:

* Overview
* Registration Form
* Ticket Types
* Price Tiers
* Orders
* Attendees
* Credentials
* Wallet Passes
* Scanning
* Kiosks
* Badges
* ACS
* Audit Logs

---

## 8.4 Registration Form Builder

Route:

`/events/:eventId/registration-form`

Redesign using card sections.

Requirements:

* Form fields list
* Add field button
* Drag/reorder if available
* Field type badge
* Required/optional badge
* Edit field modal
* Preview panel
* Save loader

---

## 8.5 Ticketing Pages

Routes:

* `/events/:eventId/ticket-types`
* `/events/:eventId/price-tiers`

Use TailAdmin-style tables and forms.

Ticket type table columns:

* Name
* Attendee type
* Tier
* Price
* Currency
* Capacity
* Remaining quantity
* Status
* Actions

Price tier table columns:

* Name
* Ticket type
* Price
* Starts at
* Ends at
* Capacity threshold
* Priority
* Status
* Actions

---

## 8.6 Orders Pages

Routes:

* `/events/:eventId/orders`
* `/events/:eventId/orders/:orderId`

Use TailAdmin-style order table.

Order table columns:

* Order ID
* Buyer
* Email
* Total
* Currency
* Payment status
* Order status
* Created at
* Actions

Order details should show:

* Buyer card
* Payment card
* Order items table
* Linked attendees
* Credential status
* Audit timeline

---

## 8.7 Attendees Pages

Routes:

* `/events/:eventId/attendees`
* `/events/:eventId/attendees/:attendeeId`

Attendee table columns:

* Name
* Email
* Phone
* Ticket type
* Registration status
* Identity status if available
* Check-in status
* Credential status
* Actions

Attendee details should show:

* Personal info
* Registration custom fields
* Order details
* Credential details
* Wallet pass status
* Scan history
* Badge print history
* Audit timeline

---

## 8.8 Credentials Pages

Routes:

* `/events/:eventId/credentials`
* `/events/:eventId/credentials/:credentialId`

Credential table columns:

* Credential code
* Attendee
* Ticket type
* Status
* Issued at
* Expires at
* Actions

Actions:

* View
* Revoke
* Reissue
* View scan history

Use reason modal for revoke.

---

## 8.9 Wallet Pass Pages

Routes:

* `/events/:eventId/wallet-passes`
* `/events/:eventId/wallet-passes/:passId`

Wallet table columns:

* Attendee
* Provider
* Credential
* Status
* Last pushed at
* Actions

Providers:

* Apple
* Google

Use status badges and action dropdowns.

---

## 8.10 Scanner and Check-in Pages

Routes:

* `/events/:eventId/scanner`
* `/events/:eventId/check-in-dashboard`
* `/events/:eventId/scan-events`

Scanner page requirements:

* Large centered scan card
* Camera scan area if supported
* Manual QR/code input fallback
* Big accepted/rejected result panel
* Clear reason for rejection
* Button/form loader while validating
* Recent scan results side panel

Check-in dashboard:

* Metric cards
* Check-in progress
* Accepted scans
* Rejected scans
* Duplicate scans
* Latest scan events table

Scan events table:

* Attendee
* Credential
* Scanner type
* Gate
* Zone
* Direction
* Result
* Reason
* Scanned at
* Actions

---

## 8.11 Kiosk Pages

Routes:

* `/events/:eventId/kiosks`
* `/events/:eventId/kiosks/:kioskId`
* `/kiosk/:deviceCode`

Kiosk list table columns:

* Device name
* Location
* Status
* Last seen
* Printer status
* Scanner status
* App version
* Actions

Kiosk mode must have a simpler fullscreen-friendly layout, but still use the same visual language.

---

## 8.12 Badge Printing Pages

Routes:

* `/events/:eventId/badge-templates`
* `/events/:eventId/badge-print-jobs`

Badge templates:

* List templates
* Create/edit template
* Preview template
* Status badge

Print jobs:

* Attendee
* Template
* Printer
* Status
* Printed by
* Printed at
* Reprint reason
* Actions

---

## 8.13 Manual Desk Page

Route:

`/events/:eventId/manual-desk`

Design as an operational workstation.

Sections:

* Search attendee card
* Attendee result card
* Status summary
* Quick actions
* Badge actions
* Manual override actions
* Recent manual actions timeline

Actions:

* Search
* Check in
* Print badge
* Reprint badge
* Register walk-up attendee if supported
* Manual override if allowed

---

## 8.14 ACS Pages

Routes:

* `/events/:eventId/acs`
* `/events/:eventId/acs/zones`
* `/events/:eventId/acs/lanes`
* `/events/:eventId/acs/rules`
* `/events/:eventId/acs/access-logs`
* `/events/:eventId/acs/gate-health`

ACS overview:

* Total zones
* Total lanes
* Active gates
* Offline gates
* Accepted entries
* Rejected entries
* Anti-passback rejections
* Emergency events

ACS zones table:

* Zone name
* External ACS zone ID
* Status
* Actions

ACS lanes table:

* Lane name
* Zone
* External ACS lane ID
* Gate type
* Status
* Actions

ACS rules table:

* Ticket type
* Attendee type
* Zone
* Lane
* Direction
* Valid from
* Valid until
* Status
* Actions

ACS logs table:

* Attendee
* Credential
* Zone
* Lane
* Direction
* Result
* Reason
* Time

Gate health:

* Gate/lane name
* Status
* Last heartbeat
* Last event
* Error state
* Emergency mode status

---

# 9. Loading, Empty, and Error States

## 9.1 Global Route Loader

Create a TailAdmin-style global route loader.

Requirements:

* Appears during route transitions
* Uses Zonetec logo or brand icon
* Smooth animation
* Works in desktop and mobile
* Does not cover the page unnecessarily after data is loaded

---

## 9.2 Skeleton Loaders

Create skeleton components for:

* Dashboard cards
* Tables
* Details cards
* Forms
* Tabs
* Timeline

Use skeletons instead of blank screens.

---

## 9.3 Form Submit Loaders

Every form/action button must have a submit loader.

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

* Disable submit button while submitting
* Show spinner inside button
* Prevent duplicate clicks
* Show success toast on success
* Show validation errors on failure

---

## 9.4 Empty States

Every list page must have a TailAdmin-style empty state.

Examples:

* No events created yet
* No attendees registered yet
* No credentials issued yet
* No scan events yet
* No kiosks registered yet
* No badge print jobs yet
* No ACS zones configured yet
* No audit logs yet

---

## 9.5 Error States

Every API page must have clear errors.

Examples:

* Failed to load data
* You do not have permission
* Record not found
* Server error
* Validation error
* Network error

---

# 10. Permission-Based UI

The UI must respect RBAC.

Use a shared `PermissionGate` component.

Example permissions:

* `events.create`
* `events.update`
* `events.publish`
* `events.cancel`
* `registration.manage`
* `ticketing.manage`
* `orders.manage`
* `attendees.view`
* `attendees.manage`
* `credentials.issue`
* `credentials.revoke`
* `credentials.reissue`
* `wallet.manage`
* `scan.perform`
* `checkin.perform`
* `badge.print`
* `badge.reprint`
* `kiosk.manage`
* `acs.manage`
* `reports.view`
* `audit.view`
* `users.manage`
* `roles.manage`
* `tenant.manage`

Frontend permission checks are for UX only.

Backend must still enforce permissions.

---

# 11. Responsive and RTL Requirements

The redesigned UI must be:

* Desktop-first for admin operations
* Tablet-friendly for event operations
* Mobile-friendly for scanner/manual desk where needed
* Arabic-ready
* English-ready
* RTL-ready

Requirements:

* Sidebar should become drawer on mobile
* Tables should be horizontally scrollable or converted to cards on small screens
* Forms should stack cleanly on small screens
* Text direction should be controlled by locale
* Icons and spacing should support RTL

---

# 12. API Integration Strategy

Create or refactor frontend API layer.

Suggested structure:

```text
/src/api
  authApi.ts
  dashboardApi.ts
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
  reportsApi.ts
```

Every API method must handle:

* Auth token
* Tenant context
* Loading state
* Error response
* Validation errors
* Unauthorized response
* Forbidden response

---

# 13. Suggested Frontend Folder Structure

```text
/src
  /api
  /assets
  /components
    /layout
    /navigation
    /tables
    /forms
    /cards
    /badges
    /modals
    /loaders
    /feedback
  /features
    /auth
    /dashboard
    /users
    /roles
    /tenant
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
  /styles
```

---

# 14. Implementation Tasks

## 14.1 TailAdmin Setup

* Review current React project structure.
* Review existing Tailwind configuration.
* Add or align TailAdmin-compatible Tailwind styles.
* Define Zonetec colors, radius, shadows, typography, spacing.
* Create shared layout components.
* Create shared card/table/form/modal/loader components.
* Remove unused demo content.
* Keep only reusable UI patterns.

---

## 14.2 Layout Migration

* Build dashboard layout.
* Build sidebar.
* Build topbar.
* Build breadcrumbs.
* Build user menu.
* Build notifications dropdown.
* Build responsive sidebar drawer.
* Add active route states.
* Add permission-based navigation.

---

## 14.3 Shared Components Migration

* Build reusable DataTable.
* Build reusable StatusBadge.
* Build reusable StatCard.
* Build reusable ConfirmModal.
* Build reusable ReasonModal.
* Build reusable SubmitButtonWithLoader.
* Build reusable GlobalRouteLoader.
* Build reusable Skeleton components.
* Build reusable EmptyState.
* Build reusable ErrorState.

---

## 14.4 Page Migration

Redesign these pages using the new TailAdmin-style components:

* Login
* Dashboard
* Profile
* Users
* Roles
* Tenant Settings
* Audit Logs
* Events List
* Create Event
* Event Details
* Registration Form Builder
* Ticket Types
* Price Tiers
* Orders
* Order Details
* Attendees
* Attendee Details
* Credentials
* Credential Details
* Wallet Passes
* Wallet Pass Details
* Scanner
* Check-in Dashboard
* Scan Events
* Kiosks
* Kiosk Details
* Kiosk Mode
* Badge Templates
* Badge Print Jobs
* Manual Desk
* ACS Overview
* ACS Zones
* ACS Lanes
* ACS Rules
* ACS Access Logs
* Gate Health
* Reports

---

## 14.5 QA and Cleanup

* Test all dashboard routes.
* Test sidebar navigation.
* Test mobile sidebar.
* Test all tables.
* Test filters and pagination.
* Test all submit loaders.
* Test all confirmation modals.
* Test all reason modals.
* Test empty states.
* Test error states.
* Test permission visibility.
* Test Arabic/RTL readiness.
* Remove unused demo data.
* Remove unused TailAdmin sample pages not needed for Zonetec.
* Update documentation.

---

# 15. Acceptance Criteria

This phase is complete when:

1. Zonetec dashboard visually follows TailAdmin-style layout.
2. Sidebar is implemented and grouped by Zonetec modules.
3. Topbar includes search, notifications, user menu, tenant, and role indicators.
4. All major pages use shared card/table/form components.
5. All list pages use the same DataTable style.
6. All statuses use the shared StatusBadge component.
7. All forms use consistent TailAdmin-style inputs.
8. All submit buttons have form-level loaders.
9. Route transitions have a global loader.
10. All pages have loading, empty, and error states.
11. Sensitive actions use confirmation modals.
12. Reason-required actions use reason modals.
13. Navigation is permission-aware.
14. Routes are protected.
15. UI is responsive.
16. UI is Arabic/English ready.
17. RTL readiness is implemented.
18. Demo TailAdmin content is removed.
19. Zonetec branding replaces TailAdmin demo branding.
20. Existing backend business logic remains unchanged.
21. No Phase 5 features are introduced.
22. Documentation is updated.

---

# 16. Testing Requirements

## 16.1 Component Tests

Test:

* Sidebar renders correct menu items
* PermissionGate hides unauthorized actions
* StatusBadge renders correct colors and labels
* DataTable renders rows, empty state, and loading state
* SubmitButtonWithLoader disables during submit
* ConfirmModal works
* ReasonModal requires reason

## 16.2 Page Tests

Test:

* Login page renders correctly
* Dashboard cards render
* Events table renders
* Orders table renders
* Attendees table renders
* Credentials table renders
* Wallet passes table renders
* Scanner page renders
* Kiosk table renders
* Badge print jobs table renders
* ACS pages render
* Audit logs page renders

## 16.3 Flow Tests

Test:

* User logs in
* User navigates dashboard
* User creates event
* User creates ticket type
* User views attendee
* User revokes credential
* User reissues credential
* User scans QR
* User opens manual desk
* User creates ACS zone
* User creates ACS lane
* User creates ACS rule

---

# 17. Missing Backend API Requirements

If any page cannot be connected to real data because an endpoint is missing, document it here instead of blocking frontend implementation.

For each missing API, document:

* Page name
* Required endpoint
* Required method
* Required request body
* Required response shape
* Priority
* Temporary frontend behavior

---

# 18. Spec-Kit Output Required

Generate the following files:

```text
/specs/010-tailadmin-ui-redesign
  spec.md
  plan.md
  frontend-routes.md
  component-map.md
  design-system.md
  api-integration-map.md
  rbac-ui-map.md
  tasks.md
  test-plan.md
  missing-api-requirements.md
```

---

# 19. Definition Of Done

This phase is done only when:

* TailAdmin-style dashboard layout is fully applied.
* Zonetec branding is applied.
* Completed Zonetec modules are visible and usable.
* All tables, cards, forms, modals, loaders, badges, and empty/error states are standardized.
* Navigation is permission-aware.
* All routes are protected.
* UI works on desktop, tablet, and mobile where required.
* RTL and Arabic readiness are prepared.
* All critical frontend tests pass.
* No demo data remains in production pages.
* No new backend phase is started.
* Documentation is updated.

- referance : https://demo.tailadmin.com
