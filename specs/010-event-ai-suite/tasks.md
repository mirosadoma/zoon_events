---

description: "Task list for Event Website Builder and AI Assistance"
---

# Tasks: Event Website Builder and AI Assistance

**Input**: Design documents from `/specs/010-event-ai-suite/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md),
[research.md](./research.md), [data-model.md](./data-model.md),
[contracts/](./contracts/)

**Tests**: Automated tests are MANDATORY. Unit, integration, contract, feature,
tenant-isolation, RBAC, audit and security regression tasks are included per
story.

**Organization**: Grouped by user story so each story is independently
implementable, testable and deployable.

**Product Phase**: Phase 9 — Event Web Presence and AI Assistance (additive)

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 (site builder), US2 (assistant), US3 (AI analytics)
- Exact file paths are included in every task

## Path Conventions

Backend modules live under `app/Modules/<Module>/`, migrations under
`database/migrations/`, frontend under `resources/js/`, tests under
`tests/{Unit,Feature,Integration,Contract}/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Create both module skeletons and wire them into the platform.

- [X] T001 Create the `EventSites` module skeleton directories per plan.md in `app/Modules/EventSites/{Application/{Actions,Queries,Support},Contracts,Domain,Http/{Controllers,Requests},Infrastructure/Persistence/Models,Providers,Routes}`
- [X] T002 Create the `Ai` module skeleton directories per plan.md in `app/Modules/Ai/{Application/{Actions,Jobs,Queries,Retrieval,Support},Contracts,Domain,Http/{Controllers,Requests},Infrastructure/{Adapters,Persistence/Models,Secrets},Testing,Providers,Routes}`
- [X] T003 Create `app/Modules/EventSites/Providers/EventSitesServiceProvider.php` and `app/Modules/Ai/Providers/AiServiceProvider.php`, then register both in `app/Providers/ModuleServiceProvider.php`
- [X] T004 Create `app/Modules/EventSites/Routes/api.php` and `app/Modules/Ai/Routes/api.php` and require both from `routes/api.php` inside the existing `v1` prefix group
- [X] T005 [P] Create `config/ai.php` with the keys defined in [contracts/llm-adapter.md](./contracts/llm-adapter.md) and add the matching `AI_*` keys to `.env.example` with safe defaults (`AI_DEFAULT_ADAPTER=fake`, `AI_ALLOW_NETWORK=false`) and no secret values
- [X] T006 [P] Create localization files `lang/en/event_sites.php`, `lang/ar/event_sites.php`, `lang/en/ai.php`, `lang/ar/ai.php` with block labels, publish-blocker messages, and assistant refusal/throttle/unavailable messages
- [X] T007 [P] Register the `public-assistant` rate limiter alongside the existing limiters in `bootstrap/app.php` (or the provider where `public-event` is defined) using `config('ai.assistant.visitor_questions_per_minute')`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, models and the model-provider adapter that every story needs.

**CRITICAL**: No user story work can begin until this phase is complete.

