# Feature Specification: Event Website Builder and AI Assistance

**Feature Branch**: `010-event-ai-suite`

**Created**: 2026-08-04

**Status**: Draft

**Input**: User description: "Per-event AI suite for Zoon: (1) an event website
builder that lets an organizer compose a public event site from reusable content
blocks with draft and published versions, tenant branding, Arabic and English
content, single-page first and multi-page as a later increment; (2) an AI RAG
chatbot embedded in that published event site that answers attendee questions
only from that event's own indexed content, cites its sources, refuses
out-of-scope questions, and works in Arabic and English; (3) AI analytics for
organizers that generates written insight summaries and answers natural-language
questions over already-computed event metrics using aggregates only with no
attendee personal data sent to any model. All model access must go through a
single adapter contract with an external provider for SaaS and a self-hosted
provider for on-premise deployments, network access disabled by default, and a
fake provider for tests."

**Product Phase**: Phase 9 — Event Web Presence and AI Assistance (additive
phase that depends on accepted Foundation, Phase 1 events/registration, and the
existing reporting surfaces; it does not depend on Phase 7 or Phase 8)

**Deployment Modes**: both (SaaS and on-premise)

## Clarifications

### Session 2026-08-04

- Q: Does the MVP include multi-page event sites, or single page only? → A: Single page only; multi-page is a later additive increment that must not change the published-version contract (FR-010).
- Q: What must the assistant do when the answer is not in the indexed content? → A: State that the information is unavailable, offer a per-event configurable fallback defaulting to the event registration page plus an organizer contact action, and store the unanswered question for organizer review (FR-011, FR-014, FR-018).
- Q: Who may use AI analytics — organizers only, or platform administrators across tenants? → A: Tenant organizer roles only via `reports.view`; cross-tenant platform AI analytics is out of scope (CR-002, US3).
- Q: How long are assistant transcripts retained, and can they be deleted? → A: Configurable per deployment with a 90-day default, deletable earlier on request, minimized, and excluded from provider training (CR-005, FR-019).
- Q: Can anonymous visitors use the assistant, or must they register first? → A: Anonymous visitors on the published site may use it, with server-side per-visitor throttles, per-event daily ceilings and context-size limits; it exposes no organizer capability (FR-020, CR-002).

## User Scenarios & Testing *(mandatory)*

Today an organizer can only publish a fixed registration page for an event.
This feature gives every event three additive capabilities: an organizer-composed
public event website, an attendee-facing assistant that answers questions using
only that event's own published knowledge, and an organizer-facing assistant that
explains that event's already-computed metrics in words.

All three stay inside the existing tenant and event boundary. The event website
publishes only what the organizer explicitly published, the attendee assistant
answers only from that event's indexed content, and the analytics assistant
receives aggregated metrics only — never attendee-level personal data.

Each story below is an independently testable product slice.

### User Story 1 - Build and publish an event website (Priority: P1)

An event organizer composes a public website for one event from reusable content
blocks (hero, about, agenda, speakers, venue, FAQ, sponsors, register call to
action), previews it in Arabic and English, saves drafts safely, and publishes it
to the event's public address. Published content is served to attendees while the
organizer keeps editing the next draft.

**Why this priority**: The website is the container for everything else and
delivers standalone value immediately: today organizers have no way to present an
event beyond the registration form. It also produces the published content that
the attendee assistant later indexes.

**Independent Test**: As an Event Manager, create a site for a published event,
add one block of each supported type, fill Arabic and English content, preview,
publish, then open the public URL as an anonymous visitor and confirm the
published blocks render with tenant branding in both languages while unpublished
draft edits stay invisible.

**Acceptance Scenarios**:

1. **Given** an organizer-owned event, **When** an authorized Event Manager opens the site builder for the first time, **Then** a draft site is created for that event with default blocks derived from existing event data (name, dates, location, main image, agenda, register link) and nothing is publicly visible yet.
2. **Given** a draft site, **When** the organizer adds, reorders, edits, hides or removes a block and saves, **Then** the draft is persisted with its block order and bilingual content, and the currently published version is unchanged.
3. **Given** a draft site with required content complete, **When** the organizer publishes it, **Then** a new immutable published version is recorded, it becomes the live version for that event, and the previous published version is retained as history.
4. **Given** a draft site missing required content (no hero title in either language, or a broken register call to action), **When** the organizer attempts to publish, **Then** publication is rejected with field-specific, localized guidance and no version is created.
5. **Given** a published site, **When** an anonymous visitor opens the event's public site address, **Then** only the published version renders, with tenant branding, correct locale and direction, and no organizer-only or draft data.
6. **Given** a published site, **When** the organizer unpublishes it, **Then** the public address stops serving site content, the registration page remains reachable, and the action is audited.
7. **Given** an event that is not in a shareable status, **When** a visitor requests its site, **Then** the request is rejected as not found without revealing whether the event exists.

