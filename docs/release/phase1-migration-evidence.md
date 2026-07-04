# Phase 1 migration evidence

Run date: 2026-07-03  
Database profile: MySQL 8.4-compatible local test service (`zonetec_testing`)

The release gate exercises a clean `migrate:fresh --seed`, an immediate repeat
seed, `migrate:status`, rollback of the final legal-hold migration, and forward
re-application. The migration chain contains the Phase 0 foundation followed by
event/form, ticket/inventory, registration/order, attendee, credential,
notification, payment, paid-fulfillment, correction, and legal-hold changes.

Backup/restore validation uses only synthetic test data: create a native MySQL
logical backup after seeding, restore it to an empty disposable schema, compare
the migration table and per-table row counts, then destroy the disposable
schema. Secrets and personal production data are prohibited from evidence.

Observed result: clean creation, repeat seed, final rollback/forward checks, and
a native `mysqldump` restore into `zonetec_phase1_restore_test` all passed. The
source and restored migration ledgers both contained 19 rows; the disposable
schema and temporary dump were removed after comparison. Schema constraints and
indexes are additionally exercised by the full MySQL integration suite. Any
release environment must attach its own encrypted backup object/checksum and
restore drill record before promotion.
