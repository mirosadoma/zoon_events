import type { NavigationItem } from '@/types/shell'

export const platformNavigation: NavigationItem[] = [
  { key: 'overview', label: 'overview', href: '/', permission: null },
  { key: 'profile', label: 'profile', href: '/profile', permission: null },
  {
    key: 'admin',
    label: 'administration',
    href: '/admin/users',
    permission: null,
    children: [
      { key: 'admin-users', label: 'users', href: '/admin/users', permission: 'membership.manage' },
      { key: 'admin-roles', label: 'roles', href: '/admin/roles', permission: 'role.manage' },
      { key: 'admin-tenant-settings', label: 'tenantSettings', href: '/admin/tenant-settings', permission: 'tenant.view' },
      { key: 'admin-audit', label: 'audit', href: '/admin/audit-logs', permission: 'audit.view' },
    ],
  },
  { key: 'tenants', label: 'tenants', href: '/platform/tenants', permission: 'platform.tenant.view' },
  { key: 'platform-users', label: 'users', href: '/platform/users', permission: 'platform.user.view' },
  { key: 'platform-roles', label: 'roles', href: '/platform/roles', permission: 'platform.role.view' },
  { key: 'platform-audit', label: 'audit', href: '/platform/audit', permission: 'platform.audit.view' },
  { key: 'health', label: 'health', href: '/platform/health', permission: 'operations.health.view' },
  { key: 'featureFlags', label: 'featureFlags', href: '/platform/feature-flags', permission: 'platform.feature_flag.view' },
  { key: 'configuration', label: 'configuration', href: '/platform/configuration', permission: 'platform.configuration.view' },
]

export function filterNavigation(
  items: NavigationItem[],
  can: Record<string, boolean>,
): NavigationItem[] {
  const filtered: NavigationItem[] = []

  for (const item of items) {
    const children = item.children ? filterNavigation(item.children, can) : undefined
    const permitted = item.permission === null || can[item.permission] === true
    const hasVisibleChildren = children !== undefined && children.length > 0

    if (!permitted && !hasVisibleChildren) {
      continue
    }

    if (children !== undefined && children.length === 0 && item.permission !== null && !permitted) {
      continue
    }

    filtered.push({ ...item, children })
  }

  return filtered
}

export function resolveLabel(messages: Record<string, string>, key: string): string {
  return messages[key] ?? key
}
