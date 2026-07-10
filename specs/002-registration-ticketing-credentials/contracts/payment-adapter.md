# Payment Adapter Contract

## Purpose

Keep order and refund rules independent of a payment provider. Phase 1 requires
a deterministic fake and a Moyasar adapter; later providers implement the same
contract.

## Invocation Context

Every call carries trusted:

- tenant and payment-account identifiers;
- order, payment-attempt, operation, and correlation identifiers;
- idempotency key;
- test/live mode;
- amount in minor units and currency;
- timeout budget and data classification.

Secrets are resolved inside infrastructure from `secret_reference`. They never
enter domain requests, logs, audit metadata, exceptions, or serialized jobs.

## Operations

### Create payment

Input: order reference, amount, currency, description, return URL, sanitized
metadata, and idempotency key.

Result:

- provider payment identifier;
- stable status (`pending`, `action_required`, `authorized`, `captured`,
  `failed`, `cancelled`, `unknown`);
- optional browser action containing only provider-approved redirect or client
  parameters;
- safe reason category;
- provider observed time.

### Fetch payment

Input: provider payment identifier.

Result: authoritative status, requested/captured/refunded amounts, currency,
merchant account identity, order reference, live mode, and safe reason category.

### Capture or void

Input: authorized provider payment, amount, currency, and idempotency key.

Result: authoritative captured/voided/unknown outcome. Phase 1 defaults to
automatic capture; the operations remain in the contract for reconciliation.

### Refund

Input: captured provider payment, positive amount no greater than locally
refundable balance, currency, reason reference, and idempotency key.

Result: provider refund identifier, refunded/failed/unknown status, cumulative
authoritative refunded amount, and safe reason category.

## Stable Error Categories

`validation`, `authentication`, `authorization`, `rate_limited`,
`dependency_unavailable`, `timeout_before_send`, `unknown_outcome`,
`duplicate`, `amount_mismatch`, `currency_mismatch`, `account_mismatch`,
`not_refundable`, and `permanent_failure`.

Provider messages/codes may be retained only in redacted restricted diagnostics;
public and domain outcomes use the stable categories.

## Webhook Contract

1. Resolve the payment account using an unguessable route token.
2. Enforce request-size and rate limits.
3. Check the configured webhook secret using constant-time comparison.
4. Deduplicate the provider event ID and store only its payload digest.
5. Acknowledge valid receipt quickly.
6. Fetch the payment through the adapter before changing financial state.
7. Match account, live mode, provider payment, order reference, amount, and
   currency.
8. Apply the state transition idempotently in trusted tenant context.
9. Record succeeded, ignored, denied, or failed audit evidence.

## Retry and Reconciliation

- Retry only `timeout_before_send`, explicit rate limits, and temporary
  unavailability using bounded exponential backoff with jitter.
- Never repeat a call after an unknown outcome without fetching/reconciling
  first.
- Reconciliation scans only due `pending` or `unknown` attempts in bounded
  batches and uses tenant-aware job middleware.
- Duplicate callbacks, jobs, or operator requests converge on one state.

## Contract Test Matrix

Every adapter must pass: create success, action required, authorization/capture,
failure, cancel, full/partial refund, duplicate key, before-send timeout,
unknown outcome, fetch recovery, amount/currency/account mismatch, malformed
response, rate limit, offline/recovery, redaction, tenant context, and test/live
separation.

