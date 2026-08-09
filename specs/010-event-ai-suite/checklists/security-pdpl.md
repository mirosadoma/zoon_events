# Security and PDPL Checklist: Event Website Builder and AI Assistance

**Purpose**: Release gate for the security, isolation and personal-data controls
introduced by this feature

**Created**: 2026-08-04

**Feature**: [spec.md](../spec.md) | **Plan**: [plan.md](../plan.md)

## Tenant and event isolation

- [ ] Every new table carries `tenant_id` and `event_id` with a composite foreign key to `events(tenant_id, id)`
- [ ] Organizer routes derive scope only from `tenant.context`; a foreign `event_id` returns 404
- [ ] Public routes derive scope only from `public.event.context`; scope fields in the request body are ignored
- [ ] Retrieval filters on the trusted `(tenant_id, event_id, index_version)` triple
- [ ] Index rebuild jobs restore tenant context and cannot write outside their scope
- [ ] Negative tests prove event A cannot retrieve, cite or receive event B or another tenant's content

## Authorization and audit

- [ ] `event.view`, `event.manage`, `event.publish` and `reports.view` enforced per route; no new permission invented
- [ ] The public assistant endpoint exposes no organizer capability and no organizer data
- [ ] Audit records exist for publish, unpublish, version restore, assistant configure, reindex, transcript review, transcript deletion and insight generation
- [ ] A failing audit write prevents the action from being reported complete

## Model access boundary

- [ ] All provider access goes through `LlmProvider` / `EmbeddingProvider`; no direct provider call in feature code
- [ ] `AI_ALLOW_NETWORK=false` and `AI_DEFAULT_ADAPTER=fake` are the shipped defaults
- [ ] Provider credentials are resolved through secret references, never logged or returned
- [ ] Timeout, single safe retry, request-size limit and error-code mapping are enforced
- [ ] Telemetry records outcome, latency and tokens without prompt or answer content
- [ ] Test suite performs zero outbound HTTP requests with default configuration

## Prompt and injection safety

- [ ] Prompts are assembled server-side only
- [ ] Retrieved chunks are delimited and labeled as untrusted reference data
- [ ] Citations are validated against chunks actually retrieved for that request; ungrounded answers become `unanswered`
- [ ] Event scope is never taken from the model output or the request body
- [ ] System instructions cannot be revealed or overridden by chunk or visitor content

## Personal data (PDPL)

- [ ] Analytics payloads contain only allow-listed aggregate keys; an automated scan asserts no attendee identifiers
- [ ] Small breakdown buckets are reported as counts without identifying labels
- [ ] Transcripts store a salted visitor hash, never a raw IP address
- [ ] Transcript retention default is 90 days, configurable, with a scheduled purge
- [ ] An organizer can delete a transcript on request, and the deletion is audited
- [ ] Transcripts and analytics payloads are excluded from any provider training use
- [ ] A deployment that forbids external processing can run with the self-hosted or fake provider only

## Excluded data classes

- [ ] No credential, wallet, QR or signing material is indexed, rendered or sent to a provider
- [ ] No scanner, kiosk or device secret is indexed, rendered or sent to a provider
- [ ] No attendee record, registration answer or identity-verification data is indexed
- [ ] A contract test asserts the excluded field list

## Availability and abuse

- [ ] Per-visitor throttle and per-event daily ceiling are enforced server-side before any provider call
- [ ] Maximum context size and output tokens are enforced
- [ ] Exceeding a limit degrades with a localized message and never fails open
- [ ] Provider failure yields an explicit unavailable state with zero fabricated content
- [ ] Site builder and numeric reports remain fully functional with all AI surfaces disabled

## Notes

- Items in this checklist map to CR-001 through CR-009 in the spec and to the
  Constitution Check in the plan; each must be verified by an automated test or a
  recorded manual verification before release.
