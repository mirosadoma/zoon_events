# Phase 1 Payment Operations

Production Moyasar activation requires evidence for merchant approval, separate
test and live secret references, the approved account reference, and a
dedicated webhook route token and signing-secret reference. Secret values are
loaded at runtime and must never be pasted into configuration, logs, tickets,
audit metadata, or command output.

Before enabling live mode, retain dated evidence for a successful test payment,
an authoritative fetch, full and partial refunds, duplicate callback handling,
amount/currency/account/live-mode mismatch denial, and an outage exercise.
Readiness must be green with network access explicitly enabled.

During an outage, leave orders pending and attempts `pending` or `unknown`.
Never retry an unknown create request. Run
`php artisan zonetec:payments:reconcile` to fetch authoritative state in bounded
batches. Disable the account if credentials are suspected compromised, rotate
the referenced secret, revalidate webhook signatures, and reconcile the full
affected window before re-enabling checkout.
