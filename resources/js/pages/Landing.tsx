import { usePage } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import {
  ArrowRight,
  BadgeCheck,
  Building2,
  CheckCircle2,
  ChevronDown,
  Fingerprint,
  Landmark,
  Mail,
  Phone,
  Printer,
  ScanLine,
  ShieldCheck,
  Ticket,
  Users,
  Wallet,
} from 'lucide-react'
import { useEffect, useRef, useState, type ReactNode } from 'react'
import { useLocale } from '@/hooks/useLocale'
import MarketingShell from '@/layouts/MarketingShell'
import { localizedPath } from '@/lib/localePath'

type SubscriptionPlan = {
  id: string
  name: string
  name_ar: string | null
  description: string | null
  description_ar: string | null
  is_trial: boolean
  duration_days: number
  price: string
  currency: string
  max_events: number | null
  max_attendees: number | null
  max_devices: number | null
}

type Props = {
  app_name_en: string
  app_name_ar: string
  logo_url?: string | null
  favicon_url?: string | null
  support_email: string | null
  support_phone: string | null
  about_en: string | null
  about_ar: string | null
  plans?: SubscriptionPlan[]
}

const screenshots = ['/landing/1.png', '/landing/2.png', '/landing/3.png', '/landing/4.png', '/landing/5.png']

// All copy content moved to locale files (en.ts/ar.ts) under landing* keys
// Features/audiences/faqs/workflow arrays are now built using t() calls below

const landingStructure = {
  audiences: [
    { title: 'landingAudienceCorporateTitle', text: 'landingAudienceCorporateText', icon: 'building' as const },
    { title: 'landingAudienceGovTitle', text: 'landingAudienceGovText', icon: 'landmark' as const },
    { title: 'landingAudienceVenueTitle', text: 'landingAudienceVenueText', icon: 'users' as const },
  ],
  features: [
    { title: 'landingFeatureRegTitle', text: 'landingFeatureRegText', icon: 'ticket' as const },
    { title: 'landingFeatureIdTitle', text: 'landingFeatureIdText', icon: 'fingerprint' as const },
    { title: 'landingFeatureCredTitle', text: 'landingFeatureCredText', icon: 'wallet' as const },
    { title: 'landingFeatureOpsTitle', text: 'landingFeatureOpsText', icon: 'scan' as const },
    { title: 'landingFeatureAcsTitle', text: 'landingFeatureAcsText', icon: 'shield' as const },
    { title: 'landingFeatureBadgeTitle', text: 'landingFeatureBadgeText', icon: 'printer' as const },
    { title: 'landingFeatureCategoryTitle', text: 'landingFeatureCategoryText', icon: 'badge' as const },
    { title: 'landingFeatureGovTitle', text: 'landingFeatureGovText', icon: 'shield' as const },
  ],
  workflowSteps: [
    { title: 'landingWorkflowPlanTitle', text: 'landingWorkflowPlanText' },
    { title: 'landingWorkflowVerifyTitle', text: 'landingWorkflowVerifyText' },
    { title: 'landingWorkflowIssueTitle', text: 'landingWorkflowIssueText' },
    { title: 'landingWorkflowOperateTitle', text: 'landingWorkflowOperateText' },
  ],
  securityPoints: [
    'landingSecurityPoint1',
    'landingSecurityPoint2',
    'landingSecurityPoint3',
    'landingSecurityPoint4',
  ],
  useCases: [
    { title: 'landingUseCaseConferencesTitle', text: 'landingUseCaseConferencesText' },
    { title: 'landingUseCaseWorkshopsTitle', text: 'landingUseCaseWorkshopsText' },
    { title: 'landingUseCaseLaunchesTitle', text: 'landingUseCaseLaunchesText' },
    { title: 'landingUseCaseToursTitle', text: 'landingUseCaseToursText' },
  ],
  faqs: [
    { q: 'landingFaqQ1', a: 'landingFaqA1' },
    { q: 'landingFaqQ2', a: 'landingFaqA2' },
    { q: 'landingFaqQ3', a: 'landingFaqA3' },
    { q: 'landingFaqQ4', a: 'landingFaqA4' },
  ],
} as const

const featureIcons = {
  ticket: Ticket,
  fingerprint: Fingerprint,
  wallet: Wallet,
  scan: ScanLine,
  shield: ShieldCheck,
  printer: Printer,
  badge: BadgeCheck,
  building: Building2,
  landmark: Landmark,
  users: Users,
} as const

