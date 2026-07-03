# ADR 005: Database queue
Date: 2026-07-03. Status: accepted. Owner: Platform Operations.

Use after-commit database queues for portable offline operation. Mandatory audit remains
synchronous. Redis-only deployment was rejected for on-premise parity.
