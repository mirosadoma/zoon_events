# Queue and scheduler

Owner: Platform Operations  
Last reviewed: 2026-07-03

Run `php artisan queue:work --queue=default --tries=3` and `php artisan schedule:work`.
Database queues support offline/on-premise use. Tenant jobs serialize identifiers only,
re-resolve active persisted state, and clear context. Scheduler cleans expired exports and
verifies recent audit integrity. Failed jobs are inspected with `queue:failed` and retried
only after cause correction.
