import { Link, usePage } from '@inertiajs/react'
import { X } from 'lucide-react'
import { useShellLayout } from '@/contexts/ShellLayoutContext'
import AppBrand from '@/components/layout/AppBrand'
import { filterNavigationGroups, platformNavigationGroups } from '@/lib/navigation'
import { eventNavigationGroups, extractEventIdFromPath } from '@/lib/tenant-navigation'
import { useLocale } from '@/hooks/useLocale'
import { localizedPath } from '@/lib/localePath'
import en from '@/locales/en'
import ar from '@/locales/ar'
import type { PermissionMap } from '@/types/shell'
import SidebarSection from './SidebarSection'

export default function Sidebar() {
  const { locale } = useLocale()
  const { sidebarCollapsed, mobileSidebarOpen, closeMobileSidebar } = useShellLayout()
  const messages = locale === 'ar' ? ar : en
  const can = (usePage().props.can ?? {}) as PermissionMap
  const { url } = usePage()
  const eventId = extractEventIdFromPath(url)

  const platformGroups = filterNavigationGroups(platformNavigationGroups, can)
  const eventGroups = eventId ? filterNavigationGroups(eventNavigationGroups(eventId), can) : []

  const sidebarContent = (
    <div className="ta-sidebar-scroll">
      <Link href={localizedPath(locale, '/dashboard')} className="ta-sidebar-brand" title={messages.overview}>
        <AppBrand showName={!sidebarCollapsed} />
      </Link>

      {platformGroups.map((group) => (
        <SidebarSection key={group.key} group={group} messages={messages} collapsed={sidebarCollapsed} locale={locale} />
      ))}

      {eventGroups.length > 0 && (
        <div className="ta-sidebar-event-context">
          {!sidebarCollapsed && (
            <p className="ta-sidebar-event-title">
              {messages.navEventContext}
            </p>
          )}
          {eventGroups.map((group) => (
            <SidebarSection key={group.key} group={group} messages={messages} collapsed={sidebarCollapsed} locale={locale} eventContext />
          ))}
        </div>
      )}
    </div>
  )

  return (
    <>
      {mobileSidebarOpen && (
        <button
          type="button"
          className="fixed inset-0 z-40 bg-black/40 lg:hidden"
          aria-label={messages.closeMenu}
          onClick={closeMobileSidebar}
        />
      )}

      <aside
        data-tour="sidebar"
        className={`ta-sidebar ${sidebarCollapsed ? 'ta-sidebar-collapsed' : ''} ${mobileSidebarOpen ? 'fixed inset-y-0 start-0 z-50 w-[290px] shadow-xl' : 'hidden lg:flex'} `}
      >
        <div className="mb-4 flex items-center justify-end lg:hidden">
          <button type="button" className="button-secondary" onClick={closeMobileSidebar}>
            <X className="h-5 w-5" />
          </button>
        </div>
        {sidebarContent}
      </aside>
    </>
  )
}
