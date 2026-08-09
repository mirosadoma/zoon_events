# Implementation Plan: Event Website Builder and AI Assistance

**Branch**: `010-event-ai-suite` | **Date**: 2026-08-04 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/010-event-ai-suite/spec.md`

**Product Phase**: Phase 9 — Event Web Presence and AI Assistance (additive after
Phase 6; depends on Foundation, Phase 1 events/registration, and existing
reporting surfaces)

**Deployment Modes**: both (SaaS and on-premise)

## Summary

Add three additive capabilities per event: an organizer-composed public website
built from bilingual content blocks with draft/published versioning, an
attendee-facing retrieval-augmented assistant restricted to that event's own
published knowledge, and an organizer-facing narrative analytics layer over
already-computed aggregated metrics.

Technical approach: two new modules. `app/Modules/EventSites` owns site drafts,
immutable published versions, block validation, public rendering payloads and
site media. `app/Modules/Ai` owns one provider adapter contract (completion plus
embeddings) with hosted, self-hosted and fake implementations, the per-event
knowledge index, retrieval, grounded answering, transcripts and insight
generation. Retrieval runs on MySQL 8.4 without a vector type: a tenant/event
scoped chunk table with a `FULLTEXT` lexical prefilter plus in-PHP cosine rerank
over stored embedding vectors, which is sufficient for the per-event corpus size
(tens to low hundreds of chunks). All model access is disabled by default and
the fake provider is the configured default, so both new modules are fully
testable and the site builder plus numeric reports never depend on network
access.

## Technical Context

**Language/Version**: PHP 8.2+ (Laravel 12), TypeScript with React 19 via Inertia 2

**Primary Dependencies**: existing repo stack only — `laravel/framework`,
`inertiajs/inertia-laravel`, `laravel/sanctum`, Vite, Tailwind. No new Composer
or npm dependency is required: provider calls use the built-in HTTP client and
embeddings are stored as JSON, mirroring
[config/payments.php](../../config/payments.php) and
[PaymentsServiceProvider.php](../../app/Modules/Payments/Providers/PaymentsServiceProvider.php).

**Storage**: MySQL 8.4 (no native `VECTOR` type; see
[research.md](./research.md) decision R1), plus the existing `public` disk for
site media and private disk conventions for exports.

**Testing**: PHPUnit 11 (`tests/Unit`, `tests/Feature`, `tests/Integration`,
`tests/Contract`), Vitest for React components.

**Target Platform**: Linux/Windows PHP-FPM web tier with the `foundation` queue
worker for indexing jobs; identical on-premise deployment.

**Project Type**: Modular monolith web application (backend modules + Inertia
React frontend).

**Performance Goals**: published site first useful content within 2s at p95;
retrieval over a 500-chunk event index within 300ms; assistant answer within 5s
at p95; insight summary reused from cache within the configured freshness window.

**Constraints**: no outbound network by default (`AI_ALLOW_NETWORK=false`);
bounded provider timeout and single retry; per-visitor throttle and per-event
daily question ceiling; maximum retrieved context size; aggregates-only
analytics payload; no PII in prompts beyond the visitor's own message.

**Scale/Scope**: one site per event, up to ~40 blocks per site, up to ~1,000
knowledge chunks per event, ~10 new tables, ~24 API operations, 6 new frontend
routes.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Initial evaluation — all gates pass with no exception required.

- **API-first**: PASS. All capabilities are exposed as versioned tenant or public
  contracts under `api/v1` documented in [contracts/](./contracts/):
  organizer site CRUD/publish, public site read, public assistant ask, assistant
  configuration and usage, and insight generation. Publish and index operations
  are idempotent (`idempotency` middleware on writes, natural idempotency on
  index rebuild). No existing contract changes shape, so compatibility is
  additive only.
- **Tenant isolation**: PASS. Every new table carries `tenant_id` and `event_id`
  with tenant-first indexes. Organizer paths resolve tenant through
  `tenant.context`; public paths resolve tenant and event through the existing
  trusted `public.event.context` middleware and never accept a client-supplied
  tenant. Retrieval and analytics payload assembly filter on the trusted pair.
  Indexing jobs implement `TenantAwareJob` and restore context through
  `RestoreTenantContext`. Negative isolation tests are required in
  `tests/Integration/Security`.
- **RBAC and auditability**: PASS. Reuses `event.view`, `event.manage`,
  `event.publish`, `reports.view`, `audit.view`; no new permission names.
  Audited actions: site publish, unpublish, version restore, assistant
  enable/disable and configuration change, index rebuild, transcript
  view/export/delete, and insight generation. Audit writes participate in the
  same transaction boundary as the state change.
- **Credential security**: PASS by exclusion. No credential is issued, signed or
  validated. Indexers explicitly exclude credential, wallet, scanner and device
  data; a contract test asserts the excluded field list.
- **Deployment parity**: PASS. Identical behavior in both modes; the only
  difference is which provider satisfies the adapter contract (hosted for SaaS,
  self-hosted for on-premise, fake by default). Site builder and numeric reports
  remain fully functional with model access disabled.
- **GCC/KSA and PDPL**: PASS. Site content is organizer marketing content.
  Transcripts are the only new personal-data surface: single purpose, minimized,
  retention-bounded by configuration (90-day default), deletable, excluded from
  training, and never sent to a provider beyond the current answer context.
  Analytics payloads are aggregates only, enforced by an allow-list assembler
  plus an automated payload inspection test. Residency is preserved because a
  deployment that forbids external processing keeps the self-hosted or fake
  provider.
- **White-label and localization**: PASS. Blocks store `content_en`/`content_ar`;
  rendering reuses the event branding theme already used by the registration
  page; chat panel, refusal, throttle and unavailability messages are localized
  in `lang/en` and `lang/ar`; RTL/LTR verified for builder and public site.
- **Modularity and adapters**: PASS. `EventSites` owns site data; `Ai` owns model
  access, index, retrieval, transcripts and insights. Neither module reads the
  other's persistence internals: `Ai` consumes published site content through an
  `EventSites` application contract, and analytics consume existing report
  view-model output rather than foreign tables. All provider access is behind
  `LlmProvider`/`EmbeddingProvider` contracts with a registry, timeout, bounded
  retry, error mapping, telemetry and a fake implementation.
- **Automated tests**: PASS. Unit (block schema, chunker, cosine rerank,
  aggregates allow-list, grounded-answer assembly), integration (publish
  lifecycle, index refresh, retrieval isolation, RBAC denial, audit atomicity),
  contract (API shape and excluded-field assertions), feature (public rendering,
  assistant ask, throttling, degraded provider), and frontend component tests.
- **Phased delivery**: PASS. Additive phase; does not touch registration,
  credential, scanning or access-control behavior, and is not a prerequisite for
  Phase 7 or Phase 8.

Post-design re-check (after Phase 1 artifacts): still PASS. The only design
choice that required justification review was retrieval without a vector store;
it is recorded as decision R1 in [research.md](./research.md) and stays inside
the owning module behind a `KnowledgeRetriever` contract, so replacing it later
does not change any API contract. No Complexity Tracking entry is required.

## Project Structure

### Documentation (this feature)

```text
specs/010-event-ai-suite/
├── plan.md              # This file
├── research.md          # Phase 0 decisions
├── data-model.md        # Phase 1 entities and schema
├── quickstart.md        # Phase 1 validation guide
├── contracts/           # Phase 1 interface contracts
│   ├── event-site-api.md
│   ├── assistant-api.md
│   ├── ai-insights-api.md
│   └── llm-adapter.md
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 output (/speckit-tasks)
```

### Source Code (repository root)

```text
app/Modules/EventSites/
├── Application/
│   ├── Actions/            # SaveSiteDraft, PublishSite, UnpublishSite, RestoreSiteVersion
│   ├── Queries/            # GetOrganizerSite, GetPublishedSite
│   └── Support/            # SiteBlockValidator, SiteBlockDefaults, PublishedSitePresenter
├── Contracts/              # PublishedSiteReader (consumed by Ai module)
├── Domain/                 # SiteBlockType, SiteStatus, Events\EventSitePublished
├── Http/
│   ├── Controllers/        # OrganizerEventSiteController, Public\PublicEventSiteController
│   └── Requests/           # SaveSiteDraftRequest, PublishSiteRequest
├── Infrastructure/Persistence/Models/   # EventSite, EventSiteVersion
├── Providers/EventSitesServiceProvider.php
└── Routes/api.php