- [X] T008 Create migration `database/migrations/2026_08_04_000001_create_event_sites_tables.php` for `event_sites` and `event_site_versions` per [data-model.md](./data-model.md), including composite FKs to `events(tenant_id, id)`, tenant-first indexes, and `CHECK` constraints on `status` and `page_mode`
- [X] T009 Create migration `database/migrations/2026_08_04_000002_create_event_assistant_tables.php` for `event_assistant_settings`, `assistant_conversations` and `assistant_turns` with composite scope FKs, `purge_after` index and status `CHECK` constraints
- [X] T010 Create migration `database/migrations/2026_08_04_000003_create_event_knowledge_chunks_table.php` including the `FULLTEXT (content, title)` index, `(tenant_id, event_id, index_version)` index and unique `(tenant_id, event_id, index_version, content_hash)`
- [X] T011 Create migration `database/migrations/2026_08_04_000004_create_event_insight_summaries_table.php` with the unique `(tenant_id, event_id, metric_window, payload_hash)` cache key
- [X] T012 [P] Create Eloquent models `app/Modules/EventSites/Infrastructure/Persistence/Models/EventSite.php` and `EventSiteVersion.php` with JSON casts, tenant/event scope helpers, and an update guard that rejects mutation of persisted version content
- [X] T013 [P] Create Eloquent models `app/Modules/Ai/Infrastructure/Persistence/Models/{EventAssistantSetting,EventKnowledgeChunk,AssistantConversation,AssistantTurn,EventInsightSummary}.php` with JSON casts and scope helpers
- [X] T014 [P] Define adapter contracts `app/Modules/Ai/Contracts/{LlmProvider,EmbeddingProvider}.php` plus value objects `app/Modules/Ai/Domain/{AiCompletionRequest,AiCompletionResult,AiProviderException}.php` exactly as specified in [contracts/llm-adapter.md](./contracts/llm-adapter.md)
- [X] T015 [P] Implement `app/Modules/Ai/Testing/FakeLlmProvider.php` and `FakeEmbeddingProvider.php` with deterministic, network-free behavior that echoes cited chunk numbers and returns stable pseudo-vectors
- [X] T016 Implement `app/Modules/Ai/Infrastructure/Adapters/Hosted/{HostedLlmProvider,HostedEmbeddingProvider}.php` and `app/Modules/Ai/Infrastructure/Adapters/SelfHosted/{SelfHostedLlmProvider,SelfHostedEmbeddingProvider}.php` sharing one OpenAI-compatible HTTP client base with configured timeout, single safe retry, request-size guard, mapped error codes and telemetry (depends: T014)
- [X] T017 Implement `app/Modules/Ai/Infrastructure/Secrets/EnvironmentAiSecretLoader.php` and `app/Modules/Ai/Application/AiProviderRegistry.php`, then bind `LlmProvider`/`EmbeddingProvider` from config in `AiServiceProvider` following `app/Modules/Payments/Providers/PaymentsServiceProvider.php` (depends: T014, T015, T016)
- [X] T018 Implement `app/Modules/Ai/Application/Support/AiLimiter.php` enforcing per-visitor throttle, per-event daily ceiling, max context characters and max output tokens from `config/ai.php` before any provider call (depends: T005)
- [X] T019 [P] Add unit tests `tests/Unit/Ai/AiProviderRegistryTest.php` and `tests/Unit/Ai/AdapterDegradationTest.php` asserting fake-by-default resolution, `network_disabled` behavior when `AI_ALLOW_NETWORK=false`, timeout/retry policy and error-code mapping (depends: T017)
- [X] T020 [P] Add integration test `tests/Integration/Security/AiTenantScopeGuardTest.php` asserting that every new model rejects reads and writes outside the trusted tenant/event scope, including inside queued jobs (depends: T012, T013)
- [X] T021 [P] Add contract test `tests/Contract/AiNoNetworkByDefaultTest.php` asserting that with default configuration no outbound HTTP request is attempted by any AI path (depends: T017)

**Checkpoint**: Schema, models and the provider adapter are ready; stories can start.

---

## Phase 3: User Story 1 - Build and publish an event website (Priority: P1) — MVP

**Goal**: An organizer composes, previews, publishes, unpublishes and restores a
single-page bilingual event website; visitors see only the published version.

**Independent Test**: Create a site for a published event, add one block of each
type, publish, open the public URL anonymously, and confirm published-only
bilingual content with branding while later draft edits stay invisible.

### Tests for User Story 1 (MANDATORY)

