import { router, usePage } from '@inertiajs/react'
import { ChevronDown, CircleHelp, ExternalLink, LogOut, Menu, Moon, PanelLeftClose, PanelLeftOpen, Sun, User } from 'lucide-react'
import { useCallback, useEffect, useRef, useState } from 'react'
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

function resolveIsDark(theme: ReturnType<typeof useTheme>['theme']): boolean {
  if (theme === 'dark') {
    return true
  }

  if (theme === 'light') {
    return false
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

export default function Topbar() {
  const { locale, localizedPath } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const { theme, setTheme } = useTheme()
  const { sidebarCollapsed, toggleSidebar, toggleMobileSidebar } = useShellLayout()
  const page = usePage<PageProps>()
  const messages = locale === 'ar' ? ar : en
  const session = page.props.session
  const [menuOpen, setMenuOpen] = useState(false)
  const [isDark, setIsDark] = useState(() => resolveIsDark(theme))

  const menuRef = useRef<HTMLDivElement>(null)

  const closeMenus = useCallback(() => {
    setMenuOpen(false)
  }, [])

  useClickOutside(menuRef, () => setMenuOpen(false), menuOpen)

  useEffect(() => {
    setIsDark(resolveIsDark(theme))

    if (theme !== 'system') {
      return undefined
    }

    const media = window.matchMedia('(prefers-color-scheme: dark)')
    const onChange = () => setIsDark(media.matches)
    media.addEventListener('change', onChange)

    return () => media.removeEventListener('change', onChange)
  }, [theme])

  const toggleLocale = () => {
    const next = locale === 'ar' ? 'en' : 'ar'
    const currentPath = `${window.location.pathname}${window.location.search}`
    document.cookie = `locale=${next};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`
    router.visit(swapLocaleInPath(currentPath, next), { preserveScroll: true, preserveState: false })
  }

  const toggleTheme = () => {
    setTheme(isDark ? 'light' : 'dark')
  }

  return (
    <header className="ta-topbar">
      <div className="ta-topbar-start">
        <button
          type="button"
          className="ta-topbar-action ta-topbar-action-mobile-only"
          onClick={toggleMobileSidebar}
          aria-label={messages.openMenu}
        >
          <Menu className="h-4 w-4" />
        </button>
        <button
          type="button"
          className="ta-topbar-action ta-topbar-action-desktop-only"
          onClick={toggleSidebar}
          aria-label={sidebarCollapsed ? messages.openMenu : messages.closeMenu}
        >
          {sidebarCollapsed ? <PanelLeftOpen className="h-4 w-4" /> : <PanelLeftClose className="h-4 w-4" />}
        </button>

        <SearchCommand />
      </div>

      <div className="ta-topbar-end">
        <div className="ta-topbar-meta">
          {session?.tenant ? (
            <span className="ta-topbar-chip ta-topbar-chip-brand hidden sm:inline-flex" title={session.tenant.name}>
              {session.tenant.name}
            </span>
          ) : null}
          {session?.role_label ? (
            <span className="ta-topbar-chip ta-topbar-chip-neutral hidden md:inline-flex">
              {session.role_label}
            </span>
          ) : null}
        </div>

        <div className="ta-topbar-actions">
          <button
            type="button"
            className="ta-topbar-action ta-topbar-action-desktop-only"
            onClick={() => window.dispatchEvent(new CustomEvent('zonetec:tour-start'))}
            aria-label={messages.productTourKicker}
            title={messages.productTourKicker}
          >
            <CircleHelp className="h-4 w-4" />
          </button>

          <button
            type="button"
            className="ta-topbar-action"
            onClick={toggleTheme}
            aria-label={messages.theme}
            title={isDark ? messages.topbarLightMode : messages.topbarDarkMode}
          >
            {isDark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
          </button>

          <button
            type="button"
            className="ta-topbar-action min-w-10 px-2 text-xs font-semibold"
            onClick={toggleLocale}
            aria-label={messages.toggleLocale}
          >
            {locale === 'ar' ? messages.localeSwitchToEn : messages.localeSwitchToAr}
          </button>

          <NotificationDropdown />
        </div>

        <div ref={menuRef} className="relative">
          <button
            type="button"
            className="ta-topbar-user-trigger"
            onClick={() => {
              setMenuOpen((value) => !value)
            }}
            aria-expanded={menuOpen}
          >
            <span className="ta-topbar-user-avatar">
              <User className="h-4 w-4" />
            </span>
            <span className="hidden max-w-[8rem] truncate font-medium sm:inline">{session?.user.name}</span>
            <ChevronDown className="hidden h-4 w-4 text-[var(--muted)] sm:inline" />
          </button>
          {menuOpen ? (
            <div className="ta-topbar-menu">
              <button
                type="button"
                className="ta-topbar-menu-item"
                onClick={() => {
                  closeMenus()
                  localizedRouter.visit('/profile')
                }}
              >
                <User className="h-4 w-4" />
                {messages.profile}
              </button>
              <a
                href={localizedPath('/')}
                className="ta-topbar-menu-item"
                onClick={closeMenus}
              >
                <ExternalLink className="h-4 w-4" />
                {messages.publicSite}
              </a>
              <button
                type="button"
                className="ta-topbar-menu-item ta-topbar-menu-item-danger"
                onClick={() => localizedRouter.post('/logout')}
              >
                <LogOut className="h-4 w-4" />
                {messages.logout}
              </button>
            </div>
          ) : null}
        </div>
      </div>
    </header>
  )
}
