import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import {
  ArrowRight,
  CalendarDays,
  CheckCircle2,
  Globe,
  Mail,
  Menu,
  Moon,
  Phone,
  ScanLine,
  ShieldCheck,
  Sparkles,
  Sun,
  Ticket,
  Users,
  X,
} from 'lucide-react'
import { useCallback, useRef, useState } from 'react'
import { useClickOutside } from '@/hooks/useClickOutside'
import { useLocale } from '@/hooks/useLocale'
import { useTheme } from '@/hooks/useTheme'
import { localizedPath, swapLocaleInPath } from '@/lib/localePath'
import en from '@/locales/en'
import ar from '@/locales/ar'

type Props = {
  app_name_en: string
  app_name_ar: string
  logo_url?: string | null
  favicon_url?: string | null
  support_email: string | null
  support_phone: string | null
  about_en: string | null
  about_ar: string | null
}

const copy = {
  en: {
    tagline: 'Enterprise event operations platform',
    heroTitle: 'Run world-class events with confidence',
    heroSubtitle:
      'Unify registration, identity verification, ticketing, on-site check-in, badges, wallet passes, and access control in one secure platform.',
    ctaLogin: 'Sign in',
    ctaRegister: 'Register as organizer',
    ctaFeatures: 'Explore capabilities',
    statsTitle: 'Designed for scale',
    featuresTitle: 'Everything your event team needs',
    aboutTitle: 'About the platform',
    contactTitle: 'Talk to our team',
    contactSubtitle: 'We help governments, enterprises, and organizers deliver secure large-scale experiences.',
    workflowTitle: 'From registration to gate access',
    workflowSteps: [
      'Configure events, tickets, and dynamic registration forms',
      'Verify identities and issue signed credentials',
      'Operate scanners, kiosks, badges, and manual desks on site',
      'Monitor access control zones, lanes, and real-time gate health',
    ],
    stats: [
      { label: 'Modules', value: '6+' },
      { label: 'Languages', value: 'AR / EN' },
      { label: 'Roles', value: 'RBAC' },
      { label: 'Audit', value: 'Immutable' },
    ],
    features: [
      { title: 'Registration & ticketing', text: 'Dynamic forms, ticket types, pricing tiers, and paid orders.' },
      { title: 'Identity verification', text: 'Consent flows, government verification, face capture, and reviewer workflows.' },
      { title: 'Credentials & wallet', text: 'Signed credentials, Apple/Google wallet passes, revoke and reissue controls.' },
      { title: 'On-site operations', text: 'Scanner, manual desk, kiosk pairing, badge templates, and print jobs.' },
      { title: 'Access control', text: 'ACS zones, lanes, rules, gate health, and emergency egress.' },
      { title: 'Governance', text: 'Tenant RBAC, platform administration, and immutable audit trails.' },
    ],
    footer: 'Built for regulated, high-trust events.',
    showcaseCaption: 'A unified console for every stage of your event lifecycle.',
  },
  ar: {
    tagline: 'منصة تشغيل فعاليات للمؤسسات',
    heroTitle: 'أدر فعاليات عالمية بثقة',
    heroSubtitle:
      'وحّد التسجيل والتحقق من الهوية والتذاكر وتسجيل الحضور والشارات وتذاكر المحفظة والتحكم في الوصول في منصة آمنة واحدة.',
    ctaLogin: 'تسجيل الدخول',
    ctaRegister: 'تسجيل منظم جديد',
    ctaFeatures: 'استكشف الإمكانيات',
    statsTitle: 'مصممة للنطاق الواسع',
    featuresTitle: 'كل ما يحتاجه فريق الفعالية',
    aboutTitle: 'عن المنصة',
    contactTitle: 'تواصل مع فريقنا',
    contactSubtitle: 'نساعد الجهات الحكومية والشركات والمنظمين على تنفيذ فعاليات كبيرة بأمان.',
    workflowTitle: 'من التسجيل حتى دخول البوابة',
    workflowSteps: [
      'إعداد الفعاليات والتذاكر ونماذج التسجيل الديناميكية',
      'التحقق من الهوية وإصدار بيانات دخول موقعة',
      'تشغيل الماسحات والأكشاك والشارات والمكاتب اليدوية في الموقع',
      'مراقبة مناطق التحكم في الوصول والممرات وصحة البوابات لحظياً',
    ],
    stats: [
      { label: 'الوحدات', value: '6+' },
      { label: 'اللغات', value: 'عربي / إنجليزي' },
      { label: 'الصلاحيات', value: 'RBAC' },
      { label: 'التدقيق', value: 'غير قابل للتلاعب' },
    ],
    features: [
      { title: 'التسجيل والتذاكر', text: 'نماذج ديناميكية وأنواع تذاكر وشرائح أسعار وطلبات مدفوعة.' },
      { title: 'التحقق من الهوية', text: 'موافقات وتحقق حكومي والتقاط الوجه وسير عمل المراجعة.' },
      { title: 'بيانات الدخول والمحفظة', text: 'بيانات موقعة وتذاكر محفظة وإلغاء وإعادة إصدار.' },
      { title: 'العمليات الميدانية', text: 'ماسح ومكتب يدوي وأكشاك وقوالب شارات وطباعة.' },
      { title: 'التحكم في الوصول', text: 'مناطق وممرات وقواعد وصحة البوابات وخروج طوارئ.' },
      { title: 'الحوكمة', text: 'صلاحيات المستأجر وإدارة المنصة وسجلات تدقيق غير قابلة للتلاعب.' },
    ],
    footer: 'مبنية للفعاليات عالية الثقة والامتثال.',
    showcaseCaption: 'لوحة تحكم موحدة لكل مراحل دورة حياة الفعالية.',
  },
}

