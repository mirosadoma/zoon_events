# Phase 0 foundation architecture

Owner: Engineering Architecture  
Last reviewed: 2026-07-03  
Applies to: SaaS and on-premise

Zonetec is a Laravel modular monolith with Identity, Tenancy, Authorization, Audit,
FeatureFlags, Operations, Integrations, AdminConsole, and Shared modules. Versioned APIs
and the React/Inertia dashboard call the same application rules. Platform scope is an
explicit privileged path; absent tenant context never grants global access.

Security-sensitive mutations and audit evidence commit in one database transaction.
Tenant-owned persistence, jobs, events, cache keys, storage paths, telemetry, and adapter
calls fail closed without trusted tenant context.
