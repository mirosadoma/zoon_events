# Phase 3 Data Model

**Feature**: Kiosk Check-In, Badge Printing, and Manual Desk
**Database**: MySQL 8.4, shared schema, tenant-first ownership

## Conventions

Phase 3 reuses every Phase 0/1/2 convention: 26-character ULIDs, non-null
`tenant_id` on every record, non-null `event_id` on every event-owned record,
composite foreign keys enforcing same-tenant/same-event ownership, UTC
microsecond timestamps, database-checked status enums, `created_at`-only
immutable evidence rows where applicable, and `created_at`/`updated_at` on
mutable rows.

## Entity Relationships

```text
Tenant
  └─ Event
      ├─ Kiosk (0..n)
      │   └─ KioskSession (0..n; current + historical pairings)
      ├─ BadgeTemplate (0..n; exactly 0 or 1 `active` at a time)
      ├─ BadgePrintJob (0..n)
      │   ├─ references Attendee, Credential, BadgeTemplate
      │   └─ original_print_job_id -> BadgePrintJob (self, reprints only)
      ├─ Attendee (Phase 1, extended with `origin`)
      │   └─ Credential (Phase 1, referenced only)
      ├─ EventCheckInSetting (Phase 2, extended with walk-up toggle)
      └─ ScanEvent (Phase 2, extended: `scanner_type` now also produced as
          `kiosk` / `manual_desk`, referencing Kiosk where applicable)
```

## Kiosk

Tenant- and event-owned record of one registered kiosk device.

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `device_name` | Human-readable label set at registration |
| `device_code` | Opaque, unique per `(tenant_id, event_id)`; printed/displayed for support use, never a secret |
| `location_label` | Optional free-text placement description |
| `status` | `registered`, `online`, `offline`, `degraded`, `retired` |
| `printer_status` | `unknown`, `ready`, `error`, `disconnected`; last value relayed by the kiosk |
| `last_heartbeat_at` | Last successful heartbeat time; drives `online`/`offline` derivation |
| `confirmation_required` | Boolean; whether a new session must pass the optional PIN/one-time-code step (FR-002) before scanning |
| `created_at`, `updated_at` | Standard |
| `retired_at` | Set when an organizer/ops actor decommissions the kiosk |

Indexes: `(tenant_id, event_id, status)`, unique `(tenant_id, event_id,
device_code)`.

A retired kiosk's active session(s) are invalidated immediately; it never
accepts further scans, lookups, or print requests.

## Kiosk Session

Bookkeeping for the paired device-session secret a kiosk uses to
authenticate (`research.md` Decision 2). Never stores the raw secret.

| Field | Rules |
|---|---|
| `id`, `tenant_id` | Required |
| `kiosk_id` | Same tenant; the kiosk this session authenticates |
| `secret_hash` | Hashed device-session secret; the raw value is shown to the pairing operator exactly once and never stored or logged |
| `confirmed_at` | Set once the optional PIN/one-time-code step succeeds, when `Kiosk.confirmation_required` is true |
| `expires_at` | Bounded lifetime; session must be re-paired or re-confirmed after expiry |
| `revoked_at` | Set on manual revocation, kiosk retirement, or replacement pairing |
| `created_at` | Standard |

Index: `(tenant_id, kiosk_id, revoked_at)` for fast "current valid session"
lookup. At most one non-revoked, non-expired session exists per kiosk at a
time; pairing a new session revokes the prior one.

## Badge Template

Tenant- and event-owned, organizer-configured print layout (`research.md`
Decision 5).

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `name` | Organizer-facing label |
| `layout` | JSON document validated against the fixed field allowlist (`attendee_name`, `company`, `job_title`, `qr`, `ticket_type`, `tier`, `zone`, `sponsor_logo_ref`, `organizer_logo_ref`, `color_code`) plus static position/size metadata; rejected on any unknown field reference |
| `paper_size` | e.g. `a6`, `4x6in`, `label_62mm`; validated against a supported-values list |
| `printer_type` | Target printer adapter category (e.g. `thermal`, `laser`, `fake`) |
| `status` | `draft`, `active`, `inactive` |
| `created_at`, `updated_at` | Standard |

Constraint: at most one `active` `BadgeTemplate` per `(tenant_id,
event_id)`. Activating one template automatically transitions the
previously active template (if any) to `inactive` in the same transaction.

Lifecycle:

```text
draft -> active -> inactive       (organizer activates a replacement)
draft -> inactive                 (organizer discards without activating)
active -> inactive                (organizer deactivates directly)
```

