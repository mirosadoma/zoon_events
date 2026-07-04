# Phase 1 data governance

Phase 1 treats attendee identity, contact details, registration answers, and
notification destinations as restricted personal data under the Saudi PDPL.
Order totals, payment references, credential lifecycle state, consent evidence,
and audit tombstones are confidential operational records. Raw card data and
provider secrets are prohibited from application storage.

Personal fields use tenant/event-scoped authenticated encryption; normalized
contact lookups use keyed blind indexes. Consent records preserve the accepted
terms/privacy versions and timestamp. Production retention is not a fixed
application default: the accountable data owner must approve a tenant policy
with purpose, legal basis, cutoff, residency, and disposal evidence.

Preview an approved cutoff with:

`php artisan zonetec:attendees:retention TENANT_ULID --before=2030-01-01T00:00:00Z`

Add `--execute` only after review and confirmation. The operation is tenant
scoped, excludes records with `legal_hold_at`, removes attendee/order/
notification contact values, and keeps non-identifying financial and audit
tombstones. Legal holds require a case reference and must be released only by
the designated privacy/legal owner.

SaaS and on-premise deployments must document the database, backup, log, queue,
and secret-store residency. Backups inherit the same classification and expire
under the approved schedule. A suspected breach triggers credential/secret
containment, preservation of audit integrity evidence, privacy-owner escalation,
impact assessment, and legally required notifications; do not anonymize records
placed on an investigation hold.