---

### User Story 2 - Ask the event assistant (Priority: P2)

A visitor on the published event website opens a chat panel and asks questions in
Arabic or English. The assistant answers using only that event's indexed
knowledge — published site blocks, event details, agenda and speakers, venue and
zone information, and registration/ticket facts — and shows which sources it
used. When the answer is not in the indexed knowledge, it says so and points the
visitor to the registration or contact path instead of guessing.

**Why this priority**: It reduces organizer support load and answers the majority
of repetitive attendee questions, but it depends on published site content
existing first.

**Independent Test**: Publish an event site with an agenda and an FAQ block; ask
five questions whose answers exist in that content and one question whose answer
does not; confirm the five answers are grounded with visible citations to that
event's content, the sixth is an explicit "not available" response, and no answer
ever contains content from another event or tenant.

**Acceptance Scenarios**:

1. **Given** a published event site, **When** the organizer enables the assistant for that event, **Then** the event's knowledge is indexed from its published content and the assistant becomes available on the public site.
2. **Given** an indexed event, **When** a visitor asks a question whose answer exists in the indexed content, **Then** the assistant answers in the question's language and lists the event content sources it used.
3. **Given** an indexed event, **When** a visitor asks something outside the indexed content, **Then** the assistant states that it does not have that information, offers the configured fallback (registration page or organizer contact), and the unanswered question is recorded for the organizer.
4. **Given** an indexed event, **When** a visitor asks about another event, another tenant, internal operations, attendee personal data, or attempts to override the assistant's instructions, **Then** the request is refused and no data outside that event's indexed content is used or revealed.
5. **Given** the organizer changes published site content or the agenda, **When** the change is published, **Then** the index is refreshed and later answers reflect the new content without manual re-indexing.
6. **Given** the model provider is unavailable, disabled, or times out, **When** a visitor asks a question, **Then** the panel shows an explicit unavailable state with the fallback path, no fabricated answer is returned, and the failure is observable to operators.
7. **Given** a visitor sends many questions quickly, **When** the configured per-visitor and per-event limits are exceeded, **Then** further requests are throttled with a clear localized message and the event's cost ceiling is not exceeded.

---

### User Story 3 - Explain event performance with AI analytics (Priority: P3)

An authorized organizer opens an event's report and receives a short written
summary of what the numbers mean — notable changes, funnel drop-off, capacity and
occupancy pressure, and suggested next actions — and can ask follow-up questions
in natural language about that event's metrics. Every statement is derived from
the same already-computed metrics shown on screen.

**Why this priority**: It builds on metrics that already exist, so it is the
lowest-risk slice, and it is most useful once organizers have events with real
traffic.

**Independent Test**: For an event with registrations, orders and check-ins,
request an insight summary and confirm every figure it states matches the report
figures; then ask a follow-up question and confirm the answer only uses the same
aggregates, contains no attendee names or contact data, and is reproducible.

**Acceptance Scenarios**:

1. **Given** an event with existing metrics, **When** an authorized organizer requests an insight summary, **Then** the summary is generated from that event's aggregated metrics only, states figures consistent with the report, and is labeled as AI-generated with its generation time.
2. **Given** an insight summary, **When** the organizer asks a follow-up question about that event's metrics, **Then** the answer uses only the aggregated metric set for that event and declines questions requiring data outside it.
3. **Given** any analytics request, **When** the metric payload is prepared, **Then** it contains aggregates and non-identifying breakdowns only; attendee names, emails, phone numbers, identity data and free-text answers are never included.
4. **Given** an event with too little data, **When** a summary is requested, **Then** the response states that available data is insufficient rather than inventing trends.
5. **Given** an actor without reporting permission, **When** an insight or follow-up is requested, **Then** the request is denied and audited without returning any metric content.
6. **Given** the model provider is unavailable or model access is disabled, **When** a summary is requested, **Then** the numeric report remains fully usable and the AI section shows an explicit unavailable state.
7. **Given** a summary was generated, **When** the same event and metric window are requested again within the configured freshness period, **Then** the stored summary is reused instead of calling the provider again.

