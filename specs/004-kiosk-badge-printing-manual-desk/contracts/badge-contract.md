# Badge Template and Print/Reprint Contract

## Purpose

Define the no-code badge template lifecycle and the print/reprint decision
order, governing the `BadgePrinting` module's public contract consumed by
the organizer template designer UI, the manual desk, and the `Kiosk` module
(`kiosk-contract.md` §Badge Print Trigger).

## Badge Template Lifecycle

1. An authorized organizer (`badge.template.manage`) creates a
   `BadgeTemplate` in `draft` status with a `layout` validated against the
   fixed field allowlist (`data-model.md` §Badge Template); any reference
   to a field outside the allowlist is rejected at save time with a
   field-level validation error, never silently dropped.
2. Activating a `draft`/`inactive` template transitions any currently
   `active` template for the same event to `inactive` and the target to
   `active`, in one transaction.
3. Deactivating the `active` template leaves the event with zero active
   templates; subsequent print attempts are rejected per §Print Decision
   Order until a template is activated again.
4. Editing a template never rewrites `badge_template_id` on
   already-created `BadgePrintJob` rows (`data-model.md` invariant 3).

## Print Decision Order

1. Resolve the event's currently `active` `BadgeTemplate`; if none exists,
   reject with `badge_template_not_active` before creating any
   `BadgePrintJob` row (FR-010, SC-004).
2. Resolve the attendee and their credential in the caller's authenticated
   tenant/event scope; a cross-tenant/cross-event or unknown reference is
   rejected identically to an unknown target.
3. Render the active template's allowlisted fields against the resolved
   attendee/credential/event data into a `PrintPayload`
   (`printer-adapter.md`); no field outside the template's configuration is
   included (FR-011).
4. Create one `BadgePrintJob` row (`status = 'queued'`) in the same
   transaction as its audit record.
5. After commit, invoke the printer adapter's `print` operation
   (`printer-adapter.md` §Print). A successful call transitions the job to
   `printed`; an adapter failure transitions it to `failed` with the
   adapter's safe reason category and leaves it retryable through a new
   print/reprint request, never silently retried in a way that could
   double-print without an idempotency key match.

## Reprint Decision Order

1. Requires the distinct `badge.reprint` permission (never satisfied by
   `badge.print` alone) — FR-013.
2. Requires a non-empty `reprint_reason` — FR-014; a request without one is
   rejected before any job is created.
3. Resolves the immediately preceding `BadgePrintJob` for the attendee (the
   "original") to link via `original_print_job_id`; a reprint requested for
   an attendee with no prior print job is rejected
   (`badge_no_prior_print_job`).
4. If the event's `EventCheckInSetting.reprint_revokes_old_qr` is enabled,
   calls the existing Phase 1 credential revoke-and-reissue action for the
   attendee's credential before rendering the new payload, so the new
   badge's QR reflects the reissued credential and the prior badge's QR no
   longer validates for entry (`research.md` Decision 7); this step is
   itself audited as a credential lifecycle event exactly as Phase 1/2
   already require.
5. Otherwise follows the same steps 3-5 as an initial print, with
   `is_reprint = true` and the resolved `reprint_reason` persisted on the
   new job.
6. Every reprint attempt — whether it results in a created job or is
   blocked by missing permission, missing reason, or missing prior job —
   produces an audit record capturing the actor, attendee, reason (when
   provided), and outcome (FR-013/FR-014, SC-005).

## Stable Error Categories

| Category | Meaning |
|---|---|
| `badge_template_not_active` | No active `BadgeTemplate` exists for the event |
| `badge_template_invalid_field` | Template layout references a field outside the fixed allowlist |
| `badge_reprint_reason_required` | Reprint attempted without a non-empty reason |
| `badge_reprint_not_permitted` | Actor lacks the distinct `badge.reprint` permission |
| `badge_no_prior_print_job` | Reprint requested for an attendee with no existing print job |
| `badge_print_not_permitted` | Actor (or kiosk session) lacks the `badge.print` permission/capability |

Printer-specific failure categories (`printer_unavailable`,
`printer_error`, `payload_rejected`) come from `printer-adapter.md`
unchanged; they never become badge-specific codes.

## Tenant Isolation and Data Handling

- `BadgeTemplate` and `BadgePrintJob` rows carry `tenant_id` and
  `event_id`; every read/write in this contract resolves scope from the
  authenticated organizer session or the kiosk/desk's authenticated
  context, never from a client-supplied identifier.
- Rendered badge content includes only the fields in
  `data-model.md`'s Badge Template allowlist; no national identifier,
  biometric, or payment data is ever included (CR-005).
- Reprint reasons and audit records never include the printer connection
  secret or driver payload.

## Contract Test Matrix

Every implementation must pass:

1. Creating a template with a layout field outside the allowlist is
   rejected at save time with `badge_template_invalid_field`.
2. Activating a template deactivates the event's previously active
   template in the same transaction; at most one `active` template ever
   exists per event.
3. A print attempt against an event with zero active templates is
   rejected with `badge_template_not_active` and creates no
   `BadgePrintJob` row.
4. A successful print renders only the active template's configured
   fields; a field not present in the template's layout never appears in
   the resulting payload.
5. A reprint without `badge.reprint` permission is rejected and creates no
   job, while an actor with only `badge.print` cannot reprint.
6. A reprint without a reason is rejected before any job is created.
7. A reprint for an attendee with no prior print job is rejected with
   `badge_no_prior_print_job`.
8. A reprint with `reprint_revokes_old_qr` enabled revokes and reissues
   the credential before rendering the new badge, and the prior
   credential/QR is rejected by a subsequent scan per
   `scan-contract.md`.
9. A reprint with `reprint_revokes_old_qr` disabled leaves the prior
   credential/QR fully valid for entry.
10. Every reprint attempt (successful or blocked) produces exactly one
    audit record capturing actor, attendee, reason (if any), and outcome.
11. Cross-tenant and cross-event template, attendee, and print-job
    references are rejected identically to an unknown target across every
    operation above.
