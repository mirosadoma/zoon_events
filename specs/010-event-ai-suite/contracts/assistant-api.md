# Contract: Event Assistant API (RAG)

Two surfaces: organizer configuration and review (tenant-scoped, authenticated)
and the public ask endpoint (anonymous, event-scoped through the trusted public
event context).

## Organizer configuration

### GET `/api/v1/tenant/events/{event_id}/assistant`

Permission: `event.view`.

```json
{
  "data": {
    "enabled": true,
    "display_name": { "en": "Event assistant", "ar": "مساعد الحدث" },
    "greeting": { "en": "Ask me about the agenda", "ar": "اسألني عن الأجندة" },
    "fallback_action": "registration",
    "fallback_contact_email": null,
    "daily_question_limit": 500,
    "index": { "status": "ready", "version": 4, "indexed_at": "…", "chunk_count": 128, "error_code": null },
    "provider": { "available": false, "reason": "network_disabled" }
  }
}
```

### PUT `/api/v1/tenant/events/{event_id}/assistant`

Permission: `event.manage`. Idempotent.

Request accepts `enabled`, bilingual `display_name`/`greeting`,
`fallback_action` (`registration|contact|none`), `fallback_contact_email`
(required when `contact`), and `daily_question_limit` (bounded by config
maximum). Enabling dispatches an index rebuild. Audits
`event_assistant.configured`.

### POST `/api/v1/tenant/events/{event_id}/assistant/reindex`

Permission: `event.manage`. Idempotent. Dispatches
`RebuildEventKnowledgeIndexJob` for that tenant/event and returns the queued
index state. Repeated calls converge on one index generation. Audits
`event_assistant.reindexed`.

### GET `/api/v1/tenant/events/{event_id}/assistant/usage`

Permission: `reports.view`.

```json
{
  "data": {
    "totals": { "questions": 412, "answered": 351, "unanswered": 44, "refused": 9, "unavailable": 8, "throttled": 0 },
    "answer_rate": 0.85,
    "avg_latency_ms": 1830,
    "tokens": { "prompt": 128400, "completion": 41200 },
    "unanswered_questions": [ { "question": "Is parking free?", "count": 12, "last_asked_at": "…" } ]
  }
}
```

### DELETE `/api/v1/tenant/events/{event_id}/assistant/conversations/{public_id}`

Permission: `event.manage`. Deletes a conversation and its turns for PDPL
deletion requests. Audits `event_assistant.transcript_deleted`.

## Public ask endpoint

### POST `/api/v1/public/events/{event_slug}/assistant/ask`

Middleware: `throttle:public-event`, `throttle:public-assistant`,
`public.event.context.clear`, `public.event.context`. Anonymous.

Request:

```json
{ "conversation_id": "b1f6…", "message": "When does the keynote start?", "locale": "en" }
```

`conversation_id` is optional; when absent a conversation is created and its
`public_id` is returned. `locale` must be `en` or `ar`. `message` is limited to
1,000 characters. Tenant and event are taken only from the trusted public event
context — any scope field in the body is ignored.

Success response:

```json
{
  "data": {
    "conversation_id": "b1f6…",
    "outcome": "answered",
    "answer": "The keynote starts at 09:30 on 12 October in Hall A.",
    "citations": [ { "source_type": "agenda", "source_id": "41", "title": "Opening keynote" } ],
    "locale": "en"
  }
}
```

Non-answer outcomes use the same shape with `answer` replaced by a localized
message and a `fallback` object:

| `outcome` | HTTP | Meaning |
|---|---|---|
| `answered` | 200 | Grounded answer with at least one valid citation |
| `unanswered` | 200 | Nothing sufficient retrieved; localized message plus fallback; recorded for the organizer |
| `refused` | 200 | Out-of-scope or instruction-override attempt; no data disclosed |
| `throttled` | 429 | Per-visitor limit or per-event daily ceiling reached |
| `unavailable` | 503 | Assistant disabled, provider disabled/unreachable/timed out, or index not ready |

Guarantees:

- Retrieval filters on the trusted `(tenant_id, event_id)` pair and the live
  `index_version`; content from another event or tenant is unreachable.
- Every citation must correspond to a chunk actually retrieved for this request;
  answers citing anything else are converted to `unanswered`.
- The answer language matches `locale`.
- No credential, wallet, scanner, device, attendee-personal or organizer-internal
  data is ever included in retrieved context or in an answer.
- Content inside chunks or the visitor message cannot change the system
  instruction, the event scope, or reveal the prompt.
- Every turn is recorded with outcome, citations, latency and token accounting.
