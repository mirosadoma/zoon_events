# Printer Adapter Contract

## Purpose

Keep badge-template and print-job orchestration independent of specific
printer hardware/drivers. This contract governs the `BadgePrinting` module's
internal provider-neutral interface and the fake and hardware-backed
adapters that implement it, following the conventions of
`specs/001-project-foundation/contracts/adapter-contract.md`.

## Non-Negotiable Boundary

A printer adapter renders an already-resolved, already-authorized
`PrintPayload`. It MUST NOT read `BadgeTemplate`, `Attendee`, or
`Credential` data itself, decide whether a print is permitted, or introduce
any second authorization or credential-validity check. `BadgePrinting`
resolves the template, enforces permissions/reasons, and builds the payload
before ever calling the adapter.

## Invocation Context

Every call carries trusted:

- tenant and event identifiers;
- badge print job identifier and correlation identifier;
- the target kiosk identifier (when print originates at a kiosk) or the
  desk operator's tenant session (when print originates at the manual
  desk);
- idempotency key;
- timeout budget.

Printer connection details (network address, driver credentials, pairing
tokens) are resolved inside infrastructure from a `secret_reference` or
kiosk-local configuration. They never enter domain requests, logs, audit
metadata, exceptions, job payloads, or API responses.

## Operations

### Print

Input: `PrintPayload` — fully rendered badge content (already-resolved text
values and logo/QR image references per the active `BadgeTemplate`'s
allowlisted fields), target `paper_size`, target `printer_type`,
idempotency key.

Preconditions: the payload MUST already reflect only allowlisted fields
(`research.md` Decision 5); the adapter receives no template logic to
interpret.

Result:

- stable status (`printed`, `failed`);
- safe reason category on failure (see Stable Error Categories);
- provider-assigned print-confirmation reference where the underlying
  printer protocol supplies one (opaque; never itself personal data).

### Health

Input: kiosk/printer connection reference.

Result: stable printer status (`ready`, `error`, `disconnected`, `unknown`)
and, where the driver exposes it, a safe reason category (`out_of_paper`,
`jammed`, `offline`, `cover_open`). This is the value a kiosk relays on its
heartbeat (`kiosk-contract.md`); the server never polls kiosk-local hardware
directly.

## Stable Error Categories

Reuses the Phase 0 adapter categories
(`specs/001-project-foundation/contracts/adapter-contract.md`) plus:

| Category | Meaning | Default retry |
|---|---|---|
| `printer_unavailable` | Printer unreachable/disconnected at call time | Retry with backoff; surfaced as `queued` remaining, not `failed`, until retry budget is exhausted |
| `printer_error` | Printer reported a hardware fault (jam, out of paper, cover open) | Never automatically; requires operator intervention, then a new print/reprint attempt |
| `payload_rejected` | Payload failed adapter-side validation (unsupported paper size/printer type combination) | Never without a corrected payload |

## Tenant Isolation and Data Handling

- The payload includes only fields already allowlisted in the active
  `BadgeTemplate` (`data-model.md` §Badge Template); no national identifier,
  biometric, or payment data ever reaches this contract.
- Printer connection secrets/tokens are treated as sensitive and excluded
  from logs, metrics, audit metadata, and error messages.
- A print or health call scoped to one tenant/event/kiosk never returns or
  leaks another tenant's or kiosk's printer state.

## Contract Test Matrix

Every printer adapter implementation must pass:

1. Print succeeds and returns `printed` for a well-formed payload.
2. Print of a payload referencing an unsupported paper size/printer type
   combination is rejected with `payload_rejected` before any hardware
   call.
3. Printer unreachable at call time reports `printer_unavailable` without
   throwing an unhandled exception, leaving the owning `BadgePrintJob`
   retryable rather than permanently `failed`.
4. A hardware fault reported by the driver maps to `printer_error` with a
   safe reason category, never a raw driver error string.
5. Health reporting returns `ready`/`error`/`disconnected`/`unknown`
   consistently with the last simulated/real hardware state.
6. Repeated `print` calls with the same idempotency key against an
   already-printed job do not produce a second physical print.
7. No connection secret, driver payload, or raw error string appears in any
   log, audit record, or API response produced during the above.
