export const platformNavigation = [
  { label: 'overview', href: '/', permission: null },
  { label: 'tenants', href: '/platform/tenants', permission: 'platform.tenant.view' },
  { label: 'users', href: '/platform/users', permission: 'platform.user.view' },
  { label: 'roles', href: '/platform/roles', permission: 'platform.role.view' },
  { label: 'audit', href: '/platform/audit', permission: 'platform.audit.view' },
  { label: 'health', href: '/platform/health', permission: 'operations.health.view' },
  { label: 'featureFlags', href: '/platform/feature-flags', permission: 'platform.feature_flag.view' },
  { label: 'configuration', href: '/platform/configuration', permission: 'platform.configuration.view' },
] as const
