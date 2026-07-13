import { Link, usePage } from '@inertiajs/react'
import { X } from 'lucide-react'
import { useEffect } from 'react'
import { clsx } from 'clsx'
import { useShellLayout } from '@/contexts/ShellLayoutContext'
import AppBrand from '@/components/layout/AppBrand'
import { filterNavigationGroups, platformNavigationGroups } from '@/lib/navigation'
import { eventNavigationGroups, extractEventIdFromPath } from '@/lib/tenant-navigation'
import { useLocale } from '@/hooks/useLocale'
import { localizedPath } from '@/lib/localePath'
import en from '@/locales/en'
import ar from '@/locales/ar'
import type { PermissionMap } from '@/types/shell'
import type { EventCapabilities } from '@/lib/eventOptions'
import SidebarSection from './SidebarSection'

export default function Sidebar() {
  const { locale } = useLocale()
  const { sidebarCollapsed, mobileSidebarOpen, closeMobileSidebar } = useShellLayout()
  const messages = locale === 'ar' ? ar : en
  const page = usePage()
  const can = (page.props.can ?? {}) as PermissionMap
  const sharedNavContext = page.props.eventNavContext as { capabilities?: EventCapabilities } | null | undefined
  const pageEvent = page.props.event as { capabilities?: EventCapabilities } | null | undefined
  const pageCapabilities = (page.props.eventCapabilities ?? pageEvent?.capabilities) as EventCapabilities | undefined
  const eventCapabilities = pageCapabilities ?? sharedNavContext?.capabilities
  const { url } = page
  const eventId = extractEventIdFromPath(url)

  const platformGroups = filterNavigationGroups(platformNavigationGroups, can)
  const eventGroups = eventId ? filterNavigationGroups(eventNavigationGroups(eventId, eventCapabilities), can) : []

  useEffect(() => {
    closeMobileSidebar()
  }, [url, closeMobileSidebar])

  useEffect(() => {
    if (!mobileSidebarOpen) {
      return undefined
    }

    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    return () => {
      document.body.style.overflow = previousOverflow
    }
  }, [mobileSidebarOpen])

  const sidebarContent = (
    <div className="ta-sidebar-scroll">
      <Link
        href={localizedPath(locale, '/dashboard')}
        className="ta-sidebar-brand"
        title={messages.overview}
        onClick={closeMobileSidebar}
      >
        <AppBrand showName={!sidebarCollapsed} />
      </Link>

      {platformGroups.map((group) => (
        <SidebarSection key={group.key} group={group} messages={messages} collapsed={sidebarCollapsed} locale={locale} />
      ))}

      {eventGroups.length > 0 ? (
        <div className="ta-sidebar-event-context">
          {!sidebarCollapsed ? (
            <p className="ta-sidebar-event-title">
              {messages.navEventContext}
            </p>
          ) : null}
          {eventGroups.map((group) => (
            <SidebarSection key={group.key} group={group} messages={messages} collapsed={sidebarCollapsed} locale={locale} eventContext />
          ))}
        </div>
      ) : null}
    </div>
  )

  return (
    <>
      {mobileSidebarOpen ? (
        <button
          type="button"
          className="ta-sidebar-overlay"
          aria-label={messages.closeMenu}
          onClick={closeMobileSidebar}
        />
      ) : null}

      <aside
        data-tour="sidebar"
        className={clsx(
          'ta-sidebar',
          sidebarCollapsed && 'ta-sidebar-collapsed',
          mobileSidebarOpen
            ? 'max-lg:fixed max-lg:inset-y-0 max-lg:start-0 max-lg:z-50 max-lg:flex max-lg:w-[min(260px,88vw)] max-lg:shadow-2xl ta-sidebar-mobile-open'
            : 'ta-sidebar-mobile hidden',
          'lg:flex',
        )}
      >
        <div className="ta-sidebar-mobile-header">
          <Link
            href={localizedPath(locale, '/dashboard')}
            className="ta-sidebar-brand !py-1"
            onClick={closeMobileSidebar}
          >
            <AppBrand showName />
          </Link>
          <button type="button" className="ta-topbar-action" onClick={closeMobileSidebar} aria-label={messages.closeMenu}>
            <X className="h-5 w-5" />
          </button>
        </div>
        {sidebarContent}
      </aside>
    </>
  )
}
