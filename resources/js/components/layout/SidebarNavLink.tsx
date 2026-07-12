import { Link, usePage } from '@inertiajs/react'
import { useShellLayout } from '@/contexts/ShellLayoutContext'
import {
  Activity,
  BadgeCheck,
  CalendarDays,
  ClipboardList,
  Flag,
  Globe,
  LayoutDashboard,
  Map,
  ScanLine,
  Settings,
  Shield,
  Ticket,
  UserCircle,
  Users,
  Wallet,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { clsx } from 'clsx'
import type { NavigationItem } from '@/types/shell'
import { localizedPath, stripLocalePrefix, type AppLocale } from '@/lib/localePath'

const NAV_ICONS: Record<string, LucideIcon> = {
  overview: LayoutDashboard,
  events: CalendarDays,
  profile: UserCircle,
  'admin-users': Users,
  'admin-roles': Shield,
  'admin-tenant-settings': Settings,
  'admin-audit': ClipboardList,
  tenants: Globe,
  'platform-users': Users,
  'organizer-requests': BadgeCheck,
  'site-settings': Settings,
  geography: Map,
  'platform-roles': Shield,
  'platform-audit': ClipboardList,
  health: Activity,
  featureFlags: Flag,
  configuration: Settings,
  'event-detail': LayoutDashboard,
  'registration-form': ClipboardList,
  'ticket-types': Ticket,
  'price-tiers': Ticket,
  orders: Ticket,
  attendees: Users,
  credentials: BadgeCheck,
  'identity-requirements': Shield,
  'identity-review': Shield,
  scanner: ScanLine,
  'check-in-dashboard': ScanLine,
  'scan-events': ScanLine,
  'wallet-passes': Wallet,
  kiosks: ScanLine,
  'badge-templates': BadgeCheck,
  'badge-print-jobs': BadgeCheck,
  'manual-desk': Users,
  'walk-up': Users,
  acs: Shield,
  'acs-zones': Map,
  'acs-lanes': Map,
  'acs-rules': Shield,
  'acs-access-logs': ClipboardList,
  'acs-gate-health': Activity,
  reports: ClipboardList,
}

type SidebarNavLinkProps = {
  item: NavigationItem
  label: string
  collapsed?: boolean
  locale: AppLocale
  eventContext?: boolean
}

export default function SidebarNavLink({
  item,
  label,
  collapsed = false,
  locale,
  eventContext = false,
}: SidebarNavLinkProps) {
  const { url } = usePage()
  const { closeMobileSidebar } = useShellLayout()
  const path = stripLocalePrefix(url.split('?')[0] ?? url)
  const href = localizedPath(locale, item.href)
  const itemPath = stripLocalePrefix(item.href)
  const isEventRoot = /\/tenant\/events\/[^/]+$/.test(itemPath)
  const active = isEventRoot
    ? path === itemPath
    : path === itemPath || path.startsWith(`${itemPath}/`)
  const Icon = NAV_ICONS[item.icon ?? item.key] ?? LayoutDashboard

  return (
    <Link
      href={href}
      onClick={closeMobileSidebar}
      className={clsx(
        'ta-nav-link flex items-center gap-2',
        active && 'ta-nav-link-active',
        collapsed && 'justify-center px-2',
        eventContext && !active && 'hover:bg-[var(--surface-elevated)]',
      )}
      aria-current={active ? 'page' : undefined}
      title={collapsed ? label : undefined}
      data-tour={`nav-${item.key}`}
    >
      <Icon className={clsx('shrink-0', collapsed ? 'h-5 w-5' : 'h-4 w-4')} aria-hidden />
      <span className={clsx('truncate', collapsed && 'sr-only')}>{label}</span>
    </Link>
  )
}
