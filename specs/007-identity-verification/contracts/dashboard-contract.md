# Dashboard UI Contract: Identity Verification (Phase 5)

Route → governing permission → required states for the Inertia surfaces. Tenant
pages sit behind `auth` + tenant context + the operation permission (server-side
authoritative; client `PermissionGate` is UX-only). The attendee surface is public,
reached via the order/registration access token. Real permission keys are seeded in
`PermissionSeeder` and mirrored in `docs/standards/permission-catalog.md`.

## Routes

| Route | Permission | Page | Status | States required |
|---|---|---|---|---|
| `/tenant/events/{event_id}/identity` | `identity.configure` | `pages/tenant/identity/Requirements.tsx` | NEW | loading / empty / error / forbidden |
| `/tenant/events/{event_id}/identity/review` | `identity.review` | `pages/tenant/identity/ReviewQueue.tsx` | NEW | loading / empty / error / forbidden; per-item approve/reject |
| `/tenant/events/{event_id}/identity/verifications/{verification_id}` | `identity.data.view` | `pages/tenant/identity/VerificationDetail.tsx` | NEW | loading / error / forbidden; sensitive access audited on load |
| `/identity/{event_slug}/{order_token}` | order access token | `pages/public/identity/Verify.tsx` | NEW | consent → verify → result; loading / error / provider-unavailable |

## Actions

| Action | Control | Permission | Modal | Endpoint |
|---|---|---|---|---|
| Set requirement (event/tier) | form | `identity.configure` | — | `PUT .../identity/requirements` |
| Consent | consent notice + confirm | order token | — (explicit consent step) | `POST .../identity/consent` |
| Start government verification | button | order token | — | `POST .../identity/verification` |
| Submit face capture | capture panel | order token | — | `POST .../identity/face-capture` |
| Approve review | button | `identity.review` | `ConfirmModal` | `POST .../verifications/{id}/review` (`approve`) |
| Reject review | button | `identity.review` | `ReasonModal` (reason required) | `POST .../verifications/{id}/review` (`reject`) |
| Delete sensitive data | button | `identity.data.manage` | `ReasonModal` | `DELETE .../identity/data` |

## Status badges

One identity `StatusBadge` variant set covering: `not_required`, `pending`,
`gov_verified`, `face_verified`, `manually_approved`, `rejected`, `expired`.
Surfaced on attendee detail, credential detail, and the review queue. Consistent
tokens with the existing shared `StatusBadge`.

## Enforcement surfacing

- Attendee/credential detail shows an identity-pending banner when issuance is
  withheld (`required_before_credential`).
- Gate/access logs show the `identity_not_verified` reason on rejected entries
  (`required_before_gate`), consistent with existing Phase 4 access-log reasons.

## Localization / accessibility

All labels, consent disclosures, statuses, and reasons are Arabic/English via
`locales/{en,ar}.ts`, render correctly in RTL/LTR, use locale-aware dates, and are
axe-clean with visible focus — matching the existing dashboard bar.