- [X] T022 [P] [US1] Contract test `tests/Contract/EventSites/EventSiteApiTest.php` asserting every operation and response shape in [contracts/event-site-api.md](./contracts/event-site-api.md), including problem-details errors
- [X] T023 [P] [US1] Unit test `tests/Unit/EventSites/SiteBlockValidatorTest.php` covering unknown block type, duplicate block id, unknown option key, oversized text, block count ceiling and bilingual content rules
- [X] T024 [P] [US1] Feature test `tests/Feature/EventSites/EventSiteDraftLifecycleTest.php` covering first-open draft seeding, save, stale-revision `409`, publish, repeat-publish idempotency, publish blockers, unpublish and version restore
- [X] T025 [P] [US1] Feature test `tests/Feature/EventSites/PublicEventSitePageTest.php` covering published-only rendering, hidden blocks excluded, live agenda/venue resolution, `404` for unpublished sites and non-shareable events, and registration page still reachable after unpublish
- [X] T026 [P] [US1] Integration test `tests/Integration/Security/EventSiteIsolationTest.php` asserting cross-tenant and cross-event denial on every organizer route and that a foreign `event_id` returns `404`
- [X] T027 [P] [US1] Feature test `tests/Feature/EventSites/EventSiteRbacTest.php` asserting `event.view`, `event.manage` and `event.publish` enforcement per route, including denial for a tenant member without the permission
- [X] T028 [P] [US1] Feature test `tests/Feature/EventSites/EventSiteAuditTest.php` asserting audit records for publish, unpublish and version restore, and that a failing audit write prevents the state change from being reported complete
- [X] T029 [P] [US1] Frontend test `resources/js/__tests__/event-site-builder.test.tsx` covering block add/reorder/remove, locale switch, RTL layout, publish-blocker display and save conflict handling

### Implementation for User Story 1

- [X] T030 [P] [US1] Implement `app/Modules/EventSites/Domain/SiteBlockType.php` and `SiteStatus.php` enums plus `app/Modules/EventSites/Application/Support/SiteBlockSchema.php` declaring allowed option and ref keys per block type
- [X] T031 [US1] Implement `app/Modules/EventSites/Application/Support/SiteBlockValidator.php` enforcing the rules in data-model.md with field paths suitable for `422` responses (depends: T030)
- [X] T032 [P] [US1] Implement `app/Modules/EventSites/Application/Support/SiteBlockDefaults.php` seeding a first draft from event name, dates, location, main image, agenda and register CTA (depends: T030)
- [X] T033 [US1] Implement `app/Modules/EventSites/Application/Actions/SaveSiteDraft.php` with optimistic `draft_revision` concurrency and validation (depends: T012, T031)
- [X] T034 [US1] Implement `app/Modules/EventSites/Application/Actions/PublishSite.php` creating an immutable version, superseding the previous one, repointing `live_version_id` in one transaction, short-circuiting on matching `blocks_hash`, and writing the audit record (depends: T033)
- [X] T035 [US1] Implement `app/Modules/EventSites/Application/Actions/UnpublishSite.php` and `RestoreSiteVersion.php` with audit records and history immutability (depends: T034)
- [X] T036 [P] [US1] Implement `app/Modules/EventSites/Application/Queries/GetOrganizerSite.php` returning draft, live version, `public_url` and computed `publish_blockers` (depends: T031)
- [X] T037 [US1] Implement `app/Modules/EventSites/Application/Support/PublishedSitePresenter.php` resolving live agenda, speakers, venue, gallery and register URL for public rendering, degrading removed references to empty state (depends: T012)
- [X] T038 [US1] Implement `app/Modules/EventSites/Contracts/PublishedSiteReader.php` and its database implementation so the `Ai` module can read published block text without touching `EventSites` tables directly (depends: T037)
- [X] T039 [US1] Implement `app/Modules/EventSites/Http/Requests/{SaveSiteDraftRequest,PublishSiteRequest,SiteMediaUploadRequest}.php` (depends: T031)
- [X] T040 [US1] Implement `app/Modules/EventSites/Http/Controllers/OrganizerEventSiteController.php` with show, saveDraft, publish, unpublish, versions, restore and media upload (depends: T033, T034, T035, T036, T039)
- [X] T041 [US1] Implement `app/Modules/EventSites/Http/Controllers/Public/PublicEventSiteController.php` reading tenant and event only from the trusted public event context (depends: T037)
- [X] T042 [US1] Wire routes in `app/Modules/EventSites/Routes/api.php` with `permission:event.view|event.manage|event.publish,tenant` and `idempotency` on writes for organizer routes, and `throttle:public-event` plus `public.event.context` for the public route (depends: T040, T041)
- [X] T043 [US1] Add Inertia web routes and controller wiring in `routes/web.php` for `/{locale}/tenant/events/{event_id}/site` and the public `/{locale}/events/{event_slug}` site page (depends: T040, T041)
- [X] T044 [P] [US1] Implement block editor components in `resources/js/components/event-site/editors/` (Hero, About, Agenda, Speakers, Venue, Faq, Sponsors, Gallery, RegisterCta) with bilingual fields and visibility toggle
- [X] T045 [P] [US1] Implement block renderer components in `resources/js/components/event-site/renderers/` consuming the public payload and honoring the event theme and text direction
- [X] T046 [US1] Implement `resources/js/pages/tenant/events/SiteBuilder.tsx` with block list, reorder, live preview, locale toggle, save, publish, unpublish, version history and restore (depends: T044)
- [X] T047 [US1] Implement `resources/js/pages/public/site/EventSite.tsx` rendering published blocks with branding, RTL/LTR and the register call to action (depends: T045)
- [X] T048 [US1] Add localized publish-blocker, validation, empty and conflict messages to `lang/{en,ar}/event_sites.php` and surface them in the builder (depends: T006, T046)

