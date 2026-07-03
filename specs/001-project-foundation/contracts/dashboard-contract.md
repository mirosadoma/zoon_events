# Phase 0 Foundation Admin Dashboard Contract

## Purpose and Scope

The dashboard is a tightly scoped administrative surface for Phase 0. It does not create a second set of business rules: every page and mutation uses the same application actions, tenant context, policies, validation, audit writer, and versioned API capabilities as other clients.

Included navigation:

- Platform: Overview, Tenants, Users, Platform Roles, Platform Audit, Health & Telemetry, Feature Flags, Configuration Reference.
- Tenant: Overview, Memberships, Roles & Permissions, Tenant Audit, Feature Flags, Configuration.

Explicitly absent: event registration, events, tickets/orders/payments, attendees, credentials/QR, wallet passes, scanning/check-in, kiosks, badges, ACS, identity verification, venue marketplace, and production integrations.

## Technology and Visual Direction

- Laravel 13 React starter architecture
- React 19 functional components and TypeScript
- Inertia 3
- Tailwind CSS 4 and shadcn/ui primitives
- Vite build
- Project-owned design tokens and layouts; no licensed dashboard-template source/assets or dependency

Visual system:

- Collapsible vertical navigation with compact top bar
- Optional inset/floating content shell at desktop widths
- High-density but readable tables, filter bars, status chips, cards, dialogs, sheets, and command/search affordance
- Light, dark, and system themes
- Restrained motion that respects reduced-motion preferences
- CSS logical properties and mirrored navigation for Arabic RTL
- WCAG 2.2 AA color contrast, keyboard operation, visible focus, semantic headings/landmarks, and accessible validation summaries

## Authentication and Context

- Browser routes use Fortify-backed session authentication with public registration, password reset, email verification, MFA, teams, and social authentication disabled.
- Session login uses the same active-user checks, rate limits, audit outcomes, and password policy as API token login.
- Platform pages require explicit platform permissions.
- Tenant pages require trusted tenant selection and active membership; route parameters or client state alone never establish tenant context.
- Tenant switch re-resolves context server-side and clears page/client caches.
- Logout clears authentication, tenant context, and client-side sensitive state.

## Application Boundary

- Inertia page controllers invoke public application query/action contracts.
- Eloquent models, query builders, and raw persistence arrays are never passed to React pages.
- Explicit view models/resources allow-list every prop.
- All dashboard capabilities remain available through `/api/v1` and follow `openapi.yaml`.
- Mutations use the same validators, policies, transactions, idempotency rules, domain events, and audit writer as their API equivalents.
- Server authorization is authoritative; hiding a control is usability only and never the security boundary.

## Route and Page Matrix

| Page | Scope | Required permission | Data/actions |
|------|-------|---------------------|--------------|
| Login | Public | None | Human password login only |
| Platform Overview | Platform | Any active platform assignment | Safe summary links and health state |
| Tenants | Platform | `platform.tenant.view/manage` | List, create, inspect, lifecycle changes |
| Users | Platform | `platform.user.view/manage` | List, provision, inspect, lifecycle changes |
| Platform Roles | Platform | `platform.role.view/manage/assign` | Role/permission/assignment management |
| Platform Audit | Platform | `platform.audit.view/export/verify` | Search, export, integrity status |
| Health & Telemetry | Platform | `operations.health.view` | Safe health categories and telemetry status |
| Platform Feature Flags | Platform | `platform.feature_flag.view/manage` | Definitions/defaults/lifecycle |
| Configuration Reference | Platform | `platform.configuration.view` | Schema versions and deployment applicability |
| Tenant Overview | Tenant | `tenant.view` | Tenant-safe summary and navigation |
| Memberships | Tenant | `membership.view/manage` | Membership list/lifecycle |
| Roles & Permissions | Tenant | `role.view/manage/assign` | Roles, grants, assignments |
| Tenant Audit | Tenant | `audit.view/export/verify` | Search, export, integrity status |
| Tenant Feature Flags | Tenant | `feature_flag.view/manage` | Effective values and overrides |
| Tenant Configuration | Tenant | `configuration.view` | Read-only schema and validated stored-value inspection |

No route may render an unauthorized page and no Inertia response may include props outside the authorized scope.

## Required Page States

Every data page defines and tests:

- Initial loading/skeleton
- Empty state with a permitted next action or explanation
- Validation errors with field and summary associations
- Forbidden state without sensitive navigation/data
- Not-found state indistinguishable from cross-tenant target
- Conflict state for stale/lifecycle/idempotency conflicts
- Dependency unavailable/degraded state
- Success confirmation
- Long-running queued export state

Destructive or high-impact changes require an explicit confirmation dialog showing the target, effect, and reason field. The last-tenant-administrator and security-control protections cannot be bypassed by the dashboard.

## Localization and Direction

- English and Arabic have equivalent labels, help text, validation, error, empty, and confirmation content.
- Direction is selected by locale and applied at the document root.
- Sidebars, breadcrumbs, icons with direction meaning, table alignment, drawers, and keyboard order mirror correctly in RTL.
- Identifiers, email addresses, machine keys, and code samples retain appropriate bidirectional isolation.
- Dates/times render in user/tenant locale and time zone while API values remain UTC.
- No persisted business value is translated in-place; display resources use locale keys.

## Theme and Branding Boundary

- The foundation console uses Zonetec platform design tokens.
- Theme tokens are CSS custom properties grouped by color, typography, spacing, radius, shadow, and motion.
- Tenant branding configuration is inspected as validated data only; Phase 0 does not dynamically skin the admin console per tenant.
- Future branded product experiences must map approved tenant tokens through a separate feature specification.

## Security and Privacy

- CSRF protection applies to browser mutations; session cookies use secure, HTTP-only, same-site settings outside local development.
- Content Security Policy and security headers are documented and tested.
- Pages never render secrets, token plaintext, password fields after submission, raw IP addresses, full user-agent strings, or raw sensitive audit payloads.
- Audit detail displays only allow-listed sanitized change summaries and privacy-preserving fingerprints.
- Search/filter input is validated server-side and output is escaped.
- Inertia history/page-state encryption is enabled where supported and sensitive props are minimized.
- Download authorization is re-evaluated when an audit export is requested.

## Acceptance Tests

1. Each page allows the named permission and denies a user lacking it.
2. Cross-tenant IDs appear in zero HTML/Inertia props and return the same not-found state as random IDs.
3. Tenant switching cannot reuse prior tenant page data.
4. Arabic RTL and English LTR pass layout, keyboard, focus, and content-equivalence checks.
5. Light/dark/system themes maintain WCAG 2.2 AA contrast.
6. Mobile, tablet, and desktop widths preserve navigation and core task completion.
7. Loading, empty, validation, forbidden, not-found, conflict, degraded, and success states are exercised.
8. Dashboard and API mutations produce equivalent validation, authorization, state, events, and audit evidence.
9. Navigation contains no excluded product feature or placeholder.
10. Production frontend build has no runtime CDN or external-network dependency.
