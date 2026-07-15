# Phase 6 audit action catalog

Owner: Security Engineering  
Last reviewed: 2026-07-15

Phase 0–5 action families remain in `docs/standards/audit-event-catalog.md` and
prior phase docs. Every Phase 6 row records scope, actor, target, outcome, stable
reason, correlation, channel, fingerprints, sanitized metadata, key ID, algorithm,
and HMAC. Integration secrets, credential payloads, dispute notes, dispute reason
text, decision reason text, and external references never enter audit metadata.

Marketplace audit events are multi-scoped: each mutating action writes separate
records for the `owner` and `organizer` participant tenants. Platform-initiated
actions (statement revision, dispute resolution) additionally write a `platform`
scope record. The `MarketplaceAuditEvent` DTO enforces payload sanitization at
construction time.

## Payload allowlist and redaction

The `MarketplaceAuditEvent` constructor rejects any payload key whose normalized
name contains any of the following fragments:

| Forbidden fragment | Rationale |
| --- | --- |
| `secret` | Integration credentials |
| `credential` | Authentication material |
| `password` | Authentication material |
| `token` | Session / API tokens |
| `binding` | Internal delegation bindings |
| `external_reference` | Third-party identifiers |
| `request_body` | Raw request payloads |
| `decision_reason` | Free-text owner decision reason |
| `dispute_reason` | Free-text dispute description |
| `note` | Internal/participant dispute notes |

Payload key validation is recursive; nested arrays are also checked. Any violation
throws `InvalidArgumentException`, which rolls back the enclosing audited
transaction.

## Failure semantics

All venue catalog, rental, and settlement audit writes execute inside
`AuditedTransaction::run()`. The audit write is the after-commit callback of the
transaction. If the primary mutation fails, no audit is written. If the audit write
itself fails (e.g. database constraint violation), the underlying audit writer
records the failure but does not roll back the already-committed business
transaction.

Notification and delegation-provisioning listeners dispatch via `ShouldQueue` with
`afterCommit = true`, ensuring they run only after the primary transaction commits.
Audit writes in those listeners are synchronous within the queued job's own
transaction boundary.

## Correlation IDs

Every marketplace API request generates a `correlationId` (typically a ULID). All
audit events within a single request share this correlation ID, enabling end-to-end
tracing across multi-scope writes, delegation provisioning, and settlement
generation.

The `correlation_id` is stored in audit metadata (not the action key) and is
available for log correlation, support investigation, and dispute evidence.

## `venue.*`

| Action | Outcome | Target | Scope | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- | --- |
| `venue.created` | succeeded | `marketplace_resource` | owner | `status`, `version` | Venue profile created in `draft` status |
| `venue.updated` | succeeded | `marketplace_resource` | owner | `before_version`, `after_version` | Venue profile updated with optimistic concurrency |
| `venue.status_changed` | succeeded | `marketplace_resource` | owner | `status`, `version` | Status transition (draft→active, active→suspended, etc.) |
| `venue.archived` | succeeded | `marketplace_resource` | owner | `status`, `version` | Terminal archival; invalidates catalog cache |

## `venue_asset.*`

| Action | Outcome | Target | Scope | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- | --- |
| `venue_asset.created` | succeeded | `marketplace_resource` | owner | `asset_type`, `status`, `version` | Asset added to venue |
| `venue_asset.updated` | succeeded | `marketplace_resource` | owner | `before_version`, `after_version` | Asset details updated |
| `venue_asset.availability_replaced` | succeeded | `marketplace_resource` | owner | `window_count` | All availability windows replaced atomically |
| `venue_asset.published` | succeeded | `marketplace_resource` | owner | `publication_version` | Asset published to marketplace catalog; invalidates catalog cache |
| `venue_asset.publication_withdrawn` | succeeded | `marketplace_resource` | owner | `publication_version` | Publication withdrawn from catalog; invalidates catalog cache |
| `venue_asset.retired` | succeeded | `marketplace_resource` | owner | `status`, `version` | Asset permanently retired; invalidates catalog cache |

## `rental.*`

| Action | Outcome | Target | Scope | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- | --- |
| `rental.requested` | succeeded | `marketplace_resource` | owner, organizer | `event_public_id`, `status`, `total_minor`, `currency` | Dual-scoped; written by `WriteRentalRequestedAudit` listener |
| `rental.approved` | succeeded | `marketplace_resource` | owner, organizer | `status`, `version` | Owner approved; reservations and delegation created |
| `rental.rejected` | succeeded | `marketplace_resource` | owner, organizer | `status`, `version` | Owner rejected; stable reason code in `reasonCode` field |
| `rental.cancelled` | succeeded | `marketplace_resource` | owner, organizer | `status`, `version` | Organizer cancelled before activation |
| `rental.revoked` | succeeded | `marketplace_resource` | owner, organizer | `status`, `version` | Owner revoked active rental; reservations released |

## `delegation.*`

| Action | Outcome | Target | Scope | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- | --- |
| `delegation.provisioned` | succeeded | `marketplace_resource` | owner, organizer | `status` | All delegated assets provisioned successfully |
| `delegation.degraded` | succeeded | `marketplace_resource` | owner, organizer | `status` | Partial provisioning; some adapters failed or returned `not_applicable` |
| `delegation.released` | succeeded | `marketplace_resource` | owner, organizer | `status` | All delegated asset bindings released |