**Checkpoint**: US1 is fully functional and independently demonstrable (MVP).

---

## Phase 4: User Story 2 - Ask the event assistant (Priority: P2)

**Goal**: A grounded, citation-bearing, bilingual assistant on the published site,
scoped strictly to one event's indexed knowledge, with explicit refusal,
throttling and degradation behavior.

**Independent Test**: Enable the assistant, ask five answerable and one
unanswerable question, and confirm grounded citations, an explicit unavailable
answer for the sixth, and zero cross-event content.

### Tests for User Story 2 (MANDATORY)

- [X] T049 [P] [US2] Contract test `tests/Contract/Ai/AssistantApiTest.php` asserting every operation, outcome value and status code in [contracts/assistant-api.md](./contracts/assistant-api.md)
- [X] T050 [P] [US2] Unit test `tests/Unit/Ai/KnowledgeChunkerTest.php` covering chunk size limits, per-locale splitting, source labeling, stable `content_hash` and exclusion of credential/wallet/scanner/attendee fields
- [X] T051 [P] [US2] Unit test `tests/Unit/Ai/CosineRerankerTest.php` covering ranking order, missing-vector fallback to lexical score and context budget truncation
- [X] T052 [P] [US2] Integration test `tests/Integration/Ai/KnowledgeIndexRebuildTest.php` asserting idempotent whole-index rebuild, single live `index_version`, no partial state on failure, and tenant context restoration inside the job
- [X] T053 [P] [US2] Integration test `tests/Integration/Security/AssistantRetrievalIsolationTest.php` asserting zero foreign chunks, citations or answers across events and tenants, including when a body field attempts to specify another event
- [X] T054 [P] [US2] Feature test `tests/Feature/Ai/PublicAssistantAskTest.php` covering answered with citations, unanswered with fallback and organizer visibility, refusal for out-of-scope and injection attempts, language matching, `429` throttling and `503` degradation with the fake provider forced to fail
- [X] T055 [P] [US2] Feature test `tests/Feature/Ai/AssistantConfigTest.php` covering configuration validation, `reports.view` gating of usage, transcript deletion and audit records for configure, reindex and transcript delete
- [X] T056 [P] [US2] Unit test `tests/Unit/Ai/CitationValidationTest.php` asserting that an answer citing chunks not retrieved for the request is converted to `unanswered`
- [X] T057 [P] [US2] Frontend test `resources/js/__tests__/assistant-panel.test.tsx` covering send, citation display, unavailable state, throttled state and RTL rendering

