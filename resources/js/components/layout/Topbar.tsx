import { router, usePage } from '@inertiajs/react'
import { ChevronDown, CircleHelp, LogOut, Menu, Moon, PanelLeftClose, PanelLeftOpen, User } from 'lucide-react'
import { useCallback, useRef, useState } from 'react'
import { useShellLayout } from '@/contexts/ShellLayoutContext'
import { useClickOutside } from '@/hooks/useClickOutside'
import { useLocale } from '@/hooks/useLocale'
import { useTheme } from '@/hooks/useTheme'
import { swapLocaleInPath } from '@/lib/localePath'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import NotificationDropdown from './NotificationDropdown'
import SearchCommand from './SearchCommand'
import en from '@/locales/en'
import ar from '@/locales/ar'
import type { SessionContext } from '@/types/shell'

type PageProps = {
  session?: SessionContext | null
}

export default function Topbar() {
  const { locale } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const { theme, setTheme } = useTheme()
  const { sidebarCollapsed, toggleSidebar, toggleMobileSidebar } = useShellLayout()
  const page = usePage<PageProps>()
  const messages = locale === 'ar' ? ar : en
  const session = page.props.session
  const [menuOpen, setMenuOpen] = useState(false)

  const menuRef = useRef<HTMLDivElement>(null)

  const closeMenus = useCallback(() => {
    setMenuOpen(false)
  }, [])

  useClickOutside(menuRef, () => setMenuOpen(false), menuOpen)

  const toggleLocale = () => {
    const next = locale === 'ar' ? 'en' : 'ar'
    const currentPath = `${window.location.pathname}${window.location.search}`
    document.cookie = `locale=${next};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`
    router.visit(swapLocaleInPath(currentPath, next), { preserveScroll: true, preserveState: false })
  }

  return (
    <header className="ta-topbar">
      <div className="flex min-w-0 flex-1 items-center gap-2">
        <button
          type="button"
          className="button-secondary hidden p-2 max-lg:inline-flex lg:!hidden"
          onClick={toggleMobileSidebar}
          aria-label={messages.openMenu}
        >
          <Menu className="h-4 w-4" />
        </button>
        <button
          type="button"
          className="button-secondary hidden p-2 lg:inline-flex max-lg:!hidden"
          onClick={toggleSidebar}
          aria-label={sidebarCollapsed ? messages.openMenu : messages.closeMenu}
        >
          {sidebarCollapsed ? <PanelLeftOpen className="h-4 w-4" /> : <PanelLeftClose className="h-4 w-4" />}
        </button>

        <SearchCommand />
      </div>

      <div className="flex flex-wrap items-center gap-2 sm:gap-3">
        {session?.tenant && (
          <span className="hidden rounded-full bg-[var(--brand-soft)] px-2.5 py-0.5 text-xs font-medium text-[var(--brand)] md:inline">
            {session.tenant.name}
          </span>
        )}
        {session?.role_label && (
          <span className="hidden rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-600 dark:bg-slate-800 md:inline">
            {session.role_label}
          </span>
        )}

        <button
          type="button"
          className="button-secondary !hidden p-2 lg:!inline-flex"
          onClick={() => window.dispatchEvent(new CustomEvent('zonetec:tour-start'))}
          aria-label={messages.productTourKicker}
          title={messages.productTourKicker}
        >
          <CircleHelp className="h-4 w-4" />
        </button>

        <button type="button" className="button-secondary p-2" onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')} aria-label={messages.theme}>
          <Moon className="h-4 w-4" />
        </button>

        <button type="button" className="button-secondary p-2" onClick={toggleLocale} aria-label={messages.toggleLocale}>
          {locale === 'ar' ? 'EN' : 'ع'}
        </button>

        <NotificationDropdown />

        <div ref={menuRef} className="relative">
          <button
            type="button"
            className="flex items-center gap-2 rounded-lg border border-[var(--border)] px-2 py-1.5 text-sm"
            onClick={() => {
              setMenuOpen((value) => !value)
            }}
            aria-expanded={menuOpen}
          >
            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--brand-soft)] text-[var(--brand)]">
              <User className="h-4 w-4" />
            </span>
            <span className="hidden max-w-[8rem] truncate font-medium sm:inline">{session?.user.name}</span>
            <ChevronDown className="h-4 w-4 text-slate-400" />
          </button>
          {menuOpen && (
            <div className="absolute end-0 z-50 mt-2 w-48 rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)] py-1 shadow-lg">
              <button
                type="button"
                className="flex w-full items-center gap-2 px-4 py-2 text-start text-sm hover:bg-[var(--brand-soft)]"
                onClick={() => {
                  closeMenus()
                  localizedRouter.visit('/profile')
                }}
              >
                <User className="h-4 w-4" />
                {messages.profile}
              </button>
              <button
                type="button"
                className="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                onClick={() => localizedRouter.post('/logout')}
              >
                <LogOut className="h-4 w-4" />
                {messages.logout}
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  )
}
