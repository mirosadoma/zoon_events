# Phase 1 registration and ticketing operations

Ownership is deliberately split: event managers own schedule, branding, and
published form selection; ticketing managers own ticket types, price tiers, and
capacity; support staff may view orders and correct approved attendee fields;
privileged payment and credential operators own refunds and lifecycle changes.

An event moves draft → published → cancelled/archived. Publication requires a
valid bilingual schedule, active brand/domain, published immutable form
version, and active ticket. Editing a form creates a new version; existing
submissions retain their original schema and consent versions. Ticket monetary
snapshots never change after an order is created.

Inventory uses row-locked held/sold counters. Paid registration holds a unit
until authoritative capture, failure, or expiry; free registration sells it in
the aggregate transaction. Support must reconcile unknown payment outcomes
before releasing inventory. It is safe to retry with the original idempotency
key, expire due holds through the scheduled command, correct a misspelled name
through the audited attendee action, or revoke a compromised credential.

Unsafe operations include editing counters in SQL, trusting a browser payment
status, changing an order money snapshot, reusing an idempotency key for a
different payload, exposing raw attendee/QR/provider data in tickets, or
deleting financial/audit rows to resolve a support case. Escalate invariant,
scope, and reconciliation conflicts to the owning module operator.
