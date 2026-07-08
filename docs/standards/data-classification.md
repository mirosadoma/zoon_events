# Phase 0 data classification

Owner: Privacy and Security  
Last reviewed: 2026-07-08

Tenant metadata is internal; workforce identity and authorization data are confidential;
password/token/audit keys are secret and never returned or logged; audit evidence is
confidential and permission-restricted. Collection is limited to administration, security,
operations, and compliance. Raw IP, full user-agent, credentials, and full object snapshots
are excluded from audit and telemetry.

## Phase 5 identity and biometric data (PDPL-sensitive)

| Class | Examples stored | Classification | Retention default | Residency |
|---|---|---|---|---|
| Identity consent | notice version, bilingual disclosures, residency mode, timestamps | Confidential | Event lifecycle + policy window | On-premise by default; cross-border off unless configured |
| Verification status | method, status, minimized verified name/nationality, reviewer metadata | Confidential | `verification_days` (365) on verified records | Processed locally via configured adapters |
| Biometric artifact | encrypted minimized template reference, liveness outcome | **Restricted / biometric** | `biometric_days` (30); purged via scheduled job | Never returned through APIs; on-premise processing preferred |
| Provider reference | opaque government/face provider handle | Confidential | `provider_payload_days` (7) then cleared | Adapter-local; no raw gov payloads persisted |

Rules:

- Prefer templates over raw images; encrypt biometric references at rest.
- APIs return status projections only — never `storage_reference`, ciphertext, or raw provider payloads.
- Sensitive access (`identity.data.view`) and deletion (`identity.data.manage`) are permission-gated and audited.
- `PurgeExpiredIdentityArtifacts` removes expired sensitive artifacts while preserving non-sensitive status and audit metadata.