### Implementation for User Story 2

- [X] T058 [P] [US2] Implement `app/Modules/Ai/Contracts/{KnowledgeRetriever,KnowledgeSourceProvider}.php` and `app/Modules/Ai/Domain/AiAnswer.php`
- [X] T059 [US2] Implement knowledge source providers in `app/Modules/Ai/Application/Retrieval/Sources/` for published site blocks (via `PublishedSiteReader`), event core fields, agenda and speakers, venue and zones, and registration/ticket facts, each emitting explicit source type and id (depends: T038, T058)
- [X] T060 [US2] Implement `app/Modules/Ai/Application/Retrieval/KnowledgeChunker.php` producing per-locale chunks with hashes and token estimates and an explicit excluded-field list (depends: T059)
- [X] T061 [US2] Implement `app/Modules/Ai/Application/Actions/RebuildEventIndex.php` writing a new `index_version`, deleting superseded versions for the scope in the same transaction, and updating index state on the settings row (depends: T060, T013)
- [X] T062 [US2] Implement `app/Modules/Ai/Application/Jobs/RebuildEventKnowledgeIndexJob.php` implementing `TenantAwareJob` with `RestoreTenantContext` middleware on the `foundation` queue, dispatched after site publish and after assistant enable (depends: T061, T034)
- [X] T063 [US2] Implement `app/Modules/Ai/Application/Retrieval/LexicalCandidateFinder.php` (scoped `FULLTEXT` query with `LIKE` fallback) and `CosineReranker.php`, composed by `DatabaseKnowledgeRetriever` (depends: T058, T010)
- [X] T064 [US2] Implement `app/Modules/Ai/Application/Support/PromptBuilder.php` assembling the system instruction, numbered untrusted context chunks and the visitor message, respecting `max_context_chars` (depends: T063)
- [X] T065 [US2] Implement `app/Modules/Ai/Application/Support/GroundedAnswerParser.php` validating cited chunk numbers against what was retrieved and downgrading ungrounded answers to `unanswered` (depends: T064)
- [X] T066 [US2] Implement `app/Modules/Ai/Application/Actions/AnswerEventQuestion.php` orchestrating limit checks, retrieval, provider call, citation validation, refusal rules, turn persistence and outcome mapping (depends: T018, T063, T065)
- [X] T067 [US2] Implement `app/Modules/Ai/Http/Controllers/Public/PublicAssistantController.php` plus `app/Modules/Ai/Http/Requests/AskAssistantRequest.php`, taking scope only from the trusted public event context (depends: T066)
- [X] T068 [US2] Implement `app/Modules/Ai/Http/Controllers/AssistantConfigController.php` with show, update, reindex, usage and conversation delete, including audit records (depends: T061, T013)
- [X] T069 [US2] Implement `app/Modules/Ai/Application/Queries/GetAssistantUsage.php` aggregating outcomes, answer rate, latency, tokens and grouped unanswered questions (depends: T013)
- [X] T070 [US2] Wire routes in `app/Modules/Ai/Routes/api.php` for organizer assistant routes (`permission:event.view|event.manage,tenant`, `reports.view` for usage, `idempotency` on writes) and the public ask route (`throttle:public-event`, `throttle:public-assistant`, `public.event.context`) (depends: T067, T068)
- [X] T071 [US2] Implement the retention sweep command `app/Modules/Ai/Application/Commands/PurgeAssistantTranscripts.php` and schedule it in `routes/console.php` using `config('ai.assistant.transcript_retention_days')` (depends: T013)
- [X] T072 [P] [US2] Implement `resources/js/components/ai/AssistantPanel.tsx` with message list, citation chips, greeting, unavailable/throttled states, RTL support and localized strings
- [X] T073 [US2] Mount the assistant panel on the public site page when the site payload reports the assistant enabled (depends: T047, T072)
- [X] T074 [US2] Add assistant configuration UI to `resources/js/pages/tenant/events/SiteBuilder.tsx` (enable, names, greeting, fallback, limit, index status, reindex) plus an unanswered-questions list (depends: T046, T068)

