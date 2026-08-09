# Contract: AI Insights API

Organizer-only, tenant-scoped narrative analytics over already-computed event
metrics. Middleware: `auth:sanctum`, `throttle:phase1-organizer`,
`tenant.context.clear`, `tenant.context`, `permission:reports.view,tenant`.

## POST `/api/v1/tenant/events/{event_id}/ai-insights`

Idempotent within the cache freshness window.

Request:

```json
{ "metric_window": "last_14_days", "locale": "en", "refresh": false }
```

`metric_window` is one of `all_time`, `last_7_days`, `last_14_days`, `last_30_days`.
`refresh: true` bypasses the cache but still respects rate limits.

Response:

```json
{
  "data": {
    "metric_window": "last_14_days",
    "generated_at": "2026-08-04T12:00:00.000000Z",
    "expires_at": "2026-08-04T12:15:00.000000Z",
    "ai_generated": true,
    "provider_key": "fake",
    "cached": false,
    "summary": "Registrations grew 18% week over week while paid conversion stayed flat at 61%…",
    "highlights": [
      { "kind": "trend", "text": "Registrations up 18% week over week" },
      { "kind": "risk", "text": "Hall B occupancy peaked at 94% of capacity" },
      { "kind": "action", "text": "Close tier A before it sells out" }
    ],
    "metrics_used": { "registered_count": 1240, "funnel": { "registered": 1240, "paid": 760 } }
  }
}
```

`metrics_used` echoes the exact aggregate payload the model received, making
every statement reproducible and auditable.

## POST `/api/v1/tenant/events/{event_id}/ai-insights/ask`

Follow-up question restricted to the same aggregate payload.

Request:

```json
{ "metric_window": "last_14_days", "locale": "en", "question": "Why did check-ins drop on day 3?" }
```

Response uses `outcome` values `answered`, `insufficient_data`, `out_of_scope`,
`throttled` (429) or `unavailable` (503), with the answer text and the same
`metrics_used` echo.

## Payload minimization contract

The assembler may only emit these keys, drawn from existing computed metrics:

`registered_count`, `checked_in_count`, `rejected_count`, `duplicate_count`,
`orders_total`, `orders_by_status`, `paid_orders`, `revenue_minor`, `currency`,
`payment_success_rate`, `credentials_issued`, `credentials_revoked`,
`registrations_by_day`, `checkins_by_day`, `funnel`, `category_breakdown`,
`ticket_type_breakdown`, `zone_occupancy_summary`, `top_reject_reasons`,
`capacity`, `event_window`.

Hard guarantees, each covered by an automated test:

- No attendee name, email, phone, national identifier, ticket code, credential
  identifier, payment instrument detail or free-text form answer can appear.
- Breakdown buckets are counts only; a bucket that would identify fewer than the
  configured minimum number of people is reported as a count without labels.
- Nothing outside the requesting tenant and event enters the payload.
- The payload is stored with the summary; the same payload must regenerate an
  equivalent summary.

## Degradation

- Model access disabled or provider unreachable → `503` with
  `code: ai.provider_unavailable`; the numeric report remains fully usable.
- Insufficient data → `outcome: insufficient_data` with no invented trend.
- Rate limited → `429` with a localized message and retry hint.
