# Configuration reference

Owner: Platform Operations  
Last reviewed: 2026-07-03

All environment reads live under `config/`. Required groups cover app identity/key/URL,
MySQL, database queues, private storage, SaaS/on-premise mode, supported locales, audit
key ID/ring and retention, telemetry exporters, integration network policy, and bootstrap
credentials. Run `php artisan zonetec:config:validate`; diagnostics name keys, never values.
Configuration cache requires restart after changes.
