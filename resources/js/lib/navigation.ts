import type { NavigationGroup, NavigationItem } from '@/types/shell'
import { stripLocalePrefix } from '@/lib/localePath'

export type { NavigationGroup }

export function normalizeNavPath(path: string): string {
  return stripLocalePrefix(path.split('?')[0] ?? path)
}

export function isNavItemActive(currentPath: string, itemHref: string): boolean {
  const path = normalizeNavPath(currentPath)
  const itemPath = normalizeNavPath(itemHref)

  if (path === itemPath) {
    return true
  }

  if (itemPath === '/tenant/events') {
    return path === '/tenant/events/create'
  }

  const isEventDetailRoot = /\/tenant\/events\/[^/]+$/.test(itemPath)

  if (isEventDetailRoot) {
    return false
  }

  return path.startsWith(`${itemPath}/`)
}

export const platformNavigationGroups: NavigationGroup[] = [
  {
    key: 'menu',
    label: 'navGroupMenu',
    items: [
      { key: 'overview', label: 'overview', href: '/dashboard', icon: 'overview', permission: null },
    ],
  },
  {
    key: 'administration',
    label: 'navGroupAdministration',
    items: [
      { key: 'admin-users', label: 'users', href: '/admin/users', icon: 'admin-users', permission: 'membership.view' },
      { key: 'admin-roles', label: 'roles', href: '/admin/roles', icon: 'admin-roles', permission: 'role.view' },
      { key: 'admin-tenant-settings', label: 'tenantSettings', href: '/admin/tenant-settings', icon: 'admin-tenant-settings', permission: 'tenant.view' },
      { key: 'admin-audit', label: 'audit', href: '/admin/audit-logs', icon: 'admin-audit', permission: 'audit.view' },
    ],
  },
  {
    key: 'platform',
    label: 'navGroupPlatform',
    items: [
      { key: 'events', label: 'events', href: '/tenant/events', icon: 'events', permission: 'event.view' },
      { key: 'tenants', label: 'tenants', href: '/platform/tenants', icon: 'tenants', permission: 'platform.tenant.view' },
      { key: 'platform-users', label: 'users', href: '/platform/users', icon: 'platform-users', permission: 'platform.user.view' },
      { key: 'organizer-requests', label: 'organizerRequests', href: '/platform/organizer-requests', icon: 'organizer-requests', permission: 'platform.user.manage' },
      { key: 'site-settings', label: 'siteSettings', href: '/platform/site-settings', icon: 'site-settings', permission: 'platform.configuration.view' },
      { key: 'geography', label: 'geography', href: '/platform/geography', icon: 'geography', permission: 'platform.configuration.view' },
      { key: 'platform-roles', label: 'roles', href: '/platform/roles', icon: 'platform-roles', permission: 'platform.role.view' },
      { key: 'platform-audit', label: 'audit', href: '/platform/audit', icon: 'platform-audit', permission: 'platform.audit.view' },
      { key: 'health', label: 'health', href: '/platform/health', icon: 'health', permission: 'operations.health.view' },
      { key: 'featureFlags', label: 'featureFlags', href: '/platform/feature-flags', icon: 'featureFlags', permission: 'platform.feature_flag.view' },
      { key: 'configuration', label: 'configuration', href: '/platform/configuration', icon: 'configuration', permission: 'platform.configuration.view' },
    ],
  },
]

/** @deprecated Use platformNavigationGroups */
export const platformNavigation: NavigationItem[] = platformNavigationGroups.flatMap((group) => group.items)

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

export function filterNavigationGroups(
  groups: NavigationGroup[],
  can: Record<string, boolean>,
): NavigationGroup[] {
  return groups
    .map((group) => ({
      ...group,
      items: filterNavigation(group.items, can),
    }))
    .filter((group) => group.items.length > 0)
}

import type { LocaleMessages } from '@/components/layout/SidebarSection'

export function resolveLabel(messages: LocaleMessages, key: string): string {
  const value = messages[key as keyof LocaleMessages]
  return typeof value === 'string' ? value : key
}
