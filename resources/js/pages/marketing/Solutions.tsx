import MarketingShell, { type MarketingBranding } from '@/layouts/MarketingShell'
import { useLocale } from '@/hooks/useLocale'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { localizedPath } from '@/lib/localePath'
import {
  ArrowRight,
  BadgeCheck,
  Fingerprint,
  Printer,
  ScanLine,
  ShieldCheck,
  Ticket,
  Wallet,
} from 'lucide-react'

type Props = MarketingBranding

const solutionsStructure = {
  modules: [
    { title: 'solutionsPageModule1Title', text: 'solutionsPageModule1Text', icon: 'ticket' as const },
    { title: 'solutionsPageModule2Title', text: 'solutionsPageModule2Text', icon: 'fingerprint' as const },
    { title: 'solutionsPageModule3Title', text: 'solutionsPageModule3Text', icon: 'wallet' as const },
    { title: 'solutionsPageModule4Title', text: 'solutionsPageModule4Text', icon: 'printer' as const },
    { title: 'solutionsPageModule5Title', text: 'solutionsPageModule5Text', icon: 'scan' as const },
    { title: 'solutionsPageModule6Title', text: 'solutionsPageModule6Text', icon: 'shield' as const },
    { title: 'solutionsPageModule7Title', text: 'solutionsPageModule7Text', icon: 'badge' as const },
    { title: 'solutionsPageModule8Title', text: 'solutionsPageModule8Text', icon: 'shield' as const },
  ],
  scenarios: [
    { title: 'solutionsPageScenario1Title', text: 'solutionsPageScenario1Text' },
    { title: 'solutionsPageScenario2Title', text: 'solutionsPageScenario2Text' },
    { title: 'solutionsPageScenario3Title', text: 'solutionsPageScenario3Text' },
    { title: 'solutionsPageScenario4Title', text: 'solutionsPageScenario4Text' },
  ],
  why: [
    'solutionsPageWhy1',
    'solutionsPageWhy2',
    'solutionsPageWhy3',
    'solutionsPageWhy4',
  ],
} as const

const icons = {
  ticket: Ticket,
  fingerprint: Fingerprint,
  wallet: Wallet,
  printer: Printer,
  scan: ScanLine,
  shield: ShieldCheck,
  badge: BadgeCheck,
} as const

export default function Solutions(props: Props) {
  const { locale, t } = useLocale()

  return (
    <MarketingShell branding={props} title={t('solutionsPageTitle')} active="solutions">
      <section className="mkt-hero">
        <div className="mkt-hero__inner">
          <p className="mkt-eyebrow">{t('solutionsPageEyebrow')}</p>
          <h1 className="mkt-hero__title">{t('solutionsPageHero')}</h1>
          <p className="mkt-hero__lead">{t('solutionsPageLead')}</p>
        </div>
      </section>

      <section className="mkt-section">
        <h2 className="mkt-section__title">{t('solutionsPageModulesTitle')}</h2>
        <div className="mkt-card-grid">
          {solutionsStructure.modules.map((module) => {
            const Icon = icons[module.icon] ?? Ticket
            return (
              <article key={module.title} className="mkt-card">
                <span className="mkt-card__icon"><Icon className="h-5 w-5" /></span>
                <h3>{t(module.title)}</h3>
                <p>{t(module.text)}</p>
              </article>
            )
          })}
        </div>
      </section>

      <section className="mkt-band">
        <div className="mkt-section">
          <h2 className="mkt-section__title">{t('solutionsPageScenariosTitle')}</h2>
          <div className="mkt-card-grid mkt-card-grid--2">
            {solutionsStructure.scenarios.map((scenario) => (
              <article key={scenario.title} className="mkt-card">
                <h3>{t(scenario.title)}</h3>
                <p>{t(scenario.text)}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="mkt-section">
        <h2 className="mkt-section__title">{t('solutionsPageWhyTitle')}</h2>
        <ul className="mkt-checklist">
          {solutionsStructure.why.map((item) => (
            <li key={item}>
              <ShieldCheck className="h-5 w-5 shrink-0 text-[var(--brand)]" />
              <span>{t(item)}</span>
            </li>
          ))}
        </ul>
      </section>

      <section className="mkt-cta">
        <div className="mkt-cta__inner">
          <h2>{t('solutionsPageCtaTitle')}</h2>
          <div className="mkt-cta__actions">
            <LocalizedLink href={localizedPath(locale, '/#pricing')} className="button-primary inline-flex items-center gap-2">
              {t('solutionsPageCtaPricing')}
              <ArrowRight className="h-4 w-4 rtl:rotate-180" />
            </LocalizedLink>
            <LocalizedLink href={localizedPath(locale, '/contact')} className="button-secondary">{t('solutionsPageCtaContact')}</LocalizedLink>
          </div>
        </div>
      </section>
    </MarketingShell>
  )
}
