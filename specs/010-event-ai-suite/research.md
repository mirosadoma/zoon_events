# Phase 0 Research: Event Website Builder and AI Assistance

All unknowns from the plan's Technical Context are resolved below. Each decision
records what was chosen, why, and what was rejected.

## R1 — Retrieval storage and ranking on MySQL 8.4

**Decision**: Store each knowledge chunk in `event_knowledge_chunks` with its
text, language, source type/identifier and a JSON embedding vector. Retrieval is
two-stage: a `FULLTEXT` (or `LIKE` fallback for very short queries and Arabic
tokenization gaps) candidate query scoped to `tenant_id` + `event_id` returning
up to 50 candidates, then in-PHP cosine similarity rerank over the candidates'
stored vectors, returning the top 5 within a maximum context character budget.
Retrieval sits behind a `KnowledgeRetriever` contract in the `Ai` module.

**Rationale**: MySQL 8.4 has no `VECTOR` type or native ANN index, and the corpus
is small and naturally partitioned by event (tens to low hundreds of chunks, and
the plan caps at ~1,000). A scoped lexical prefilter plus cosine rerank over at
most 50 vectors is a few milliseconds of PHP work and comfortably meets the
300ms retrieval target without new infrastructure. Keeping it behind a contract
means a future vector engine is a driver swap, not a contract change. Embeddings
are optional: when no embedding provider is enabled, the lexical stage alone
still produces grounded citations, so the assistant degrades in quality rather
than breaking.

**Alternatives considered**:
- Dedicated vector database or search engine (pgvector, OpenSearch, Qdrant):
  rejected because it adds an operational dependency that every on-premise
  customer would have to run, violating the parity intent for no measurable
  benefit at this corpus size.
- Upgrading to MySQL 9 for `VECTOR`: rejected because the platform baseline is
  MySQL 8.4 LTS and a database major upgrade is far outside this feature.
- Full in-memory scan of all chunks per query without a lexical prefilter:
  rejected because it scales with total chunks per event and wastes work; the
  prefilter bounds the rerank set deterministically.

## R2 — Model provider adapter shape

**Decision**: Two contracts in `app/Modules/Ai/Contracts`: `LlmProvider`
(`complete(AiCompletionRequest): AiCompletionResult`) and `EmbeddingProvider`
(`embed(array $texts, string $locale): array`), each resolved through a registry
keyed by config, exactly mirroring
[PaymentsServiceProvider.php](../../app/Modules/Payments/Providers/PaymentsServiceProvider.php).
Drivers: `fake` (default, deterministic), `hosted` (external HTTP API), and
`self_hosted` (OpenAI-compatible local endpoint). Every driver enforces the
configured timeout, at most one retry on a safe failure, a maximum request size,
mapped errors, and telemetry through the existing pipeline.

**Rationale**: The registry-plus-config pattern is already proven in this
codebase for payments, notifications and ACS, so operators and tests behave
predictably. A single OpenAI-compatible HTTP shape covers both hosted and common
self-hosted runtimes, so on-premise parity costs one driver rather than a
separate integration.

**Alternatives considered**:
- A vendor SDK dependency: rejected because it pins the platform to one vendor,
  adds a dependency to audit, and provides nothing the built-in HTTP client and
  a narrow contract do not.
- One combined provider interface for completion and embeddings: rejected because
  deployments legitimately mix them (for example self-hosted embeddings with no
  completion provider enabled), and separate contracts allow independent
  degradation.

**Failure and degradation mapping**: disabled (no network or no provider) →
explicit unavailable state; timeout → unavailable with retry hint; provider
4xx → unavailable plus operator-visible configuration warning; provider 5xx →
one retry then unavailable. No path fabricates an answer.

## R3 — Grounding, refusal and prompt-injection resistance

**Decision**: The prompt is assembled server-side only, from a fixed system
instruction plus numbered retrieved chunks plus the visitor's message. Retrieved
content is delimited and explicitly labeled as untrusted reference data. The
model must answer only from the numbered chunks and must return the chunk numbers
it used; an answer whose cited numbers are absent, or with no citations at all,
is converted into the configured unavailable/fallback response. Event scope is
never taken from the request body: it comes from the trusted public event
context.

**Rationale**: Injection resistance comes from scope enforcement outside the
model (retrieval is already filtered to one event, and citations are validated
against what was actually retrieved), not from instructing the model to behave.
Even a fully compromised model response cannot widen data access.

**Alternatives considered**:
- Trusting model self-restraint through instructions alone: rejected because it
  fails the isolation requirement under adversarial input.
- Client-side prompt assembly: rejected outright; the client could then choose
  its own context and scope.

## R4 — Analytics payload minimization

