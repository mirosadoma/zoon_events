import { Head, router, usePage } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import {
  CalendarDays,
  Globe,
  Menu,
  Moon,
  Sun,
  X,
} from 'lucide-react'
import { useCallback, useRef, useState, type PropsWithChildren } from 'react'
import { useClickOutside } from '@/hooks/useClickOutside'
import { useLocale } from '@/hooks/useLocale'
import { useTheme } from '@/hooks/useTheme'
import { swapLocaleInPath } from '@/lib/localePath'
import en from '@/locales/en'
import ar from '@/locales/ar'

export type MarketingBranding = {
  app_name_en: string
  app_name_ar: string
  logo_url?: string | null
  support_email?: string | null
  support_phone?: string | null
  about_en?: string | null
  about_ar?: string | null
}

type Props = PropsWithChildren<{
  branding: MarketingBranding
  title?: string
  active?: 'home' | 'about' | 'solutions' | 'pricing' | 'contact' | 'privacy' | 'terms'
}>

export default function MarketingShell({ branding, title, active = 'home', children }: Props) {
  const { locale, direction, t } = useLocale()
  const { props } = usePage<{ auth?: { user?: { id: string } | null } }>()
  const isAuthenticated = Boolean(props.auth?.user)
  const { theme, setTheme } = useTheme()
  const messages = locale === 'ar' ? ar : en
  const appName = locale === 'ar' ? branding.app_name_ar : branding.app_name_en
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const mobileMenuRef = useRef<HTMLDivElement>(null)

  const closeMobileMenu = useCallback(() => setMobileMenuOpen(false), [])
  useClickOutside(mobileMenuRef, closeMobileMenu, mobileMenuOpen)

  const toggleLocale = () => {
    const next = locale === 'ar' ? 'en' : 'ar'
    const currentPath = `${window.location.pathname}${window.location.search}`
    document.cookie = `locale=${next};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`
    router.visit(swapLocaleInPath(currentPath, next), { preserveState: false })
    closeMobileMenu()
  }

  const navItems = [
    { key: 'home' as const, href: '/', label: t('marketingShellHome') },
    { key: 'about' as const, href: '/about', label: t('marketingShellAbout') },
    { key: 'solutions' as const, href: '/solutions', label: t('marketingShellSolutions') },
    { key: 'pricing' as const, href: '/#pricing', label: t('marketingShellPricing') },
    { key: 'contact' as const, href: '/contact', label: t('marketingShellContact') },
  ]

  return (
    <div className="landing-page" dir={direction}>
      <Head title={title ? `${title} · ${appName}` : appName} />
      <div className="landing-orb landing-orb-a" aria-hidden />
      <div className="landing-orb landing-orb-b" aria-hidden />
      <div className="landing-orb landing-orb-c" aria-hidden />

      <header className="landing-header">
        <div className="landing-header__inner">
          <LocalizedLink href="/" className="landing-header__brand">
            {branding.logo_url ? (
              <img src={branding.logo_url} alt="" className="h-10 w-10 shrink-0 rounded object-contain sm:h-11 sm:w-11" />
            ) : (
              <span className="ta-sidebar-brand-icon shrink-0">
                <CalendarDays className="h-5 w-5" />
              </span>
            )}
            <span className="truncate">{appName}</span>
          </LocalizedLink>

          <nav className="landing-header__nav" aria-label="Primary">
            {navItems.map((item) => (
              <LocalizedLink
                key={item.key}
                href={item.href}
                className={active === item.key ? 'is-active' : undefined}
              >
                {item.label}
              </LocalizedLink>
            ))}
          </nav>

          <div className="landing-header__toolbar">
            <button type="button" className="button-secondary cursor-pointer p-2" onClick={toggleLocale} aria-label={messages.toggleLocale}>
              <Globe className="h-4 w-4" />
              <span className="text-xs font-semibold">{locale === 'ar' ? messages.localeSwitchToEn : messages.localeSwitchToAr}</span>
            </button>
            <button type="button" className="button-secondary cursor-pointer p-2" onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')} aria-label={messages.theme}>
              {theme === 'dark' ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
            </button>

            <div className="landing-header__desktop-actions">
              {isAuthenticated ? (
                <LocalizedLink href="/dashboard" className="button-primary">{t('marketingShellCtaDashboard')}</LocalizedLink>
              ) : (
                <>
                  <LocalizedLink href="/login" className="button-secondary">{t('marketingShellCtaLogin')}</LocalizedLink>
                  <LocalizedLink href="/#pricing" className="button-primary">{t('marketingShellCtaRegister')}</LocalizedLink>
                </>
              )}
            </div>

            <button
              type="button"
              className="button-secondary landing-header__menu-toggle p-2"
              onClick={() => setMobileMenuOpen((open) => !open)}
              aria-expanded={mobileMenuOpen}
              aria-controls="marketing-mobile-menu"
              aria-label={mobileMenuOpen ? messages.closeMenu : messages.openMenu}
            >
              {mobileMenuOpen ? <X className="h-4 w-4" /> : <Menu className="h-4 w-4" />}
            </button>
          </div>
        </div>

        {mobileMenuOpen ? (
          <div id="marketing-mobile-menu" ref={mobileMenuRef} className="landing-header__mobile-menu">
            {navItems.map((item) => (
              <LocalizedLink key={item.key} href={item.href} onClick={closeMobileMenu}>
                {item.label}
              </LocalizedLink>
            ))}
            {isAuthenticated ? (
              <LocalizedLink href="/dashboard" className="button-primary w-full" onClick={closeMobileMenu}>
                {t('marketingShellCtaDashboard')}
              </LocalizedLink>
            ) : (
              <>
                <LocalizedLink href="/login" className="button-secondary w-full" onClick={closeMobileMenu}>
                  {t('marketingShellCtaLogin')}
                </LocalizedLink>
                <LocalizedLink href="/#pricing" className="button-primary w-full" onClick={closeMobileMenu}>
                  {t('marketingShellCtaRegister')}
                </LocalizedLink>
              </>
            )}
          </div>
        ) : null}
      </header>

      <main>{children}</main>

      <footer className="landing-footer">
        <div className="landing-footer__inner">
          <div className="landing-footer__columns">
            <div>
              <h4>{t('marketingShellPlatform')}</h4>
              <LocalizedLink href="/about">{t('marketingShellAbout')}</LocalizedLink>
              <LocalizedLink href="/solutions">{t('marketingShellSolutions')}</LocalizedLink>
              <LocalizedLink href="/#pricing">{t('marketingShellPricing')}</LocalizedLink>
            </div>
            <div>
              <h4>{t('marketingShellSupport')}</h4>
              <LocalizedLink href="/contact">{t('marketingShellContact')}</LocalizedLink>
              {branding.support_email ? <a href={`mailto:${branding.support_email}`}>{branding.support_email}</a> : null}
              {branding.support_phone ? <a href={`tel:${branding.support_phone}`}>{branding.support_phone}</a> : null}
            </div>
            <div>
              <h4>{t('marketingShellLegal')}</h4>
              <LocalizedLink href="/privacy">{t('marketingShellPrivacy')}</LocalizedLink>
              <LocalizedLink href="/terms">{t('marketingShellTerms')}</LocalizedLink>
            </div>
          </div>
          <div className="landing-footer__brand">
            <LocalizedLink href="/" className="landing-footer__logo" aria-label={appName}>
              {branding.logo_url ? (
                <img src={branding.logo_url} alt="" className="landing-footer__logo-image" />
              ) : (
                <span className="ta-sidebar-brand-icon shrink-0">
                  <CalendarDays className="h-5 w-5" />
                </span>
              )}
            </LocalizedLink>
            <p>{t('marketingShellFooter')}</p>
          </div>
        </div>
        <p className="landing-footer__copy">© {new Date().getFullYear()}. {t('marketingShellRights')}</p>
      </footer>
    </div>
  )
}
