# ADR 004: Canonical HMAC audit integrity
Date: 2026-07-03. Status: accepted. Owner: Security Engineering.

Sign each canonical sanitized record with a versioned secret key. A global hash chain was
rejected because it serializes writes; unsigned or asynchronous audit was rejected.