### Edge Cases

- **Concurrent editing**: Two organizers editing the same draft site produce a detectable conflict; the second save is rejected or merged explicitly rather than silently overwriting.
- **Publishing race**: Repeated or concurrent publish attempts for the same draft produce exactly one new published version.
- **Address resolution**: An event site is served on the same trusted host-plus-slug resolution already used for public event pages; a custom host that is not verified for that tenant never serves that event's site.
- **Deleted or archived event**: Archiving, cancelling or unpublishing an event removes public site and assistant availability without deleting version history or audit records.
- **Blocks referencing live data**: Agenda, speaker, venue and registration blocks reflect current event data at render time; if that data is removed, the block degrades to an empty state instead of failing the page.
- **Bilingual gaps**: If a block has content in only one language, the site renders the available language for that block with a clear fallback rule and the publish check warns the organizer.
- **Index staleness**: If indexing fails or lags, the assistant either answers from the last good index with a stated freshness or reports unavailability; it never mixes indexes across events.
- **Prompt injection through content**: Text inside event content, visitor messages or uploaded copy cannot change the assistant's instructions, its event scope, or cause it to reveal system instructions.
- **Cross-tenant isolation**: Site versions, blocks, knowledge chunks, embeddings, transcripts, insight summaries, caches, jobs, exports and provider requests carry trusted tenant and event scope; no client-supplied identifier establishes trust.
- **Missing permission**: Builder, assistant configuration, transcript review and insight surfaces hide controls for convenience but every read and write is independently denied by the authorization boundary.
- **Audit failure**: Publish, unpublish, assistant enable/disable, configuration change, transcript deletion and privileged reads are not reported complete when the required audit record cannot be persisted.
- **Model unavailability and network policy**: With network access disabled, or with no configured provider, all AI surfaces degrade explicitly; the website builder and existing reports remain fully functional.
- **Cost and abuse limits**: Per-event daily question ceilings, per-visitor throttles and maximum context sizes are enforced server-side; exceeding them degrades gracefully instead of failing open.
- **Personal data in visitor messages**: Visitors may type personal data; transcripts are minimized, retention-bounded, deletable on request, and never used to change the index without organizer action.
- **Localization and accessibility**: Builder, public site, chat panel and insight surfaces are complete in Arabic/RTL and English/LTR with locale-aware dates and numbers and equivalent keyboard and screen-reader behavior.

## Requirements *(mandatory)*

### Functional Requirements

**Event website builder**

- **FR-001**: An authorized organizer MUST be able to create exactly one website per event, edit it as a draft, and view its publication state.
- **FR-002**: The system MUST support the block types hero, rich text/about, agenda, speakers, venue and location, FAQ, sponsors/logos, image gallery, and register call to action; each block MUST carry Arabic and English content where it contains text.
- **FR-003**: The organizer MUST be able to add, edit, reorder, hide and remove blocks in the draft, and the draft MUST be savable at any time without affecting the published version.
- **FR-004**: Publishing MUST create an immutable published version containing the full block set at that moment, MUST make it the live version for the event, and MUST retain previous versions as history.
- **FR-005**: The system MUST validate before publication that required content exists (a hero title in at least one language, a working registration call to action, and no block referencing a deleted resource) and MUST reject publication with field-specific localized errors otherwise.
- **FR-006**: The organizer MUST be able to unpublish a site; unpublishing MUST stop public site rendering without affecting the event's registration, agenda or venue-map pages.
- **FR-007**: The public event site MUST render only the live published version, apply the event's validated branding and theme, and support Arabic/RTL and English/LTR.
- **FR-008**: The public event site MUST be reachable only for events in a shareable status, using the same trusted public event address resolution as existing public event pages; all other requests MUST return not found without disclosure.
- **FR-009**: Site version history MUST be viewable by authorized organizers, and an earlier version MUST be restorable into the draft as a new editable draft rather than by mutating history.
- **FR-010**: The MVP MUST deliver a single-page site; multi-page sites MUST be an additive later increment that does not change the published-version contract for single-page sites.