A `BadgePrintJob` always renders from the specific `badge_template_id`
recorded at job-creation time; deactivating or editing a template after a
job is created never rewrites already-created jobs (FR-011's "at the time
the job is created").

## Badge Print Job

Tenant- and event-scoped record of one print attempt (`research.md`
Decision 7).

| Field | Rules |
|---|---|
| `id`, `tenant_id`, `event_id` | Required |
| `attendee_id` | Same scope; the attendee this badge is for |
| `credential_id` | Same scope; the credential whose QR is rendered on the badge |
| `badge_template_id` | Same scope; the template rendered at creation time |
| `kiosk_id` | Nullable; set when the job originated at a kiosk |
| `printed_by_user_id` | Nullable; set when the job originated at the manual desk (or a staff-assisted kiosk reprint) |
| `status` | `queued`, `printed`, `failed` |
| `failure_reason` | Safe reason category when `status = failed` (e.g. `printer_unavailable`, `printer_error`) |
| `is_reprint` | Boolean |
| `reprint_reason` | Required and non-empty when `is_reprint = true`; null otherwise |
| `original_print_job_id` | Same scope; required when `is_reprint = true`, referencing the immediately preceding job for the same attendee; null for an initial print |
| `printed_at` | Set when `status` transitions to `printed` |
| `created_at`, `updated_at` | Standard |

Indexes: `(tenant_id, event_id, attendee_id, created_at)` for print history
and reprint-chain lookups, `(tenant_id, event_id, status)` for backlog
queries.

Constraints:

- Creation is rejected (no row persisted) unless the target event has an
  `active` `BadgeTemplate` at the moment of the request (FR-010).
- `printed_by_user_id` and `kiosk_id` are mutually informative, not
  mutually exclusive: a staff-assisted kiosk reprint may set both.
- `original_print_job_id` never forms a cycle; it always points strictly
  backward to a job created earlier for the same attendee.

## Event Check-In Setting (Phase 2 extension)

Adds to the existing per-event configuration row:

| Field | Rules |
|---|---|
| `walk_up_registration_enabled` | Boolean; default `false` |
| `walk_up_payment_method_enabled` | Boolean; default `false`; whether an on-site payment method is enabled for walk-ups at this event |
| `reprint_revokes_old_qr` | Boolean; default `false`; whether completing a reprint revokes and reissues the attendee's credential (`research.md` Decision 7) |
| `lookup_confirmation_required` | Boolean; default `false`; whether a name/email/phone lookup requires the one-time-code confirmation step before check-in (`research.md` Decision 4) |
| `kiosk_offline_threshold_seconds` | Integer; default per configuration, bounds how long since the last heartbeat before a kiosk is considered `offline` |

No existing Phase 2 field on this row changes meaning.

## Attendee (Phase 1/2 extension)

Adds to the existing attendee record:

| Field | Rules |
|---|---|
| `origin` | `standard`, `walk_up`; default `standard`; set once at creation, never changed afterward |

No other Phase 1/2 attendee field changes meaning.

## Scan Event (Phase 2 extension, no schema change)

Phase 2's migration already reserves `kiosk` and `manual_desk` as valid
`scanner_type` check-constraint values (see
`specs/003-wallet-passes-scanning/data-model.md`). Phase 3 begins producing
them: `scanner_type = 'kiosk'` scans set `scanner_id` to the authenticated
kiosk's `id`; `scanner_type = 'manual_desk'` scans set `scanner_id` to the
authenticated staff user's id. No migration is required for this activation.

## Credential (Phase 1, referenced not redefined)

Unchanged. A reprint with `reprint_revokes_old_qr` enabled calls the
existing Phase 1 revoke-and-reissue action; `BadgePrinting` never persists
or re-derives credential validity itself, matching Phase 2's precedent.

## Cross-Entity Invariants

1. A record may reference only records sharing its `tenant_id`; event-owned
   records must also share `event_id`. A kiosk, badge template, badge print
   job, or lookup naming a different tenant/event than its authenticated
   context resolves fails closed and returns a response indistinguishable
   from an unknown target (CR-001).
2. A kiosk never authenticates as a human actor; every kiosk-originated
   action's actor in audit records is the kiosk's own identifier, not a
   staff user id, unless a staff user is also present for an assisted
   action (e.g. a staff-assisted kiosk reprint).
3. At most one `active` `BadgeTemplate` exists per event; a `BadgePrintJob`
   can never be created for an event without one.
4. A `BadgePrintJob` is immutable once created except for its `status`,
   `failure_reason`, and `printed_at` transition fields; corrections happen
   only by creating a new (reprint) job, never by editing history, matching
   `ScanEvent`'s append-only pattern from Phase 2.
5. `is_reprint = true` always carries a non-empty `reprint_reason` and a
   valid `original_print_job_id`; the inverse (`is_reprint = false` with
   either field set) never occurs.
6. Kiosk and manual desk check-in reuse the exact Phase 2 scan decision
   order (`specs/003-wallet-passes-scanning/contracts/scan-contract.md`); no
   new `ScanEvent.result` value is introduced by this phase.
7. Walk-up attendee creation and credential issuance reuse the exact Phase 1
   action; `Attendee.origin = 'walk_up'` is metadata only and never changes
   validation, signing, or revocation behavior.
8. Audit and telemetry for kiosk, badge, and walk-up actions receive
   identifiers, classifications, and safe reason codes, never a raw kiosk
   session secret, printer driver payload, or attendee personal data beyond
   what Phase 1/2 already permit.
