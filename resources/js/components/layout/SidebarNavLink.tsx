import { Link, usePage } from '@inertiajs/react'
import { useEffect, useRef } from 'react'
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
import { isNavItemActive } from '@/lib/navigation'
import type { NavigationItem } from '@/types/shell'
import { localizedPath, type AppLocale } from '@/lib/localePath'

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
  const linkRef = useRef<HTMLAnchorElement>(null)
  const href = localizedPath(locale, item.href)
  const active = isNavItemActive(url, item.href)
  const Icon = NAV_ICONS[item.icon ?? item.key] ?? LayoutDashboard

  useEffect(() => {
    if (!active || !linkRef.current) {
      return
    }

    linkRef.current.scrollIntoView({ block: 'nearest', inline: 'nearest' })
  }, [active, url])

  return (
    <Link
      ref={linkRef}
      href={href}
      onClick={closeMobileSidebar}
      className={clsx(
        'ta-nav-link',
        active && 'ta-nav-link-active',
        collapsed && 'justify-center px-2',
        eventContext && !active && 'hover:bg-[var(--surface-elevated)]',
      )}
      aria-current={active ? 'page' : undefined}
      title={collapsed ? label : undefined}
      data-tour={`nav-${item.key}`}
    >
      <span className={clsx('ta-nav-link-icon', collapsed && 'h-8 w-8')} aria-hidden="true">
        <Icon className={clsx(collapsed ? 'h-4 w-4' : 'h-3.5 w-3.5')} />
      </span>
      <span className={clsx('truncate', collapsed && 'sr-only')}>{label}</span>
    </Link>
  )
}