**Decision**: A dedicated `EventMetricsPayload` assembler builds an explicit
allow-list of aggregate keys from existing sources
([DashboardOverviewBuilder.php](../../app/Modules/AdminConsole/Application/DashboardOverviewBuilder.php),
[EventReportViewModel.php](../../app/Modules/AdminConsole/ViewModels/Reports/EventReportViewModel.php),
[GetEventZoneOccupancyQuery.php](../../app/Modules/Scanning/Application/Queries/GetEventZoneOccupancyQuery.php),
[GetCheckInSummaryQuery.php](../../app/Modules/Scanning/Application/Queries/GetCheckInSummaryQuery.php)).
The payload is stored with the generated summary so the output is reproducible,
and an automated test asserts that no key outside the allow-list and no
attendee-identifying value can appear.

**Rationale**: An allow-list fails closed: a new metric is invisible to the model
until deliberately added, which is the correct default for PDPL. Storing the
payload makes every AI statement auditable against the numbers that produced it.

**Alternatives considered**:
- Passing the whole report view model with a deny-list: rejected because any new
  field would leak by default.
- Giving the model query access to the database: rejected as an unacceptable
  isolation and injection risk.

## R5 — Site draft and version model

**Decision**: `event_sites` holds one row per event with the editable draft block
set as JSON, publication status and a pointer to the live version;
`event_site_versions` holds immutable published snapshots (full block set, block
count, publisher, publish time, status). Publishing inserts a version and
repoints the site inside one transaction; published rows reject updates at the
model level. Restoring copies an old version's blocks into the draft.

**Rationale**: This is the registration-form pattern already accepted in this
codebase (`RegistrationForm` + `RegistrationFormVersion` with
`events.active_form_version_id`), so publish semantics, immutability and history
behave consistently and reviewers recognize it. JSON blocks avoid a table per
block type while the block schema is still evolving; validation is enforced by a
server-side validator rather than by column types.

**Alternatives considered**:
- Normalized `site_blocks` rows: rejected for now because ordering, reordering
  and atomic version snapshots become multi-row migrations for no query benefit —
  blocks are always read as a whole page.
- Single mutable row with no versions (the email-template pattern): rejected
  because the spec requires immutable published versions and history.

## R6 — Public addressing and rendering

**Decision**: Serve the public site at `/{locale}/events/{event_slug}` (Inertia
page) reusing the existing resolution used by public event pages: verified host
plus slug through `DatabasePublicEventContextResolver` with the shareable-status
slug fallback, and reusing the branding theme already applied to the
registration page. The public JSON contract lives at
`api/v1/public/events/{event_slug}/site` behind the existing
`public.event.context` middleware and `throttle:public-event`.

**Rationale**: Reuses an accepted trust path, so no new domain verification,
certificate or tenant-resolution surface is introduced, and the site
automatically inherits the branding, locale and not-found behavior that public
event pages already have.

**Alternatives considered**:
- New per-site subdomains or custom-domain onboarding: rejected as out of scope;
  the spec's assumptions explicitly defer domain verification workflows.
- Rendering sites through a Blade-only path: rejected because the rest of the
  public experience is Inertia/React and the chat panel needs the same runtime.

## R7 — Indexing trigger and idempotency

**Decision**: `RebuildEventKnowledgeIndexJob` implements `TenantAwareJob`, runs
on the `foundation` queue, and is dispatched after site publish, after assistant
enable, and after indexed event data changes. It rebuilds the whole event index
inside a transaction (delete-then-insert for that tenant/event scope) and records
an index version and timestamp on the assistant settings row, so concurrent or
repeated runs converge on one consistent index and never leave a partial state.

**Rationale**: Whole-event rebuild is simple, idempotent and cheap at this corpus
size; incremental diffing would add correctness risk for no measurable gain.
`TenantAwareJob` plus `RestoreTenantContext` is the accepted way this codebase
restores tenant scope inside queued work.

**Alternatives considered**:
- Incremental per-source upserts: rejected as premature; can be added later
  behind the same job contract.
- Synchronous indexing during the publish request: rejected because it couples
  publish latency to embedding-provider latency and could fail a publish for a
  non-essential reason.

## R8 — Limits, cost control and caching

**Decision**: Configuration in `config/ai.php` defines per-visitor question
throttle, per-event daily question ceiling, maximum context characters, maximum
answer tokens and insight cache freshness (default 15 minutes). Limits are
enforced server-side before any provider call; the assistant returns a localized
throttled state when exceeded. Insight summaries are cached per event and metric
window and reused within the freshness period.

**Rationale**: Provider spend is the main new operational risk, so ceilings are
enforced before the call and are configuration rather than code. Caching removes
the common repeat-view cost entirely.

**Alternatives considered**:
- Client-side throttling only: rejected because it fails open.
- No ceiling with alerting after the fact: rejected because cost damage is
  already done by the time an alert fires.