const icons = [Ticket, ShieldCheck, ScanLine, Users, CalendarDays, ShieldCheck]
const screenshots = ['/landing/1.png', '/landing/2.png', '/landing/3.png', '/landing/4.png', '/landing/5.png']

export default function Landing({
  app_name_en,
  app_name_ar,
  logo_url,
  support_email,
  support_phone,
  about_en,
  about_ar,
}: Props) {
  const { locale, direction } = useLocale()
  const { theme, setTheme } = useTheme()
  const messages = locale === 'ar' ? ar : en
  const t = copy[locale]
  const appName = locale === 'ar' ? app_name_ar : app_name_en
  const about = locale === 'ar' ? about_ar : about_en
  const contactEmail = support_email ?? 'hello@zonetec.com'
  const contactPhone = support_phone ?? '+20 100 000 0000'
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const mobileMenuRef = useRef<HTMLDivElement>(null)

  const closeMobileMenu = useCallback(() => {
    setMobileMenuOpen(false)
  }, [])

  useClickOutside(mobileMenuRef, closeMobileMenu, mobileMenuOpen)

  const toggleLocale = () => {
    const next = locale === 'ar' ? 'en' : 'ar'
    const currentPath = `${window.location.pathname}${window.location.search}`
    document.cookie = `locale=${next};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`
    router.visit(swapLocaleInPath(currentPath, next), { preserveState: false })
    closeMobileMenu()
  }

  const toggleTheme = () => {
    setTheme(theme === 'dark' ? 'light' : 'dark')
  }

  return (
    <div className="min-h-screen overflow-x-hidden bg-[var(--surface)] text-[var(--ink)]" dir={direction}>
      <div className="landing-orb landing-orb-a" aria-hidden />
      <div className="landing-orb landing-orb-b" aria-hidden />

      <header className="landing-header sticky top-0 z-40 border-b border-[var(--border)] bg-[var(--surface-elevated)]/90 backdrop-blur">
        <div className="landing-header__inner">
          <div className="landing-header__brand landing-fade-in">
            {logo_url ? (
              <img src={logo_url} alt="" className="h-10 w-10 shrink-0 rounded object-contain sm:h-12 sm:w-12" />
            ) : (
              <span className="ta-sidebar-brand-icon shrink-0">
                <CalendarDays className="h-5 w-5" />
              </span>
            )}
            <span className="truncate">{appName}</span>
          </div>

          <div className="landing-header__toolbar landing-fade-in landing-delay-1">
            <button
              type="button"
              className="button-secondary cursor-pointer p-2"
              onClick={toggleLocale}
              aria-label={messages.toggleLocale}
            >
              <Globe className="h-4 w-4" />
              <span className="text-xs font-semibold">{locale === 'ar' ? 'EN' : 'ع'}</span>
            </button>
            <button
              type="button"
              className="button-secondary cursor-pointer p-2"
              onClick={toggleTheme}
              aria-label={messages.theme}
            >
              {theme === 'dark' ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
            </button>

            <div className="landing-header__desktop-actions">
              <LocalizedLink href={localizedPath(locale, '/login')} className="button-secondary">
                {t.ctaLogin}
              </LocalizedLink>
              <LocalizedLink href={localizedPath(locale, '/register')} className="button-primary">
                {t.ctaRegister}
              </LocalizedLink>
            </div>

            <button
              type="button"
              className="button-secondary landing-header__menu-toggle p-2"
              onClick={() => setMobileMenuOpen((open) => !open)}
              aria-expanded={mobileMenuOpen}
              aria-controls="landing-mobile-menu"
              aria-label={mobileMenuOpen ? messages.closeMenu : messages.openMenu}
            >
              {mobileMenuOpen ? <X className="h-4 w-4" /> : <Menu className="h-4 w-4" />}
            </button>
          </div>
        </div>

        {mobileMenuOpen ? (
          <div
            id="landing-mobile-menu"
            ref={mobileMenuRef}
            className="landing-header__mobile-menu"
          >
            <LocalizedLink
              href={localizedPath(locale, '/login')}
              className="button-secondary w-full"
              onClick={closeMobileMenu}
            >
              {t.ctaLogin}
            </LocalizedLink>
            <LocalizedLink
              href={localizedPath(locale, '/register')}
              className="button-primary w-full"
              onClick={closeMobileMenu}
            >
              {t.ctaRegister}
            </LocalizedLink>
          </div>
        ) : null}
      </header>

      <section className="relative">
        <div className="absolute inset-0 bg-gradient-to-br from-[var(--brand-soft)] via-transparent to-transparent" />
        <div className="relative mx-auto grid max-w-[80%] gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:items-center lg:py-24">
          <div className="landing-slide-up">
            <p className="mb-3 inline-flex items-center gap-2 rounded-full bg-[var(--brand-soft)] px-3 py-1 text-sm font-semibold text-[var(--brand)]">
              <Sparkles className="h-4 w-4" />
              {t.tagline}
            </p>
            <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">{t.heroTitle}</h1>
            <p className="mt-4 text-lg text-[var(--muted)]">{t.heroSubtitle}</p>
            <div className="mt-8 flex flex-wrap gap-3">
              <LocalizedLink href={localizedPath(locale, '/register')} className="button-primary inline-flex items-center gap-2">
                {t.ctaRegister}
                <ArrowRight className="h-4 w-4" />
              </LocalizedLink>
              <LocalizedLink href={localizedPath(locale, '/login')} className="button-secondary inline-flex items-center gap-2">
                {t.ctaLogin}
              </LocalizedLink>
              <a href="#features" className="button-secondary">
                {t.ctaFeatures}
              </a>
            </div>
          </div>
          <div className="landing-float grid grid-cols-2 gap-4">
            {t.stats.map((stat, index) => (
              <article key={stat.label} className={`ta-card landing-fade-in landing-delay-${index + 1}`}>
                <p className="text-sm text-[var(--muted)]">{stat.label}</p>
                <p className="mt-2 text-2xl font-bold">{stat.value}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-[80%] px-4 py-12 sm:px-6">
        <div className="landing-slide-up text-center">
          <h2 className="text-2xl font-bold sm:text-3xl">{t.statsTitle}</h2>
          <p className="mx-auto mt-3 max-w-2xl text-[var(--muted)]">{t.showcaseCaption}</p>
        </div>
        <div className="mt-8 grid gap-4 lg:grid-cols-12">
          <figure className="landing-fade-in overflow-hidden rounded-2xl border border-[var(--border)] bg-[var(--surface-elevated)] shadow-[var(--card-shadow)] lg:col-span-7">
            <img
              src={screenshots[0]}
              alt={`${appName} dashboard`}
              className="h-72 w-full object-cover object-top transition duration-500 hover:scale-[1.02] sm:h-96"
              loading="lazy"
            />
          </figure>
          <div className="grid gap-4 sm:grid-cols-2 lg:col-span-5">
            {screenshots.slice(1).map((src, index) => (
              <figure
                key={src}
                className={`landing-fade-in landing-delay-${index + 1} overflow-hidden rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] shadow-[var(--card-shadow)]`}
              >
                <img
                  src={src}
                  alt={`${appName} screenshot ${index + 2}`}
                  className="h-40 w-full object-cover object-top transition duration-500 hover:scale-105"
                  loading="lazy"
                />
              </figure>
            ))}
          </div>
        </div>
      </section>

      <section id="features" className="mx-auto max-w-[80%] px-4 py-16 sm:px-6">
        <h2 className="landing-slide-up text-3xl font-bold">{t.featuresTitle}</h2>
        <div className="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {t.features.map((feature, index) => {
            const Icon = icons[index] ?? Ticket

            return (
              <article key={feature.title} className={`ta-card landing-fade-in landing-delay-${(index % 3) + 1}`}>
                <span className="ta-stat-icon mb-4">
                  <Icon className="h-5 w-5" />
                </span>
                <h3 className="text-lg font-semibold">{feature.title}</h3>
                <p className="mt-2 text-sm text-[var(--muted)]">{feature.text}</p>
              </article>
            )
          })}
        </div>
      </section>

      <section className="bg-[var(--surface-elevated)] py-16">
        <div className="mx-auto grid max-w-[80%] gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:items-center">
          <div className="landing-slide-up">
            <h2 className="text-3xl font-bold">{t.workflowTitle}</h2>
            <ul className="mt-6 space-y-4">
              {t.workflowSteps.map((step) => (
                <li key={step} className="flex items-start gap-3 text-[var(--muted)]">
                  <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-[var(--brand)]" />
                  <span>{step}</span>
                </li>
              ))}
            </ul>
          </div>
          <article className="ta-card landing-float p-8">
            <h3 className="text-xl font-semibold">{t.aboutTitle}</h3>
            <p className="mt-4 whitespace-pre-wrap text-[var(--muted)]">
              {about ?? t.heroSubtitle}
            </p>
          </article>
        </div>
      </section>

      <section className="mx-auto max-w-[80%] px-4 py-16 sm:px-6">
        <div className="landing-slide-up grid gap-8 lg:grid-cols-2">
          <div>
            <h2 className="text-3xl font-bold">{t.contactTitle}</h2>
            <p className="mt-3 text-[var(--muted)]">{t.contactSubtitle}</p>
          </div>
          <div className="ta-card space-y-4">
            <p className="flex items-center gap-3 text-sm">
              <Mail className="h-4 w-4 text-[var(--brand)]" />
              <a href={`mailto:${contactEmail}`} className="hover:underline">{contactEmail}</a>
            </p>
            <p className="flex items-center gap-3 text-sm">
              <Phone className="h-4 w-4 text-[var(--brand)]" />
              <a href={`tel:${contactPhone}`} className="hover:underline">{contactPhone}</a>
            </p>
            <div className="flex flex-wrap gap-3 pt-2">
              <LocalizedLink href={localizedPath(locale, '/register')} className="button-primary">{t.ctaRegister}</LocalizedLink>
              <LocalizedLink href={localizedPath(locale, '/login')} className="button-secondary">{t.ctaLogin}</LocalizedLink>
            </div>
          </div>
        </div>
      </section>

      <footer className="border-t border-[var(--border)] py-8 text-center text-sm text-[var(--muted)]">
        © {appName}. {t.footer}
      </footer>
    </div>
  )
}
