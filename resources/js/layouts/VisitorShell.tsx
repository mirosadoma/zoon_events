import { PropsWithChildren, type ReactNode, useState } from 'react'
import { Head, router, usePage } from '@inertiajs/react'
import { CalendarDays, KeyRound, LogOut, Menu, UserRound, X } from 'lucide-react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import AppBrand from '@/components/layout/AppBrand'
import { useLocale } from '@/hooks/useLocale'

type SessionProps = {
  session?: {
    user?: { name?: string; email?: string }
  } | null
}

type NavKey = 'events' | 'profile' | 'password'

function resolveActive(path: string): NavKey {
  if (path.includes('/visitor/password')) return 'password'
  if (path.includes('/visitor/profile')) return 'profile'
  return 'events'
}

export default function VisitorShell({ children, title }: PropsWithChildren<{ title?: string }>) {
  const { locale, direction, t, localizedPath } = useLocale()
  const page = usePage()
  const { session } = page.props as SessionProps
  const [mobileOpen, setMobileOpen] = useState(false)
  const active = resolveActive(page.url)

  const navItems = [
    { key: 'events' as const, href: '/visitor', label: t('visitorMyEvents'), icon: CalendarDays },
    { key: 'profile' as const, href: '/visitor/profile', label: t('visitorProfile'), icon: UserRound },
    { key: 'password' as const, href: '/visitor/password', label: t('visitorPassword'), icon: KeyRound },
  ]

  function logout() {
    router.post(localizedPath('/logout'))
  }

  return (
    <div dir={direction} lang={locale} className="visitor-page">
      <Head title={title ?? t('visitorPortalTitle')} />

      <header className="visitor-header">
        <div className="visitor-header__inner">
          <LocalizedLink href="/visitor" className="visitor-header__brand">
            <AppBrand nameClassName="text-lg font-bold sm:text-xl" />
          </LocalizedLink>

          <nav className="visitor-header__nav" aria-label={t('visitorPortalTitle')}>
            {navItems.map((item) => {
              const Icon = item.icon
              return (
                <LocalizedLink
                  key={item.key}
                  href={item.href}
                  className={active === item.key ? 'is-active' : undefined}
                >
                  <Icon className="h-4 w-4" aria-hidden />
                  <span>{item.label}</span>
                </LocalizedLink>
              )
            })}
          </nav>

          <div className="visitor-header__actions">
            <div className="visitor-header__user">
              {session?.user?.name ? (
                <span className="visitor-header__user-name">{session.user.name}</span>
              ) : null}
              {session?.user?.email ? (
                <span className="visitor-header__user-email">{session.user.email}</span>
              ) : null}
            </div>
            <button type="button" className="button-secondary visitor-header__logout" onClick={logout}>
              <LogOut className="h-4 w-4" aria-hidden />
              <span>{t('logout')}</span>
            </button>
            <button
              type="button"
              className="button-secondary visitor-header__menu p-2 lg:hidden"
              onClick={() => setMobileOpen((open) => !open)}
              aria-expanded={mobileOpen}
              aria-label={mobileOpen ? t('closeMenu') : t('openMenu')}
            >
              {mobileOpen ? <X className="h-4 w-4" /> : <Menu className="h-4 w-4" />}
            </button>
          </div>
        </div>

        {mobileOpen ? (
          <div className="visitor-header__mobile">
            {navItems.map((item) => {
              const Icon = item.icon
              return (
                <LocalizedLink
                  key={item.key}
                  href={item.href}
                  className={active === item.key ? 'is-active' : undefined}
                  onClick={() => setMobileOpen(false)}
                >
                  <Icon className="h-4 w-4" aria-hidden />
                  <span>{item.label}</span>
                </LocalizedLink>
              )
            })}
            <button type="button" className="button-secondary w-full" onClick={logout}>
              <LogOut className="h-4 w-4" aria-hidden />
              <span>{t('logout')}</span>
            </button>
          </div>
        ) : null}
      </header>

      <main className="visitor-main">{children}</main>
    </div>
  )
}

export function VisitorPageHeader({
  title,
  lead,
  action,
}: {
  title: string
  lead?: string
  action?: ReactNode
}) {
  return (
    <div className="visitor-page-header">
      <div>
        <h1 className="visitor-page-header__title">{title}</h1>
        {lead ? <p className="visitor-page-header__lead">{lead}</p> : null}
      </div>
      {action ? <div className="visitor-page-header__action">{action}</div> : null}
    </div>
  )
}

export function VisitorPanel({ children, className = '' }: PropsWithChildren<{ className?: string }>) {
  return <div className={`visitor-panel ${className}`.trim()}>{children}</div>
}