**Attendee assistant (RAG)**

- **FR-011**: An authorized organizer MUST be able to enable or disable the assistant per event, set its display name, its greeting, and its fallback action (registration page or organizer contact).
- **FR-012**: The system MUST build a per-event knowledge index from that event's published site blocks, core event fields, agenda items and speakers, venue and zone descriptions, and registration/ticket availability facts.
- **FR-013**: Every indexed knowledge unit MUST carry its tenant, event, source type and source identifier, and retrieval MUST filter by the trusted tenant and event of the current request.
- **FR-014**: Answers MUST be generated only from retrieved knowledge for that event and MUST include the sources used; when retrieval returns nothing sufficient, the assistant MUST state that the information is unavailable and offer the configured fallback.
- **FR-015**: The assistant MUST answer in the language of the question for Arabic and English, and MUST NOT mix languages within a single answer.
- **FR-016**: The system MUST refuse requests that ask for other events, other tenants, internal operations, attendee personal data, or that attempt to alter the assistant's instructions or scope.
- **FR-017**: The index MUST be refreshed automatically when the site is published, the assistant is enabled, or indexed event data changes; refresh MUST be idempotent and MUST NOT leave the index partially replaced.
- **FR-018**: The system MUST record each conversation turn with its event scope, question, answer, cited sources, whether it was answered, latency and token/cost accounting, and MUST flag unanswered questions for organizer review.
- **FR-019**: Authorized organizers MUST be able to review unanswered questions and assistant usage for their event, and MUST be able to delete a transcript on request.
- **FR-020**: The system MUST enforce server-side per-visitor rate limits, per-event daily question ceilings and maximum retrieved-context size, and MUST degrade with an explicit localized message when a limit is reached.
- **FR-021**: When the model provider is disabled, unreachable or times out, the assistant MUST return an explicit unavailable state with the fallback path and MUST NOT return a fabricated answer.

**Organizer AI analytics**

- **FR-022**: An authorized organizer MUST be able to request a written insight summary for one event, generated from that event's already-computed aggregated metrics.
- **FR-023**: The metric payload sent to any model MUST contain aggregates and non-identifying breakdowns only; attendee identities, contact data, identity-verification data, payment instrument data and free-text form answers MUST be excluded.
- **FR-024**: The organizer MUST be able to ask natural-language follow-up questions restricted to the same event metric set, and out-of-scope questions MUST be declined.
- **FR-025**: Every AI-generated analytics output MUST be labeled as AI-generated, MUST record the metric window and generation time, and MUST be reproducible from the stored metric payload.
- **FR-026**: When available data is insufficient for a conclusion, the output MUST say so rather than assert a trend.
- **FR-027**: Generated summaries MUST be cached per event and metric window for a configured freshness period and reused instead of repeating provider calls.
- **FR-028**: The numeric report MUST remain fully functional when AI analytics is unavailable or disabled.

**Model access, adapter and configuration**

- **FR-029**: All model and embedding access MUST go through one versioned adapter contract with interchangeable providers; no feature code may call a provider directly.
- **FR-030**: The system MUST support at least an external hosted provider (for SaaS), a self-hosted/local provider (for on-premise), and a deterministic fake provider used by tests and by default configuration.
- **FR-031**: Outbound model calls MUST be disabled by default and enabled only by explicit configuration, and MUST define timeout, bounded retry, request size limits, error mapping and observability.
- **FR-032**: Provider credentials MUST be referenced indirectly through the existing secret-reference mechanism and MUST never be committed, logged or returned to clients.
- **FR-033**: Provider selection, limits and retention periods MUST be configurable per deployment, and the active provider and degraded state MUST be visible to operators through existing health/telemetry surfaces.

**Cross-cutting experience**

- **FR-034**: Builder, public site, chat panel, transcript review and insight surfaces MUST provide loading, empty, validation, conflict, throttled, degraded, forbidden and retry states, and MUST prevent accidental duplicate submissions.
- **FR-035**: All user-visible content, including AI unavailability, refusal and throttling messages, MUST be available in Arabic and English with RTL/LTR support and tenant branding.
- **FR-036**: All new surfaces MUST provide equivalent keyboard, focus, screen-reader and responsive behavior in Arabic and English.

### Constitutional Requirements *(mandatory)*

