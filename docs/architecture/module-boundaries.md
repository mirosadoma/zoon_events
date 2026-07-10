# Module boundaries

Owner: Engineering Architecture  
Last reviewed: 2026-07-03

Dependencies flow HTTP → Application → Domain/Contracts → Infrastructure. A module may
use another module's public contract or immutable event, never its Infrastructure model.
Shared contains framework-neutral primitives. AdminConsole owns presentation only and
receives allow-listed view models; it does not query persistence. Integrations owns all
external provider boundaries.
