# Phase 9 — Public Landing Page, Frontend Control Center, Applications Workflow & UI Loaders

## 1. Phase Goal

Build a complete public-facing and admin-facing frontend layer for Zonetec that allows:

1. Visitors to understand the platform through a rich landing page.
2. Existing React dashboard users to visually access and monitor all completed product phases.
3. Customers to apply to become either:

   * Event Organizer
   * Venue Owner with venue assets/equipment
4. Platform Admins to review, approve, or reject these applications.
5. The system to send acceptance or rejection emails automatically.
6. The UI to include professional global page loaders and form-submit loaders consistent with Zonetec’s design and product importance.

This phase should improve product presentation, operational visibility, onboarding, and user experience without changing the core business flow of previous phases.

---

## 2. Scope

### 2.1 Public Landing Page

Create a rich marketing landing page for Zonetec.

The landing page must explain the product clearly and include multiple content sections.

Required sections:

1. Hero section

   * Strong headline
   * Short subtitle
   * Main CTA buttons
   * Example CTAs:

     * Request Demo
     * Become Organizer
     * List Your Venue
     * Contact Sales

2. Product overview

   * What Zonetec is
   * Who it is for
   * Why it exists

3. Event lifecycle section

   * Event creation
   * Registration
   * Ticketing
   * Payment
   * QR credential
   * Wallet pass
   * Check-in
   * Badge printing
   * Access control
   * Reporting

4. Main personas section

   * Event Organizer
   * Venue Owner
   * Attendee
   * On-site Staff
   * Security / ACS Operator
   * Platform Admin

5. Core features section

   * Multi-tenant event management
   * White-label registration pages
   * Ticketing and orders
   * QR credentials
   * Apple Wallet / Google Wallet passes
   * Kiosk check-in
   * Badge printing
   * Manual desk operations
   * ACS / gate access control
   * Identity verification
   * Venue infrastructure marketplace
   * Reporting and audit logs

6. Event tiers section

   * Corporate
   * Public
   * VIP
   * VVIP

7. SaaS and On-premise section

   * Explain that Zonetec supports SaaS and on-premise deployment.
   * On-premise is for government, enterprise, and high-security clients.

8. Security and compliance section

   * Tenant isolation
   * RBAC
   * Audit logs
   * Signed QR credentials
   * Data retention
   * Sensitive data handling

9. Venue marketplace section

   * Explain how venue owners can list gates, kiosks, printers, scanners, cameras, zones, and other assets.
   * Explain how organizers can request venue infrastructure.

10. CTA section

* Become Organizer
* Become Venue Owner
* Contact Sales

11. Footer

* Product links
* Contact links
* Legal links
* Social links if available

---

## 3. React Dashboard: Completed Phases Visibility

### 3.1 Goal

Add a frontend dashboard area that displays all implemented Zonetec phases/modules so the product owner and admin can visually see what has been completed and what is available.

### 3.2 Required Dashboard Page

Create a page called:

`/dashboard/platform-progress`

or

`/admin/platform-progress`

This page should display all product phases as cards or timeline items.

### 3.3 Phase Cards

Each phase card should include:

* Phase number
* Phase name
* Short description
* Completion status
* Related modules
* Available dashboard routes
* Main actions
* Last updated date if available

### 3.4 Example Phases To Display

6. Phase 5 — Identity Verification
7. Phase 6 — Venue Marketplace
8. Phase 7 — On-Premise, Enterprise, and Compliance
9. Phase 8 — Pilot, Hardening, Launch, and Scale
10. Phase 9 — Landing Page, Dashboard Visibility, Applications Workflow, and UI Loaders

### 3.5 Status Values

Supported phase statuses:

* not_started
* in_progress
* completed
* blocked
* needs_review

### 3.6 UX Requirements

The page should be clean, visual, and easy to scan.

Use:

* Cards
* Icons
* Progress indicators
* Badges
* Filters by status
* Search by phase/module name

### 3.7 Data Source

The frontend can initially use static configuration if no backend endpoint exists yet.

However, the architecture must allow replacing static config later with an API endpoint.

Recommended future endpoint:

`GET /api/v1/admin/platform-phases`

---

## 4. Customer Application Form

### 4.1 Goal

