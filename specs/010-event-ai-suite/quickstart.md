# Quickstart: Event Website Builder and AI Assistance

Runnable validation scenarios that prove the feature works end to end. Details of
shapes and schemas live in [contracts/](./contracts/) and
[data-model.md](./data-model.md).

## Prerequisites

- MySQL 8.4 reachable with the `.env` connection used by the repo.
- Migrations applied and demo data seeded:

```powershell
php artisan migrate
php artisan db:seed --class=Database\Seeders\FoundationSeeder
php artisan db:seed --class=Database\Seeders\BuilderDemoSeeder
```

- Model access stays off for validation (defaults):

```text
AI_DEFAULT_ADAPTER=fake
AI_EMBEDDING_ADAPTER=fake
AI_ALLOW_NETWORK=false
```

- Queue worker running so index rebuilds execute:

```powershell
php artisan queue:work --queue=foundation --tries=3
```

- Dev servers: `composer dev` (serves PHP plus Vite).

## Scenario 1 — Build and publish a site (US1)

1. Sign in as the demo tenant Event Manager and open
   `/en/tenant/events/{event_id}/site`.
2. Confirm a draft is created on first open with blocks seeded from the event
   (hero with event name and dates, agenda, register call to action).
3. Add an FAQ block, fill Arabic and English entries, save.
4. Switch the preview to Arabic and confirm RTL layout and Arabic content.
5. Publish. Expected: version 1 created, `public_url` returned.
6. Open `/en/events/{event_slug}` in a private window (anonymous). Expected: the
   published blocks render with event branding; no draft-only content appears.
7. Edit the draft again without publishing, reload the public URL. Expected: the
   public page still shows version 1.
8. Publish again. Expected: version 2 created, version 1 marked superseded.
9. Restore version 1 from history. Expected: draft now contains version 1 blocks,
   history unchanged, `draft_revision` incremented.
10. Unpublish. Expected: the public site returns 404 while
    `/en/events/{event_slug}/register` still works.

Automated equivalents:

```powershell
php artisan test --filter=EventSite
```

Covers draft schema validation, stale-revision conflict, publish idempotency
(same draft hash publishes once), publish blockers, public rendering of published
content only, not-found for non-shareable events, and audit records for publish,
unpublish and restore.

## Scenario 2 — Ask the assistant (US2)

1. On the same event, `PUT /api/v1/tenant/events/{event_id}/assistant` with
   `enabled: true` and `fallback_action: registration`.
2. Wait for the queue to process the rebuild, then re-read the endpoint. Expected:
   `index.status = ready` with a non-zero `chunk_count`.
3. Open the public site and ask, in English, a question answerable from the
   agenda. Expected: `outcome: answered` with at least one citation pointing at an
   agenda item of this event.
4. Ask the same question in Arabic. Expected: an Arabic answer.
5. Ask something absent from the content (for example about parking). Expected:
   `outcome: unanswered`, a localized message, the registration fallback, and the
   question visible in
   `GET /api/v1/tenant/events/{event_id}/assistant/usage`.
6. Ask about a different event by name, and separately send a message trying to
   override the instructions. Expected: `outcome: refused` with no foreign
   content and no prompt disclosure.
7. Exceed the per-visitor limit rapidly. Expected: `429` with
   `outcome: throttled`.
8. Set `AI_DEFAULT_ADAPTER=disabled` (or an unreachable hosted URL) and ask again.
   Expected: `503` with `outcome: unavailable`, no fabricated answer, and the site
   itself still renders.

Automated equivalents:

```powershell
php artisan test --filter=Assistant
```

Covers index build/refresh idempotency, retrieval scope isolation across events
and tenants, citation validation, language behavior, refusal and injection
resistance, throttling, and provider-failure degradation using the fake provider.

## Scenario 3 — AI analytics (US3)

1. Ensure the event has registrations, orders and check-ins (the demo seeders
   provide them).
2. Open `/en/tenant/events/{event_id}/site/insights` as a user with
   `reports.view`, request a summary for `last_14_days`.
3. Compare every figure stated in the summary against the numeric report at
   `/en/tenant/events/{event_id}/reports`. Expected: identical values.
4. Inspect `metrics_used` in the response. Expected: only allow-listed aggregate
   keys; no attendee name, email, phone or free-text answer anywhere.
5. Ask a follow-up question about the funnel. Expected: an answer using only the
   same aggregates; an out-of-scope question returns `out_of_scope`.
6. Request the same window again within the cache window. Expected:
   `cached: true` and no new provider call (verify via telemetry/log count).
7. Sign in as a user without `reports.view` and repeat. Expected: `403` with no
   metric content, plus an audit record.
8. Disable model access and request again. Expected: `503` while the numeric
   report stays fully usable.

Automated equivalents:

```powershell
php artisan test --filter=AiInsight
```

Covers the payload allow-list assertion, PII exclusion, insufficient-data
behavior, cache reuse, RBAC denial and degraded provider handling.

## Scenario 4 — Isolation and parity gates

```powershell
php artisan test --testsuite=Integration
composer lint
```

Expected outcomes:

- Cross-tenant and cross-event retrieval tests return zero foreign chunks,
  citations or answers.
- Index rebuild jobs restore tenant context and refuse to write outside their
  scope.
- With `AI_ALLOW_NETWORK=false`, no test performs an outbound HTTP request.
- Site builder and numeric report journeys pass identically with every AI surface
  reporting an explicit unavailable state.

## Performance checks

- Retrieval: seed ~500 chunks for one event and assert the retrieval step stays
  under 300ms (unit-level timing around `KnowledgeRetriever`).
- Public site: measure time to first useful content under the agreed load and
  confirm the 2s p95 target.
