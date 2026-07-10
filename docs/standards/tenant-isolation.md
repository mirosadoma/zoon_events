# Tenant isolation standard

Owner: Security Engineering  
Last reviewed: 2026-07-03

Every tenant row has non-null `tenant_id` with tenant-first indexes and composite foreign
keys. Requests authenticate before resolving active tenant membership. Tenant models use
the fail-closed scope; jobs/events restore persisted active context and clear it finally.
Storage begins `tenants/{tenant_id}`, caches begin `tenant:{tenant_id}`, and adapters accept
only validated contexts. Cross-tenant and random identifiers return identical not-found
responses. Raw unscoped queries are allowed only in explicit platform/application code.