- **CR-001 Tenant Scope**: Event sites, site versions, blocks, assistant configuration, knowledge chunks, embeddings, conversations, transcript turns, insight summaries, uploaded site media, indexing jobs, caches, telemetry and provider requests MUST carry trusted `tenant_id` and `event_id` scope resolved from the authenticated session or the trusted public event context. Retrieval, analytics payload assembly and every read/write MUST filter on that scope. No client-supplied tenant or event identifier establishes trust. Negative tests MUST prove that a visitor on event A can never retrieve, cite or receive content from event B or another tenant, including through background jobs, caches and exports.
- **CR-002 RBAC**: Actors are Event Manager / Organizer Admin (`event.view` to read site state, `event.manage` to edit the draft and configure the assistant, `event.publish` to publish, unpublish and restore versions), Reports viewer (`reports.view` for AI insights and assistant usage review), Auditor (`audit.view`), anonymous public visitor (published site read and assistant question only, no organizer data), and Platform Admin only through explicit audited privileged paths. No new permission names are introduced; existing tenant permissions are reused. Least privilege applies; UI visibility is never authorization; the public assistant endpoint grants no organizer capability.
- **CR-003 Auditability**: Site creation, draft save, publish, unpublish, version restore, custom-address change, assistant enable/disable and configuration change, index rebuild, transcript review, transcript deletion, insight generation, and any privileged cross-tenant read MUST record actor, tenant, event, action, target, correlation identifier, timestamp and outcome. A required audited action MUST NOT be reported complete when its audit record cannot be persisted.
- **CR-004 Credential Security**: This feature issues and validates no credentials. Published sites, the assistant, indexes and analytics payloads MUST NOT contain QR payloads, signing material, key identifiers, scanner or device secrets, wallet payloads, or attendee credential identifiers, and MUST NOT create a second trust path into registration or check-in.
- **CR-005 Data and PDPL**: Site content is organizer-authored marketing content. Assistant transcripts may contain visitor-typed personal data and are collected for the sole purpose of answering event questions and improving event content; they MUST be minimized, retention-bounded by configuration, deletable on request, excluded from model training, and never sent to a provider beyond the current answer context. Analytics payloads MUST be aggregates only. Residency follows tenant policy: when a deployment forbids external processing, model access MUST use the self-hosted provider or remain disabled; no personal data may cross a border implicitly through a provider call.
- **CR-006 API and Integrations**: Site builder, publication, public site read, assistant question, index management, transcript review and insight generation MUST be exposed as documented, versioned, tenant-scoped contracts with validation, deterministic errors, idempotent publish/index operations and rate limits. Model and embedding providers MUST sit behind one adapter contract with timeout, bounded retry, idempotency where applicable, error mapping, telemetry, a fake provider for tests, and no network access by default.
- **CR-007 White-Label and Localization**: Published sites, chat panel and organizer surfaces MUST honor the event's validated branding and theme with no tenant-specific code fork, and MUST fully support Arabic/English, RTL/LTR, locale-aware dates, numbers and currencies, and equivalent accessibility. AI outputs MUST respect the requested locale.
- **CR-008 Deployment Parity**: Website builder, publication, indexing, retrieval, transcript handling, analytics assembly, RBAC, isolation and audit behavior MUST be identical in SaaS and on-premise. The only permitted difference is which provider satisfies the adapter contract: an external hosted provider for SaaS and a self-hosted provider on-premise. With no provider or with network disabled, the site builder and numeric reports MUST remain fully functional and AI surfaces MUST degrade explicitly.
- **CR-009 Automated Verification**: Required coverage includes block schema validation; draft/publish/unpublish/restore lifecycle and version immutability; concurrent save and publish idempotency; public rendering of published-only content; not-found behavior for non-shareable events; index build and refresh idempotency; retrieval scope isolation across events and tenants; grounded-answer, citation, refusal and language behavior; prompt-injection resistance; rate limit and ceiling enforcement; provider timeout/failure degradation using the fake provider; analytics payload PII exclusion; insight caching; RBAC denial on every endpoint; audit-failure atomicity; Arabic/English, RTL and accessibility checks; and API contract compatibility.
- **CR-010 Phase Alignment**: This is an additive phase after Phase 6. It depends on accepted Foundation tenant context, RBAC, audit, idempotency, telemetry, storage and adapter boundaries; Phase 1 events, registration and orders; and the existing reporting and occupancy surfaces. It MUST NOT weaken any earlier contract, MUST NOT alter registration, credential, scanning or access-control behavior, and MUST NOT become a prerequisite for Phase 7 or Phase 8 work.