## `statement.*`

| Action | Outcome | Target | Scope | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- | --- |
| `statement.generated` | succeeded | `marketplace_resource` | owner, organizer | `revision`, `rental_outcome`, `currency`, `agreed_total_minor` | Initial settlement statement generated |
| `statement.revised` | succeeded | `marketplace_resource` | owner, organizer, platform | `revision` | Platform-initiated statement revision; supersedes prior revision |
| `statement.exported` | succeeded | `marketplace_resource` | owner or organizer | `revision`, `format` | CSV export streamed to participant; sensitive read audited |

## `dispute.*`

| Action | Outcome | Target | Scope | Metadata (sanitized) | Notes |
| --- | --- | --- | --- | --- | --- |
| `dispute.opened` | succeeded | `marketplace_resource` | owner, organizer | `status`, `reason_code` | Participant opened dispute; free-text reason is **not** in audit |
| `dispute.review_started` | succeeded | `marketplace_resource` | owner, organizer, platform | `status` | Platform began formal review |
| `dispute.note_added` | succeeded | `marketplace_resource` | owner, organizer, platform | `status` | Note content is **not** in audit metadata |
| `dispute.resolved` | succeeded | `marketplace_resource` | owner, organizer, platform | `status`, `resolution_code` | Terminal resolution by platform |
| `dispute.rejected` | succeeded | `marketplace_resource` | owner, organizer, platform | `status`, `resolution_code` | Dispute rejected by platform |

## API operation → audit event mapping

Every mutating API operation and every sensitive read maps to at least one
implemented audit event:

| API operation | Permission | Audit event(s) |
| --- | --- | --- |
| Create venue | `venue.manage` | `venue.created` |
| Update venue | `venue.manage` | `venue.updated` |
| Change venue status | `venue.manage` | `venue.status_changed` |
| Archive venue | `venue.manage` | `venue.archived` |
| Create venue asset | `venue.manage` | `venue_asset.created` |
| Update venue asset | `venue.manage` | `venue_asset.updated` |
| Replace availability | `venue.manage` | `venue_asset.availability_replaced` |
| Publish asset | `venue.manage` | `venue_asset.published` |
| Withdraw publication | `venue.manage` | `venue_asset.publication_withdrawn` |
| Submit rental request | `marketplace.manage` | `rental.requested` (×2 scopes) |
| Approve rental | `rentals.approve` | `rental.approved` (×2 scopes) |
| Reject rental | `rentals.approve` | `rental.rejected` (×2 scopes) |
| Cancel rental | `marketplace.manage` | `rental.cancelled` (×2 scopes) |
| Revoke rental | `rentals.approve` | `rental.revoked` (×2 scopes) |
| Export statement CSV | `reports.view` | `statement.exported` |
| Open dispute | `reports.view` | `dispute.opened` (×2 scopes) |
| Start dispute review | `platform.marketplace.disputes.manage` | `dispute.review_started` (×3 scopes) |
| Add dispute note | `platform.marketplace.disputes.manage` | `dispute.note_added` (×3 scopes) |
| Resolve dispute | `platform.marketplace.disputes.manage` | `dispute.resolved` or `dispute.rejected` (×3 scopes) |
| Revise statement | `platform.marketplace.disputes.manage` | `statement.revised` (×3 scopes) |

Read-only list/show endpoints (catalog browse, rental list, statement list, etc.)
are not individually audited; access is gated by permission middleware and standard
request telemetry.

## Asynchronous event listeners

| Listener | Event | Dispatch | Notes |
| --- | --- | --- | --- |
| `WriteRentalRequestedAudit` | `RentalRequested` | synchronous | Writes owner + organizer audit records |
| `WriteDelegationAudit` | `DelegationProvisioned`, `DelegationReleased` | synchronous in queued job | Writes owner + organizer audit records |
| `WriteSettlementDisputeAudit` | `StatementGenerated`, `StatementRevised`, `DisputeOpened`, `DisputeResolved` | synchronous | Routes to per-event handler; writes multi-scope records |
| `WriteVenueCatalogAudit` | `VenueCatalogEvents` | synchronous | Also invalidates catalog cache for publication/status changes |
| `SendRentalRequestedNotifications` | `RentalRequested` | queued, `afterCommit` | No audit data; notification-only |
| `SendRentalDecisionNotifications` | `RentalDecided` | queued, `afterCommit` | Cache-deduped; no audit data |
| `SendDelegationNotifications` | `DelegationProvisioned`, `DelegationReleased` | queued, `afterCommit` | No audit data |
| `SendSettlementDisputeNotifications` | settlement/dispute events | queued, `afterCommit` | No audit data |

## Retention

Marketplace audit records follow the configurable retention policy from
`MarketplaceRetentionPolicy`:

| Record type | Default retention | Active record rule |
| --- | --- | --- |
| Audit entries | 2,555 days (~7 years) | Always retained while active |
| Settlement statements | 2,555 days (~7 years) | Issued statements always retained |
| Dispute records | 2,555 days (~7 years) | Active disputes always retained |

After the retention period, dispute evidence may be minimized (redacted) if the
dispute has been resolved. Statements in `superseded` status become eligible for
cleanup after the retention window.