**Checkpoint**: US1 and US2 both work independently.

---

## Phase 5: User Story 3 - Explain event performance with AI analytics (Priority: P3)

**Goal**: Narrative insight summaries and natural-language follow-ups over
already-computed aggregates, with a hard PII exclusion boundary and cache reuse.

**Independent Test**: Generate a summary for an event with real metrics, verify
every stated figure matches the numeric report, and verify the payload contains
only allow-listed aggregate keys.

### Tests for User Story 3 (MANDATORY)

- [X] T075 [P] [US3] Contract test `tests/Contract/Ai/AiInsightsApiTest.php` asserting the operations, outcomes and `metrics_used` echo in [contracts/ai-insights-api.md](./contracts/ai-insights-api.md)
- [X] T076 [P] [US3] Unit test `tests/Unit/Ai/EventMetricsPayloadTest.php` asserting the allow-list, rejection of any non-listed key, small-bucket suppression and stable `payload_hash`
- [X] T077 [P] [US3] Security test `tests/Integration/Security/AiInsightPayloadPrivacyTest.php` scanning generated payloads for attendee names, emails, phones, identifiers, ticket codes and free-text answers and asserting none appear on any analytics path
- [X] T078 [P] [US3] Feature test `tests/Feature/Ai/AiInsightGenerationTest.php` covering generation, figure consistency with the report source, cache reuse within freshness, `refresh` bypass, insufficient-data outcome, out-of-scope follow-up, `403` without `reports.view` and `503` when the provider is unavailable
- [X] T079 [P] [US3] Feature test `tests/Feature/Ai/AiInsightAuditTest.php` asserting audit records for insight generation and privileged reads
- [X] T080 [P] [US3] Frontend test `resources/js/__tests__/ai-insights.test.tsx` covering summary render, AI-generated labeling, highlights, follow-up question and unavailable state

### Implementation for User Story 3

- [X] T081 [US3] Implement `app/Modules/Ai/Application/Support/EventMetricsPayload.php` assembling only allow-listed aggregates from the existing sources named in [research.md](./research.md) R4, with small-bucket suppression and deterministic hashing (depends: T005)
- [X] T082 [US3] Implement `app/Modules/Ai/Application/Actions/GenerateEventInsight.php` producing bilingual summary text and structured highlights, storing the payload with the summary, and reusing the cache within freshness (depends: T081, T013, T018)
- [X] T083 [US3] Implement `app/Modules/Ai/Application/Actions/AnswerInsightQuestion.php` restricted to the stored aggregate payload with `insufficient_data` and `out_of_scope` outcomes (depends: T082)
- [X] T084 [US3] Implement `app/Modules/Ai/Http/Controllers/AiInsightController.php` and its form requests, gated by `permission:reports.view,tenant`, with audit records (depends: T082, T083)
- [X] T085 [US3] Add insight routes to `app/Modules/Ai/Routes/api.php` (depends: T084)
- [X] T086 [US3] Implement `resources/js/pages/tenant/events/AiInsights.tsx` with the summary card, highlight list, AI-generated label with generation time, follow-up input, cached indicator and unavailable state, and add its Inertia route in `routes/web.php` (depends: T084)
- [X] T087 [US3] Add an AI insights entry point from the existing event report page so organizers reach it from the numbers they are reading (depends: T086)

