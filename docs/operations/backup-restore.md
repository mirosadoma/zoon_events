# Backup and restore

Owner: Platform Operations  
Last reviewed: 2026-07-03

Back up MySQL and `storage/app/private` atomically where possible; encrypt and retain them
inside the approved residency region. Restore into an isolated environment, validate
configuration, run migrations, verify audit HMACs, confirm private export paths, then run
tenant/RBAC matrices before traffic. Record recovery-point and recovery-time evidence.
