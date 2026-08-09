# Contract: Event Site API

All organizer operations sit under `api/v1/tenant/events/{event_id}/site` with the
middleware stack already used by
[Events/Routes/api.php](../../../app/Modules/Events/Routes/api.php):
`auth:sanctum`, `throttle:phase1-organizer`, `tenant.context.clear`,
`tenant.context`, plus a per-route `permission:...,tenant` and `idempotency` on
writes. Errors use the platform problem-details format. `event_id` is always
resolved inside the trusted tenant scope; a foreign event returns `404`.

## Organizer operations

### GET `/api/v1/tenant/events/{event_id}/site`

Permission: `event.view`.

Returns the draft and publication state.

```json
{
  "data": {
    "status": "draft",
    "page_mode": "single",
    "draft_revision": 7,
    "draft_blocks": [ { "id": "b_hero_1", "type": "hero", "visible": true, "content_en": {}, "content_ar": {}, "options": {}, "refs": {} } ],
    "settings": { "show_assistant": true, "seo": { "en": {}, "ar": {} } },
    "live_version": { "id": 12, "version": 3, "published_at": "2026-08-04T10:00:00.000000Z" },
    "public_url": "/en/events/zonetec-summit-2026",
    "publish_blockers": []
  }
}
```

When no site exists yet the endpoint creates and returns a draft seeded from
existing event data (idempotent first-open behavior).

### PUT `/api/v1/tenant/events/{event_id}/site/draft`

Permission: `event.manage`. Idempotent by `draft_revision`.

Request:

```json
{
  "draft_revision": 7,
  "blocks": [ { "id": "b_hero_1", "type": "hero", "visible": true, "content_en": { "title": "..." }, "content_ar": { "title": "..." } } ],
  "settings": { "show_assistant": true }
}
```

Responses: `200` with the saved draft and incremented `draft_revision`; `409`
with code `event_site.stale_revision` when `draft_revision` does not match the
stored value (concurrent editing); `422` with per-block field paths
(`blocks.0.content_en.title`) for schema violations.

### POST `/api/v1/tenant/events/{event_id}/site/publish`

Permission: `event.publish`. Idempotent.

Publishes the current draft. Responses: `201` with the new version;
`200` with the existing version when the draft hash already matches the live
version (repeat publish); `422` with `publish_blockers` when required content is
missing.

```json
{ "data": { "version": 4, "published_at": "2026-08-04T11:00:00.000000Z", "blocks_hash": "…", "public_url": "/en/events/zonetec-summit-2026" } }
```

Side effects: audit record `event_site.published`; dispatches the knowledge index
rebuild when the assistant is enabled.

### POST `/api/v1/tenant/events/{event_id}/site/unpublish`

Permission: `event.publish`. Idempotent. Stops public site rendering, leaves
registration and agenda pages untouched, audits `event_site.unpublished`.

### GET `/api/v1/tenant/events/{event_id}/site/versions`

Permission: `event.view`. Cursor-paginated version history with `version`,
`status`, `published_at`, `published_by`, `block_count`.

### POST `/api/v1/tenant/events/{event_id}/site/versions/{version_id}/restore`

Permission: `event.publish`. Idempotent. Copies that version's blocks into the
draft as a new editable draft, increments `draft_revision`, never mutates
history, audits `event_site.version_restored`. Returns the new draft.

### POST `/api/v1/tenant/events/{event_id}/site/media`

Permission: `event.manage`. Multipart image upload for block images. Validates
mime type and size, stores under a tenant/event scoped path on the public disk,
returns `{ "data": { "path": "event-sites/{tenant}/{event}/…" } }`.

## Public operation

### GET `/api/v1/public/events/{event_slug}/site`

Middleware: `throttle:public-event`, `public.event.context.clear`,
`public.event.context`. No authentication. Tenant and event come from the trusted
public event context; the request body and query string never carry scope.

Returns the live published version only:

```json
{
  "data": {
    "event": { "slug": "zonetec-summit-2026", "name": { "en": "…", "ar": "…" }, "start_at": "…", "end_at": "…", "timezone": "Asia/Riyadh" },
    "theme": { "colors": {}, "logo_path": null },
    "blocks": [ { "id": "b_hero_1", "type": "hero", "content_en": {}, "content_ar": {}, "options": {}, "resolved": {} } ],
    "assistant": { "enabled": true, "display_name": { "en": "Event assistant", "ar": "مساعد الحدث" }, "greeting": { "en": "…", "ar": "…" } },
    "register_url": "/en/events/zonetec-summit-2026/register"
  }
}
```

Rules: only `visible` blocks are returned; `resolved` carries live agenda,
speaker, venue and gallery data at render time; hidden and draft-only content is
absent; unpublished sites and non-shareable events return `404` without
disclosing existence; no organizer, tenant-internal or credential field appears.

## Inertia web routes

- `GET /{locale}/tenant/events/{event_id}/site` — builder page (`event.manage`).
- `GET /{locale}/tenant/events/{event_id}/site/insights` — AI insights page (`reports.view`).
- `GET /{locale}/events/{event_slug}` — public event site (anonymous).

## Compatibility

Every route is new. No existing request or response shape changes, so this is an
additive contract change requiring no version bump.