Create a form that allows a customer to request becoming either:

1. Event Organizer
2. Venue Owner

The request must go to the Platform Admin for review.

If approved, the user receives an acceptance email and is assigned the correct account type/role.

If rejected, the user receives a rejection email with the reason written by the admin.

---

## 5. Public Application Flow

### 5.1 Entry Points

Add CTA buttons on the landing page:

* Become an Organizer
* List Your Venue

Both can open either:

* One shared application form with request type selection
* Or two separate forms

Recommended approach:

Use one shared form with a request type field.

---

## 6. Application Form Fields

### 6.1 Common Fields

The application form must collect:

* Request type:

  * organizer
  * venue_owner
* Full name
* Email
* Phone
* Company / Organization name
* Country
* City
* Website or social link if available
* Description / message
* Expected usage
* Consent checkbox
* Preferred language

### 6.2 Organizer-Specific Fields

If request type is `organizer`, collect:

* Organizer type:

  * Company
  * Government entity
  * Agency
  * Individual organizer
  * Enterprise
* Expected number of events per year
* Typical event type:

  * Corporate
  * Public
  * VIP
  * VVIP
  * Exhibition
  * Conference
* Expected attendee range
* Need ticketing?
* Need payment?
* Need badge printing?
* Need access control?
* Need identity verification?

### 6.3 Venue Owner-Specific Fields

If request type is `venue_owner`, collect:

* Venue name
* Venue address
* Venue city
* Venue country
* Venue capacity
* Venue type:

  * Conference hall
  * Exhibition center
  * Hotel ballroom
  * Stadium
  * Government venue
  * Private venue
  * Other
* Available assets/equipment:

  * Turnstiles
  * Security gates
  * Cameras
  * Kiosks
  * Printers
  * Scanners
  * Access lanes
  * Access zones
* Asset notes
* Rental availability notes
* Pricing notes if available

---

## 7. Application Statuses

The application must support these statuses:

* pending
* approved
* rejected
* cancelled

Default status after submission:

`pending`

---

## 8. Admin Review Flow

### 8.1 Admin Applications Page

Create an admin page:

`/admin/applications`

The Platform Admin can view all customer applications.

### 8.2 Application List

The list must support:

* Search
* Filter by request type
* Filter by status
* Filter by date
* View application details

### 8.3 Application Details Page

Create a details page:

`/admin/applications/{id}`

Admin can see:

* Customer information
* Request type
* Organizer details if organizer request
* Venue details if venue owner request
* Submitted assets/equipment if venue owner
* Current status
* Review history
* Admin notes

### 8.4 Admin Actions

Admin can:

1. Approve application
2. Reject application
3. Add internal notes

---

## 9. Approval Rules

When admin approves an application:

### 9.1 If request type is Organizer

The system must:

1. Mark application as `approved`.
2. Create or update organizer profile.
3. Assign organizer role to the user.
4. Link the user to the correct tenant if applicable.
5. Send acceptance email.
6. Create audit log.

### 9.2 If request type is Venue Owner

The system must:

1. Mark application as `approved`.
2. Create or update venue owner profile.
3. Create initial venue profile if venue data is provided.
4. Create draft venue assets if equipment data is provided.
5. Assign venue owner role to the user.
6. Send acceptance email.
7. Create audit log.

---

## 10. Rejection Rules

When admin rejects an application:

1. Admin must write a rejection reason.
2. The system marks the application as `rejected`.
3. The rejection reason is stored.
4. A rejection email is sent to the customer.
5. The email must include the rejection reason.
6. Audit log must be created.

Admin cannot reject without a reason.

---

## 11. Email Notifications

### 11.1 Application Submitted Email

Send confirmation email to the customer after submission.

Email purpose:

* Confirm the request has been received.
* Tell the user the team will review it.

### 11.2 Admin Notification Email

Send email or internal notification to Platform Admin when a new application is submitted.

### 11.3 Acceptance Email

If approved, send a welcome email.

Acceptance email should include:

* Greeting
* Confirmation that the request was approved
* Approved role:

  * Event Organizer
  * Venue Owner
* Short welcome message
* Next steps
* Login/dashboard link if available

### 11.4 Rejection Email

If rejected, send email with:

