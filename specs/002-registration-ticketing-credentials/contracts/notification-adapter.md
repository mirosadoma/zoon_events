# Notification Adapter Contract

## Channels

- Email: configured SMTP transport with tenant-approved sender identity.
- SMS: provider-neutral sender with fake and Unifonic implementations.

## Request

Each request carries:

- notification, tenant, event, attendee, order, and correlation identifiers;
- channel and adapter key;
- encrypted destination resolved only for delivery;
- approved sender reference;
- locale and immutable template/version;
- rendered subject/body;
- idempotency key and timeout budget.

Rendered content is minimized. It may contain event details, order reference,
and a safe credential-access link; it must not contain payment secrets, raw form
answers, national identity data, or provider credentials.

## Result

- stable status: `accepted`, `sent`, `delivered`, `temporary_failure`,
  `permanent_failure`, or `unknown`;
- provider message identifier when supplied;
- safe reason category;
- provider observed time.

## Delivery Callbacks

Callbacks resolve a notification through provider message ID plus tenant adapter
configuration, authenticate according to the provider contract, deduplicate the
callback ID, and update only forward-compatible delivery states. Callback
payloads are not stored wholesale.

## Retry Rules

- Retry temporary failure and before-send timeout with bounded backoff.
- Reconcile unknown outcomes before resending when the provider supports status.
- Never create a second notification intent for the same
  `(order,channel,template,version)`.
- Permanent failure remains visible to authorized staff and creates sanitized
  audit evidence.

## Contract Test Matrix

Every adapter must cover accepted, delivered, invalid destination, unauthorized
sender, rate limit, temporary outage, timeout before send, unknown outcome,
duplicate invocation, callback duplication, Arabic Unicode, English content,
redaction, tenant scope, offline/recovery, and test/live separation.