app/Modules/Ai/
├── Application/
│   ├── Actions/            # AnswerEventQuestion, GenerateEventInsight, RebuildEventIndex
│   ├── Jobs/               # RebuildEventKnowledgeIndexJob
│   ├── Queries/            # GetAssistantUsage
│   ├── Retrieval/          # KnowledgeChunker, LexicalCandidateFinder, CosineReranker
│   └── Support/            # PromptBuilder, GroundedAnswerParser, EventMetricsPayload, AiLimiter
├── Contracts/              # LlmProvider, EmbeddingProvider, KnowledgeRetriever, KnowledgeSourceProvider
├── Domain/                 # AiAnswer, AiUsage, Events\AssistantConfigured
├── Http/
│   ├── Controllers/        # AssistantConfigController, AiInsightController, Public\PublicAssistantController
│   └── Requests/
├── Infrastructure/
│   ├── Adapters/Hosted/HostedLlmProvider.php, HostedEmbeddingProvider.php
│   ├── Adapters/SelfHosted/SelfHostedLlmProvider.php, SelfHostedEmbeddingProvider.php
│   ├── Persistence/Models/  # EventAssistantSetting, EventKnowledgeChunk, AssistantConversation, AssistantTurn, EventInsightSummary
│   └── Secrets/EnvironmentAiSecretLoader.php
├── Testing/                # FakeLlmProvider, FakeEmbeddingProvider
├── Providers/AiServiceProvider.php
└── Routes/api.php

config/ai.php
database/migrations/2026_08_04_0000{01..05}_*.php
lang/{en,ar}/event_sites.php, lang/{en,ar}/ai.php
resources/js/pages/tenant/events/SiteBuilder.tsx
resources/js/pages/tenant/events/AiInsights.tsx
resources/js/pages/public/site/EventSite.tsx
resources/js/components/event-site/    # block editors + block renderers
resources/js/components/ai/AssistantPanel.tsx
tests/{Unit,Feature,Integration,Contract}/{EventSites,Ai}/
```

**Structure Decision**: Follow the established modular monolith layout. Two new
modules registered in
[app/Providers/ModuleServiceProvider.php](../../app/Providers/ModuleServiceProvider.php),
each with `Routes/api.php` required from
[routes/api.php](../../routes/api.php) inside the existing `v1` prefix, plus
Inertia web routes in [routes/web.php](../../routes/web.php) for the builder,
the insights page and the public site. Frontend follows existing builder pages
such as [Builder.tsx](../../resources/js/pages/tenant/registration/Builder.tsx).

## Complexity Tracking

No Constitution Check gate failed; no justified violation is recorded.