* Greeting
* Confirmation that the request was reviewed
* Rejection reason written by admin
* Optional contact/support note

---

## 12. UI Loaders

### 12.1 Global Page Loader

Create a professional global loader for page transitions and route changes.

The loader must:

* Match Zonetec design system
* Be visible when navigating between pages
* Feel premium and enterprise-grade
* Not block the UI longer than necessary
* Work with React routing

Loader design suggestions:

* Zonetec logo animation
* Subtle pulse animation
* Circular progress around brand icon
* Minimal dark/light mode support
* Smooth fade-in and fade-out

### 12.2 Page Data Loader

For pages that load data from APIs, show:

* Skeleton cards
* Skeleton tables
* Skeleton forms
* Loading placeholders

Avoid showing empty pages while data is loading.

### 12.3 Form Submit Loader

Add a form-level loader when user clicks Save, Submit, Approve, Reject, or Send.

Important rule:

The form submit loader should appear only on the form or button area, not as a full-page loader.

Examples:

* Save button becomes disabled and shows spinner.
* Form section shows loading overlay.
* Prevent duplicate submissions.
* Keep user on the same page.
* Show success or error message after response.

### 12.4 Required Form Loader Locations

Apply form submit loader to:

* Application form submission
* Admin approve application
* Admin reject application
* Event forms if already present
* Ticket type forms if already present
* Venue asset forms if already present
* Any save/update form in existing dashboard where possible

---

## 13. Backend Data Model

### 13.1 application_requests

Create a table called:

`application_requests`

Fields:

* id
* tenant_id nullable
* request_type enum:

  * organizer
  * venue_owner
* status enum:

  * pending
  * approved
  * rejected
  * cancelled
* full_name
* email
* phone
* company_name
* country
* city
* website_url nullable
* message nullable
* preferred_language
* expected_usage nullable
* organizer_details_json nullable
* venue_details_json nullable
* assets_json nullable
* consent_accepted boolean
* reviewed_by_user_id nullable
* reviewed_at nullable
* admin_notes nullable
* rejection_reason nullable
* created_at
* updated_at

### 13.2 organizer_profiles

If not already existing, create or reuse organizer profile table.

Required fields:

* id
* tenant_id
* user_id
* company_name
* organizer_type
* status
* source_application_request_id nullable
* created_at
* updated_at

### 13.3 venue_owner_profiles

If not already existing, create or reuse venue owner profile table.

Required fields:

* id
* tenant_id
* user_id
* company_name
* status
* source_application_request_id nullable
* created_at
* updated_at

### 13.4 venues

If venue owner request contains venue information, create initial venue as draft or pending setup.

Required fields:

* id
* tenant_id
* venue_owner_id
* name
* address
* city
* country
* capacity nullable
* status
* source_application_request_id nullable
* created_at
* updated_at

### 13.5 venue_assets

If venue owner request contains equipment/assets, create draft venue assets.

Required fields:

* id
* tenant_id
* venue_id
* asset_type
* name
* description nullable
* capabilities_json nullable
* status
* source_application_request_id nullable
* created_at
* updated_at

---

## 14. API Contracts

### 14.1 Public APIs

Create application request:

`POST /api/v1/application-requests`

Request body:

```json
{
  "request_type": "organizer",
  "full_name": "string",
  "email": "string",
  "phone": "string",
  "company_name": "string",
  "country": "string",
  "city": "string",
  "website_url": "string",
  "message": "string",
  "preferred_language": "en",
  "expected_usage": "string",
  "organizer_details": {},
  "venue_details": {},
  "assets": [],
  "consent_accepted": true
}
```

Response:

```json
{
  "data": {
    "id": "uuid",
    "status": "pending"
  },
  "message": "Application request submitted successfully."
}
```

### 14.2 Admin APIs

List applications:

`GET /api/v1/admin/application-requests`

View application:

`GET /api/v1/admin/application-requests/{id}`

Approve application:

`POST /api/v1/admin/application-requests/{id}/approve`

Reject application:

`POST /api/v1/admin/application-requests/{id}/reject`

Reject request body:

```json
{
  "rejection_reason": "string"
}
```

Update admin notes:

`PATCH /api/v1/admin/application-requests/{id}/notes`

Request body:

