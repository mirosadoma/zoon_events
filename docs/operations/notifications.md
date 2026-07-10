# Notification operations

Phase 1 confirmation delivery is asynchronous: registration commits the order,
attendee, credential, and durable notification intent before a worker contacts a
provider. Provider outages therefore never roll back a completed registration.

## Sender onboarding

- Configure Laravel `MAIL_*` settings and verify the From domain for SMTP.
- Configure `UNIFONIC_SENDER_ID`. `UNIFONIC_APP_SID_REFERENCE` must contain the
  name of a runtime secret, never the secret itself.
- Set a high-entropy `UNIFONIC_CALLBACK_ROUTE_TOKEN` and register
  `/api/v1/webhooks/notifications/unifonic/{route_token}` with Unifonic.
- Keep `NOTIFICATIONS_ALLOW_NETWORK=false` until readiness checks pass in the
  target environment. Never use production destinations in readiness probes.

Templates are versioned by `template_key` and `template_version`. English uses
LTR markup and Arabic uses RTL markup. Templates may contain event display
names, public order references, and safe public links. They must never contain
buyer/attendee form answers, payment payloads, provider secrets, access tokens,
or raw credential tokens.

## Delivery and recovery

Run a queue worker and the scheduler. `zonetec:notifications:deliver-due`
re-enqueues pending or retryable intents every minute. Delivery attempts are
idempotent by notification ID, use bounded exponential delays, and become a
safe terminal failure after exhaustion. Duplicate jobs and callbacks converge
under row locks. The scheduler also re-enqueues processing intents older than
ten minutes so a terminated worker cannot strand delivery indefinitely.

Readiness exposes only the `notifications` category and a generic configuration
reason. Logs, telemetry, audit records, and organizer views contain channel and
state categories only; they exclude destination, body, and provider payload.

During an outage, correct the secret reference or provider configuration, run
the readiness check, then run `php artisan zonetec:notifications:deliver-due`.
Do not edit completed orders or credentials. For terminal failures, support may
send a new versioned notification intent after confirming the destination
through the approved personal-data workflow.