**Checkpoint**: All three stories are independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T088 [P] Document both modules in `app/Modules/EventSites/README.md` and `app/Modules/Ai/README.md`, including owned tables, exposed contracts and consumed contracts
- [X] T089 [P] Add the new public and tenant operations to the OpenAPI source and run `php scripts/sync-openapi.php --check` until the route-coverage gate passes
- [X] T090 [P] Add deployment notes for hosted versus self-hosted providers, network policy and retention configuration in `docs/release/deployment-parity.md`
- [X] T091 Verify tenant isolation, RBAC and audit coverage across all new routes and jobs by running `php artisan test --testsuite=Integration`
- [X] T092 Verify PDPL controls: transcript retention sweep, deletion request path, aggregates-only analytics payload and no personal data in provider requests
- [X] T093 Verify Arabic/English, RTL/LTR, branding and accessibility on the builder, public site, assistant panel and insights page
- [X] T094 Verify SaaS/on-premise parity by running the full suite with `AI_DEFAULT_ADAPTER=fake`, then with a forced-failure driver, confirming explicit degradation and zero fabricated output
- [X] T095 Measure the performance targets from plan.md (retrieval under 300ms for a 500-chunk index; public site 2s p95) and record results
- [X] T096 Run `composer lint` and `composer test`, then execute every scenario in [quickstart.md](./quickstart.md)
- [X] T097 Expose the active provider key and degraded reason to operators by adding an AI provider readiness check next to the existing checks in `app/Modules/Operations/Application/Health/Checks/` and covering it in `tests/Feature/Health/HealthCheckTest.php` (satisfies FR-033)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: no dependencies.
- **Foundational (Phase 2)**: depends on Setup; blocks all stories.
- **US1 (Phase 3)**: depends on Phase 2 only.
- **US2 (Phase 4)**: depends on Phase 2; consumes the `PublishedSiteReader`
  contract from T038 for site-block knowledge, and mounts its panel on the public
  page from T047. Both are contract-level touchpoints, so US2 remains
  independently testable with an empty site.
- **US3 (Phase 5)**: depends on Phase 2 only; independent of US1 and US2.
- **Polish (Phase 6)**: depends on the stories being delivered.

### Within Each User Story

- Required tests are written first and must fail before implementation.
- Models before actions, actions before controllers, controllers before routes,
  backend before frontend wiring.

### Parallel Opportunities

- T005, T006, T007 in Setup.
- T012, T013, T014, T015 and the test tasks T019, T020, T021 in Foundational.
- All US1 test tasks T022–T029; then T030, T032, T036 and the component tasks
  T044, T045.
- All US2 test tasks T049–T057; T058 and T072 in implementation.
- All US3 test tasks T075–T080.
- After Phase 2, US1, US2 and US3 can be staffed in parallel.

## Parallel Example: User Story 1

```bash
# Tests first, together:
Task: "Contract test in tests/Contract/EventSites/EventSiteApiTest.php"
Task: "Unit test in tests/Unit/EventSites/SiteBlockValidatorTest.php"
Task: "Feature test in tests/Feature/EventSites/EventSiteDraftLifecycleTest.php"
Task: "Isolation test in tests/Integration/Security/EventSiteIsolationTest.php"

# Then independent implementation units:
Task: "Enums and schema in app/Modules/EventSites/Domain/SiteBlockType.php"
Task: "Defaults in app/Modules/EventSites/Application/Support/SiteBlockDefaults.php"
Task: "Block editors in resources/js/components/event-site/editors/"
```

## Implementation Strategy

### MVP first

1. Phase 1 Setup.
2. Phase 2 Foundational (blocking).
3. Phase 3 US1, then stop and validate scenario 1 in quickstart.md.
4. Demo the published event site.

### Incremental delivery

1. Setup + Foundational → foundation ready.
2. US1 → validate → demo (MVP).
3. US2 → validate → demo.
4. US3 → validate → demo.
5. Polish gates before release.

## Task Summary

- Total tasks: 97
- Setup: 7 (T001–T007)
- Foundational: 14 (T008–T021)
- US1 (P1, MVP): 27 (T022–T048), of which 8 are tests
- US2 (P2): 26 (T049–T074), of which 9 are tests
- US3 (P3): 13 (T075–T087), of which 6 are tests
- Polish: 10 (T088–T097)
- Suggested MVP scope: Phases 1–3 (T001–T048)