function Reveal({ children, className = '', delay = 0 }: { children: ReactNode; className?: string; delay?: number }) {
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const node = ref.current
    if (!node) {
      return
    }

    const markVisible = () => {
      node.classList.add('is-visible')
    }

    const isInViewport = () => {
      const rect = node.getBoundingClientRect()
      const viewportHeight = window.innerHeight || document.documentElement.clientHeight
      return rect.top < viewportHeight && rect.bottom > 0
    }

    if (isInViewport()) {
      markVisible()
      return
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) {
          markVisible()
          observer.unobserve(node)
        }
      },
      { threshold: 0.05, rootMargin: '40px 0px 40px 0px' },
    )

    observer.observe(node)
    return () => observer.disconnect()
  }, [])

  return (
    <div ref={ref} className={`landing-reveal ${className}`} style={{ transitionDelay: `${delay}ms` }}>
      {children}
    </div>
  )
}

function scrollToLandingHash() {
  const id = window.location.hash.replace(/^#/, '')
  if (!id) {
    return
  }

  const target = document.getElementById(id)
  if (!target) {
    return
  }

  target.querySelectorAll('.landing-reveal').forEach((element) => {
    element.classList.add('is-visible')
  })

  const header = document.querySelector('.landing-header')
  const headerOffset = header instanceof HTMLElement ? header.getBoundingClientRect().height + 12 : 88
  const top = target.getBoundingClientRect().top + window.scrollY - headerOffset

  window.scrollTo({ top: Math.max(0, top), behavior: 'auto' })
}

export default function Landing({
  app_name_en,
  app_name_ar,
  logo_url,
  support_email,
  support_phone,
  about_en,
  about_ar,
  plans = [],
}: Props) {
  const { locale, t } = useLocale()
  const { props } = usePage<{ auth?: { user?: { id: string } | null } }>()
  const isAuthenticated = Boolean(props.auth?.user)
  const appName = locale === 'ar' ? app_name_ar : app_name_en
  const about = locale === 'ar' ? about_ar : about_en
  const contactEmail = support_email ?? 'hello@zonetec.com'
  const contactPhone = support_phone ?? '+20 100 000 0000'
  const [openFaq, setOpenFaq] = useState<number | null>(0)
  const featuredPlanIndex =
    plans.length >= 3 ? 1 : Math.max(0, plans.findIndex((plan) => Number(plan.price) > 0))

  useEffect(() => {
    const run = () => {
      scrollToLandingHash()
    }

    const frame = window.requestAnimationFrame(() => {
      window.requestAnimationFrame(run)
    })
    const timeout = window.setTimeout(run, 120)

    window.addEventListener('hashchange', run)
    return () => {
      window.cancelAnimationFrame(frame)
      window.clearTimeout(timeout)
      window.removeEventListener('hashchange', run)
    }
  }, [plans.length])

  return (
    <MarketingShell
      branding={{ app_name_en, app_name_ar, logo_url, support_email, support_phone, about_en, about_ar }}
      active="home"
    >
      <section id="top" className="landing-hero" aria-label={appName}>
        <div className="landing-hero__glow" aria-hidden />
        <div className="landing-hero__inner">
          <div className="landing-hero__copy">
            <p className="landing-brand landing-anim-brand">{appName}</p>
            <h1 className="landing-hero__headline landing-anim-headline">{t('landingHeroTitle')}</h1>
            <p className="landing-hero__support landing-anim-support">{t('landingHeroSubtitle')}</p>
            <div className="landing-hero__actions landing-anim-actions">
              {isAuthenticated ? (
                <LocalizedLink href={localizedPath(locale, '/dashboard')} className="button-primary inline-flex items-center gap-2">
                  {t('landingCtaDashboard')}
                  <ArrowRight className="h-4 w-4 rtl:rotate-180" />
                </LocalizedLink>
              ) : (
                <>
                  <a href="#pricing" className="button-primary inline-flex items-center gap-2">
                    {t('landingCtaRegister')}
                    <ArrowRight className="h-4 w-4 rtl:rotate-180" />
                  </a>
                  <LocalizedLink href={localizedPath(locale, '/solutions')} className="button-secondary">{t('landingCtaExplore')}</LocalizedLink>
                </>
              )}
            </div>
          </div>
          <div className="landing-hero__visual landing-anim-visual">
            <div className="landing-hero__frame">
              <img src={screenshots[0]} alt="" className="landing-hero__image" />
            </div>
          </div>
        </div>
      </section>

      <section id="showcase" className="landing-section">
        <Reveal>
          <h2 className="landing-section__title">{t('landingShowcaseTitle')}</h2>
          <p className="landing-section__subtitle">{t('landingShowcaseSubtitle')}</p>
        </Reveal>
        <div className="landing-showcase">
          <Reveal className="landing-showcase__main" delay={80}>
            <img src={screenshots[0]} alt={`${appName} dashboard`} loading="lazy" />
          </Reveal>
          <div className="landing-showcase__side">
            {screenshots.slice(1).map((src, index) => (
              <Reveal key={src} delay={120 + index * 70}>
                <figure className="landing-showcase__thumb">
                  <img src={src} alt={`${appName} screen ${index + 2}`} loading="lazy" />
                </figure>
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      <section className="landing-band">
        <div className="landing-section">
          <Reveal>
            <h2 className="landing-section__title">{t('landingAudiencesTitle')}</h2>
            <p className="landing-section__subtitle">{t('landingAudiencesSubtitle')}</p>
          </Reveal>
          <div className="landing-audience-grid">
            {landingStructure.audiences.map((audience, index) => {
              const Icon = featureIcons[audience.icon] ?? Building2
              return (
                <Reveal key={audience.title} delay={index * 90}>
                  <article className="landing-audience">
                    <span className="landing-audience__icon"><Icon className="h-5 w-5" /></span>
                    <h3>{t(audience.title)}</h3>
                    <p>{t(audience.text)}</p>
                  </article>
                </Reveal>
              )
            })}
          </div>
        </div>
      </section>

      <section id="features" className="landing-section">
        <Reveal>
          <h2 className="landing-section__title">{t('landingFeaturesTitle')}</h2>
          <p className="landing-section__subtitle">{t('landingFeaturesSubtitle')}</p>
        </Reveal>
        <div className="landing-feature-grid">
          {landingStructure.features.map((feature, index) => {
            const Icon = featureIcons[feature.icon] ?? Ticket
            return (
              <Reveal key={feature.title} delay={(index % 4) * 70}>
                <article className="landing-feature">
                  <span className="landing-feature__icon"><Icon className="h-5 w-5" /></span>
                  <h3>{t(feature.title)}</h3>
                  <p>{t(feature.text)}</p>
                </article>
              </Reveal>
            )
          })}
        </div>
      </section>

      <section id="workflow" className="landing-workflow">
        <div className="landing-section">
          <Reveal className="max-w-2xl">
            <h2 className="landing-section__title">{t('landingWorkflowTitle')}</h2>
            <p className="landing-section__subtitle">{t('landingWorkflowSubtitle')}</p>
            {about ? <p className="landing-about">{about}</p> : null}
          </Reveal>
          <ol className="landing-workflow__list">
            {landingStructure.workflowSteps.map((step, index) => (
              <Reveal key={step.title} delay={index * 100}>
                <li className="landing-workflow__step">
                  <span className="landing-workflow__index">{String(index + 1).padStart(2, '0')}</span>
                  <h3>{t(step.title)}</h3>
                  <p>{t(step.text)}</p>
                </li>
              </Reveal>
            ))}
          </ol>
        </div>
      </section>

      <section className="landing-section landing-split">
        <Reveal>
          <h2 className="landing-section__title">{t('landingSecurityTitle')}</h2>
          <p className="landing-section__subtitle">{t('landingSecuritySubtitle')}</p>
          <ul className="landing-security-list">
            {landingStructure.securityPoints.map((point) => (
              <li key={point}>
                <CheckCircle2 className="h-5 w-5 shrink-0 text-[var(--brand)]" />
                <span>{t(point)}</span>
              </li>
            ))}
          </ul>
        </Reveal>
        <Reveal delay={120}>
          <div className="landing-security-visual">
            <img src={screenshots[2]} alt="" loading="lazy" />
          </div>
        </Reveal>
      </section>

      <section className="landing-band">
        <div className="landing-section">
          <Reveal>
            <h2 className="landing-section__title">{t('landingUseCasesTitle')}</h2>
            <p className="landing-section__subtitle">{t('landingUseCasesSubtitle')}</p>
          </Reveal>
          <div className="landing-usecase-grid">
            {landingStructure.useCases.map((item, index) => (
              <Reveal key={item.title} delay={index * 80}>
                <article className="landing-usecase">
                  <h3>{t(item.title)}</h3>
                  <p>{t(item.text)}</p>
                </article>
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      {plans.length > 0 ? (
        <section id="pricing" className="landing-section">
          <Reveal className="text-center sm:mx-auto sm:max-w-2xl">
            <h2 className="landing-section__title">{t('landingPricingTitle')}</h2>
            <p className="landing-section__subtitle mx-auto">{t('landingPricingSubtitle')}</p>
          </Reveal>
          <div className="landing-pricing-grid">
            {plans.map((plan, index) => {
              const planName = locale === 'ar' ? plan.name_ar || plan.name : plan.name
              const planDesc = locale === 'ar' ? plan.description_ar || plan.description : plan.description
              const isFree = Number(plan.price) === 0
              const isFeatured = index === featuredPlanIndex
              const limits = [
                { label: t('landingPricingEvents'), value: plan.max_events ?? t('landingPricingUnlimited') },
                { label: t('landingPricingAttendees'), value: plan.max_attendees ?? t('landingPricingUnlimited') },
                { label: t('landingPricingDevices'), value: plan.max_devices ?? t('landingPricingUnlimited') },
              ]

              return (
                <Reveal key={plan.id} delay={(index % 3) * 90}>
                  <article className={`landing-plan${isFeatured ? ' landing-plan--featured' : ''}`}>
                    {isFeatured ? <span className="landing-plan__ribbon">{t('landingPricingPopular')}</span> : null}
                    <div className="landing-plan__top">
                      <div className={`landing-plan__heading${isFeatured ? ' landing-plan__heading--featured' : ''}`}>
                        <h3>{planName}</h3>
                        {plan.is_trial ? <span className="landing-plan__badge">{t('landingPricingTrial')}</span> : null}
                      </div>
                      {planDesc ? <p className="landing-plan__desc">{planDesc}</p> : null}
                      <div className="landing-plan__price-block">
                        {isFree ? (
                          <>
                            <p className="landing-plan__price">{t('landingPricingFree')}</p>
                            <p className="landing-plan__period">
                              {plan.duration_days} {t('landingPricingDays')}
                            </p>
                          </>
                        ) : (
                          <>
                            <p className="landing-plan__price">
                              <span className="landing-plan__currency">{plan.currency}</span>
                              {plan.price}
                            </p>
                            <p className="landing-plan__period">
                              {t('landingPricingPer')} {plan.duration_days} {t('landingPricingDays')}
                            </p>
                          </>
                        )}
                      </div>
                    </div>
                    <ul className="landing-plan__limits">
                      {limits.map((row) => (
                        <li key={row.label}>
                          <span>{row.label}</span>
                          <strong>{row.value}</strong>
                        </li>
                      ))}
                    </ul>
                    <LocalizedLink
                      href={localizedPath(locale, `/subscribe/${plan.id}`)}
                      className={`landing-plan__cta ${isFeatured ? 'button-primary' : 'button-secondary'} w-full text-center`}
                    >
                      {t('landingPricingGetStarted')}
                    </LocalizedLink>
                  </article>
                </Reveal>
              )
            })}
          </div>
        </section>
      ) : null}

      <section className="landing-section">
        <Reveal>
          <h2 className="landing-section__title">{t('landingFaqTitle')}</h2>
          <p className="landing-section__subtitle">{t('landingFaqSubtitle')}</p>
        </Reveal>
        <div className="landing-faq">
          {landingStructure.faqs.map((faq, index) => {
            const open = openFaq === index
            return (
              <Reveal key={faq.q} delay={index * 60}>
                <div className={`landing-faq__item${open ? ' is-open' : ''}`}>
                  <button
                    type="button"
                    className="landing-faq__trigger"
                    aria-expanded={open}
                    onClick={() => setOpenFaq(open ? null : index)}
                  >
                    <span>{t(faq.q)}</span>
                    <ChevronDown className="landing-faq__chevron" />
                  </button>
                  <div className="landing-faq__panel" hidden={!open}>
                    <p>{t(faq.a)}</p>
                  </div>
                </div>
              </Reveal>
            )
          })}
        </div>
      </section>

      <section id="contact" className="landing-cta-band">
        <div className="landing-section">
          <div className="landing-contact">
            <Reveal>
              <h2 className="landing-section__title">{t('landingContactTitle')}</h2>
              <p className="landing-section__subtitle">{t('landingContactSubtitle')}</p>
              <div className="mt-6 flex flex-wrap gap-3">
                <LocalizedLink href={localizedPath(locale, '/contact')} className="button-primary">
                  {t('landingContactPage')}
                </LocalizedLink>
                <a href="#pricing" className="button-secondary">{t('landingCtaRegister')}</a>
              </div>
            </Reveal>
            <Reveal delay={100}>
              <div className="landing-contact__panel">
                <h3 className="mb-4 text-lg font-semibold">{t('landingContactPanelTitle')}</h3>
                <p className="flex items-center gap-3 text-sm">
                  <Mail className="h-4 w-4 text-[var(--brand)]" />
                  <a href={`mailto:${contactEmail}`} className="hover:underline">{contactEmail}</a>
                </p>
                <p className="mt-3 flex items-center gap-3 text-sm">
                  <Phone className="h-4 w-4 text-[var(--brand)]" />
                  <a href={`tel:${contactPhone}`} className="hover:underline">{contactPhone}</a>
                </p>
              </div>
            </Reveal>
          </div>
        </div>
      </section>
    </MarketingShell>
  )
}