```json
{
  "admin_notes": "string"
}
```

### 14.3 Platform Phases API

Optional API for completed phases dashboard:

`GET /api/v1/admin/platform-phases`

Response:

```json
{
  "data": [
    {
      "phase_number": 0,
      "name": "Foundation and Constitution",
      "status": "completed",
      "description": "Core foundation, RBAC, tenant isolation, audit foundation.",
      "modules": ["Tenant", "RBAC", "Audit", "Auth"],
      "routes": []
    }
  ]
}
```

If backend endpoint is not ready, use frontend static config temporarily.

---

## 15. RBAC Rules

### 15.1 Public User

Can:

* View landing page
* Submit application request

Cannot:

* View application list
* Approve or reject applications

### 15.2 Platform Admin

Can:

* View all application requests
* Approve applications
* Reject applications
* Add admin notes
* View platform progress page
* View audit logs

### 15.3 Organizer

Can:

* Access organizer dashboard after approval
* Manage events according to assigned permissions

### 15.4 Venue Owner

Can:

* Access venue owner dashboard after approval
* Manage venue and assets according to assigned permissions

---

## 16. Audit Logging

Create audit logs for:

* application_request.created
* application_request.approved
* application_request.rejected
* organizer_profile.created
* venue_owner_profile.created
* venue.created_from_application
* venue_asset.created_from_application
* email.application_submitted.sent
* email.application_approved.sent
* email.application_rejected.sent

---

## 17. Validation Rules

### 17.1 Application Form

Required:

* request_type
* full_name
* email
* phone
* company_name
* country
* city
* consent_accepted

If request_type = organizer:

* organizer_type is required
* expected event usage should be provided

If request_type = venue_owner:

* venue name is required
* venue city is required
* at least one venue asset or asset note should be provided if available

### 17.2 Rejection

Admin cannot reject an application without writing a rejection reason.

### 17.3 Duplicate Requests

Prevent obvious duplicate pending requests by same email and same request type.

If duplicate pending request exists, return clear validation error.

---

## 18. Frontend Pages

### 18.1 Public

* `/`

  * Landing page

* `/apply`

  * Shared application form

* `/apply/organizer`

  * Optional shortcut with organizer selected

* `/apply/venue-owner`

  * Optional shortcut with venue owner selected

* `/application-submitted`

  * Success page after submission

### 18.2 Admin

* `/admin/platform-progress`

  * Completed phases and modules view

* `/admin/application-requests`

  * List application requests

* `/admin/application-requests/:id`

  * Application details, approve/reject actions

### 18.3 Dashboard Redirects

After approval:

* Organizer should be directed to organizer dashboard.
* Venue owner should be directed to venue owner dashboard.

---

## 19. UX Requirements

The frontend must be polished, clean, and enterprise-ready.

General UX rules:

* Responsive design
* Arabic and English ready
* RTL support ready
* Clear CTAs
* Clear success states
* Clear error messages
* No empty blank screens
* Use skeleton loaders during loading
* Disable submit buttons during API calls
* Show toast notifications for success/error
* Use confirmation modal before approval/rejection
* Rejection modal must include required reason field

---

## 20. Non-Functional Requirements

### 20.1 Performance

Landing page should load quickly.

Use:

* Lazy loading for heavy sections
* Optimized images
* Component splitting if needed

### 20.2 Accessibility

Forms must support:

* Labels
* Keyboard navigation
* Error messages
* Focus states

### 20.3 Security

* Validate all public form input.
* Rate limit application submission.
* Sanitize text fields.
* Protect admin APIs with authentication and RBAC.
* Do not expose admin notes to public users.

### 20.4 Reliability

* Application submission should not be lost if email sending fails.
* Email sending should happen through queue/job if available.
* Approval/rejection should be transaction-safe.

---

## 21. Acceptance Criteria

This phase is complete when:

1. Landing page exists and contains rich Zonetec content.
2. Landing page has CTA buttons for organizer and venue owner applications.
3. Application form supports organizer and venue owner request types.
4. Form validates required fields.
5. Application request is saved with pending status.
6. Customer receives submission confirmation email.
7. Admin can view all pending applications.
8. Admin can open application details.
9. Admin can approve organizer application.
10. Approved organizer gets organizer profile/role.
11. Admin can approve venue owner application.
12. Approved venue owner gets venue owner profile/role.
13. Venue owner application can create draft venue and draft assets if data exists.
14. Acceptance email is sent after approval.
15. Admin can reject application only with reason.
16. Rejection email is sent with admin-written reason.
17. All approval/rejection actions create audit logs.
18. Platform progress page shows all completed phases/modules.
19. Global route/page loader works during navigation.
20. Page data skeleton loaders appear while loading API data.
21. Form submit loader appears only on the form/button during save/submit actions.
22. Duplicate submissions are prevented.
23. RBAC prevents non-admin users from reviewing applications.
24. UI is responsive and ready for Arabic/English.

---

## 22. Testing Requirements

### 22.1 Backend Tests

Write tests for:

* Submit organizer application
* Submit venue owner application
* Validation errors
* Duplicate pending application prevention
* Admin list applications
* Admin view application
* Admin approve organizer
* Admin approve venue owner
* Admin reject with reason
* Admin reject without reason fails
* Email queued on submit
* Email queued on approval
* Email queued on rejection
* Audit log created on submit
* Audit log created on approval
* Audit log created on rejection
* RBAC blocks unauthorized review

### 22.2 Frontend Tests

Write tests for:

* Landing page renders main sections
* CTA buttons navigate correctly
* Application form changes fields based on request type
* Required field validation
* Submit button shows loader
* Duplicate click is prevented during submit
* Success page appears after submission
* Admin applications list renders
* Admin filters work
* Approve action shows loader
* Reject action requires reason
* Platform progress cards render

---

## 23. Implementation Tasks

### 23.1 Landing Page

* Create landing page route.
* Build hero section.
* Build product overview section.
* Build lifecycle section.
* Build personas section.
* Build features section.
* Build event tiers section.
* Build SaaS/on-premise section.
* Build security/compliance section.
* Build venue marketplace section.
* Build CTA section.
* Build footer.
* Add responsive design.
* Add Arabic/English-ready content structure.

### 23.2 Application Form

* Create shared application form.
* Add request type selector.
* Add common fields.
* Add organizer conditional fields.
* Add venue owner conditional fields.
* Add assets/equipment selector.
* Add validation.
* Add submit loader.
* Add success state.
* Connect to backend API.

### 23.3 Backend Application Workflow

* Create migration for application_requests.
* Create model.
* Create validation request.
* Create public controller for submission.
* Create admin controller for review.
* Create approve service.
* Create reject service.
* Create role assignment logic.
* Create organizer profile creation logic.
* Create venue owner profile creation logic.
* Create venue/asset draft creation logic.
* Create audit logs.
* Create email jobs/templates.

### 23.4 Admin Application Review

* Create applications list page.
* Add filters/search.
* Create application details page.
* Add approve action.
* Add reject modal with required reason.
* Add admin notes.
* Add loading states.
* Add success/error toasts.

### 23.5 Platform Progress Page

* Create platform progress route.
* Create phases config.
* Build phase cards.
* Add status badges.
* Add filters/search.
* Allow later API replacement.

### 23.6 UI Loaders

* Create global route loader component.
* Create page skeleton loader components.
* Create table skeleton.
* Create card skeleton.
* Create form submit loader.
* Apply loader to application form.
* Apply loader to admin approve/reject actions.
* Apply loader to existing forms where possible.

---

## 24. Technical Stack Instruction

Use the existing project stack.

For this project, assume:

* Backend: Laravel
* Database: MySQL
* Frontend: React
* Styling: use the existing design system / CSS framework in the project
* Queues: Laravel queues if available
* Email: Laravel mail system
* Do not introduce Docker unless explicitly requested

---

## 25. Definition Of Done

This phase is done only when:

* All pages are implemented.
* All APIs are implemented.
* Application approval/rejection workflow works end to end.
* Emails are sent or queued correctly.
* Roles/profiles are created correctly after approval.
* Rejection reason is required and sent to the customer.
* Global loader works.
* Form loaders work.
* Dashboard progress page shows all completed phases.
* RBAC is enforced.
* Audit logs are created.
* Automated tests pass.
* UI is responsive.
* Documentation is updated.
