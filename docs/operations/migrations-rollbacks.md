# Migrations and rollback

Owner: Database Engineering  
Last reviewed: 2026-07-03

Back up before schema changes. Rehearse `php artisan migrate --pretend`, then migrate one
release at a time. Destructive rollback is not the default recovery path for retained audit
evidence; prefer a forward repair. Fresh validation uses
`php artisan migrate:fresh --env=testing --seed`. Verify composite tenant keys, audit
integrity, idempotency uniqueness, and export privacy after upgrade or restore.
