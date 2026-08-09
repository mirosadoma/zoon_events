# EventSites module

Owns per-event public website drafts, immutable published versions, block validation, public rendering payloads, and site media.

## Owned tables

- `event_sites`
- `event_site_versions`

## Exposed contracts

- `App\Modules\EventSites\Contracts\PublishedSiteReader` — published block text for the Ai knowledge index (no direct table access from Ai)

## Consumed contracts

- Events models and public event resolution (`ShareablePublicEventResolver`, branding)
- Audit (`AuditWriter`) for publish / unpublish / restore
- Tenancy context and RBAC (`event.view`, `event.manage`, `event.publish`)

## Key surfaces

- Organizer API: `api/v1/tenant/events/{event_id}/site*`
- Public API: `api/v1/public/events/{event_slug}/site`
- Builder: `/{locale}/tenant/events/{event_id}/site`
- Public page: `/{locale}/events/{event_slug}`
