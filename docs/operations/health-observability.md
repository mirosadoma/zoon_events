# Health and observability

Owner: Platform Operations  
Last reviewed: 2026-07-03

`/api/v1/health/live` proves the process responds. `/health/ready` checks configuration,
database, queue, private storage, and audit key without exposing details. Authorized
platform health returns safe categories, status, duration, and reason codes. Telemetry
propagates correlation and trusted tenant IDs through a provider-neutral, redacting,
failure-bounded pipeline; it never replaces audit evidence.
