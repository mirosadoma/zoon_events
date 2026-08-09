# Ai module

Owns the model-provider adapter, per-event knowledge index, grounded public assistant, transcripts, and organizer AI insight narratives over aggregate metrics.

## Owned tables

- `event_assistant_settings`
- `event_knowledge_chunks`
- `assistant_conversations`
- `assistant_turns`
- `event_insight_summaries`

## Exposed contracts

- `LlmProvider` / `EmbeddingProvider` — sole boundary for model access
- `KnowledgeRetriever` / `KnowledgeSourceProvider` — retrieval pipeline

## Consumed contracts

- `EventSites\Contracts\PublishedSiteReader` for published site text
- Events agenda / venue / registration facts for indexing
- Existing report aggregates for insights (allow-listed keys only)
- Audit, Tenancy (`TenantAwareJob`), RBAC (`event.view`, `event.manage`, `reports.view`)

## Configuration

See `config/ai.php`. Defaults: `AI_DEFAULT_ADAPTER=fake`, `AI_ALLOW_NETWORK=false`.

## Key surfaces

- Platform chat (RAG + analytics function calling): `POST api/v1/chat`
- Platform chat UI: `/{locale}/tenant/chat`
- Public ask: `POST api/v1/public/events/{event_slug}/assistant/ask`
- Organizer assistant: `api/v1/tenant/events/{event_id}/assistant*`
- Insights: `api/v1/tenant/events/{event_id}/ai-insights*`
- Insights UI: `/{locale}/tenant/events/{event_id}/site/insights`