### Key Entities *(include if feature involves data)*

- **Event Site**: One website per event; owns its draft block set, publication state, current live version reference and settings.
- **Event Site Version**: An immutable snapshot of the block set published at a moment in time, with publisher, timestamp and status (published or superseded).
- **Site Block**: One ordered content unit inside a draft or version; has a type, visibility, bilingual content and optional references to live event data.
- **Assistant Configuration**: Per-event assistant settings: enabled state, display name, greeting, fallback action, limits, and index freshness state.
- **Event Knowledge Chunk**: A retrievable unit of that event's published knowledge with source type, source identifier, language, text, and its retrieval representation.
- **Assistant Conversation**: A visitor session with the assistant for one event, bounded by scope, locale and lifecycle timestamps.
- **Assistant Turn**: One question/answer pair with cited sources, answered/unanswered outcome, latency and cost accounting.
- **Insight Summary**: An AI-generated narrative for one event and metric window, with the aggregated payload it was derived from, generation time and provider identity.
- **Model Provider Adapter**: The adapter boundary describing completion and embedding operations, limits, failure modes and observability, with hosted, self-hosted and fake implementations.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A trained organizer can build and publish a complete single-page event site in Arabic and English in under 20 minutes without developer help.
- **SC-002**: 100% of publicly served site content comes from the live published version; 0 draft-only or organizer-only fields appear on the public site.
- **SC-003**: 95% of published event site page loads present usable content within 2 seconds under the agreed operating load.
- **SC-004**: At least 80% of attendee questions whose answers exist in the event's published knowledge are answered correctly with at least one correct citation, measured on a fixed evaluation set per language.
- **SC-005**: 100% of questions whose answers are absent from the indexed content produce an explicit unavailable response with a fallback, and 0 fabricated facts are accepted in the evaluation set.
- **SC-006**: 95% of assistant answers are returned within 5 seconds, and retrieval for a 500-chunk event index completes within 300 milliseconds.
- **SC-007**: Cross-scope tests produce 0 retrievals, citations or answers containing another event's or another tenant's content.
- **SC-008**: 0 attendee-identifying fields appear in any analytics payload sent to a provider, verified by automated payload inspection on every analytics path.
- **SC-009**: 100% of figures stated in a generated insight summary match the corresponding report figures for the same metric window.
- **SC-010**: With network access disabled or the provider failing, 100% of site builder and numeric report journeys still complete, and every AI surface shows an explicit unavailable state with 0 fabricated outputs.
- **SC-011**: 100% of defined publish, configuration, transcript and privileged-read actions produce a complete audit record, and none is reported successful when the audit write fails.
- **SC-012**: All critical organizer and visitor journeys complete in Arabic/RTL and English/LTR with no high-severity accessibility defect.
- **SC-013**: Assistant usage stays within configured per-event ceilings in 100% of load tests, with throttling instead of unbounded provider spend.

## Assumptions

- **Prior capabilities are accepted**: Existing tenant context, RBAC, audit, idempotency, telemetry, storage, notifications, event/registration data, reporting and occupancy metrics are reused rather than rebuilt.
- **One site per event**: Tenant-level or multi-event marketing sites, blogs and landing-page A/B testing are out of scope.
- **Addressing reuses existing resolution**: Sites are served through the existing public event address resolution (verified host plus event slug). New domain verification and certificate provisioning workflows are out of scope.
- **Organizer-authored content**: The builder composes organizer-provided text and media. AI generation of marketing copy is out of scope in this phase.
- **English and Arabic only**: Additional languages are a later increment; the schema keeps content per locale so more locales can be added without redesign.
- **Knowledge sources are internal**: The assistant indexes only the event's own platform data. Crawling external websites, uploading arbitrary documents, and human live-chat handoff are out of scope.
- **Analytics reuses existing metrics**: No new metric pipelines are introduced; AI analytics narrates the existing computed aggregates for one event at a time.
- **Costs are bounded by configuration**: Per-event ceilings, per-visitor throttles and context limits are configuration, not hard-coded business rules.
- **Retrieval quality is measured, not assumed**: Answer quality is validated against a fixed bilingual evaluation set per release rather than by subjective review.
