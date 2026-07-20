import MarketingShell, { type MarketingBranding } from '@/layouts/MarketingShell'
import { useLocale } from '@/hooks/useLocale'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { localizedPath } from '@/lib/localePath'
import { ArrowRight, Building2, CheckCircle2, Globe2, ShieldCheck, Users } from 'lucide-react'

type Props = MarketingBranding

const aboutStructure = {
  story: ['aboutPageStoryPara1', 'aboutPageStoryPara2'],
  values: [
    { title: 'aboutPageValue1Title', text: 'aboutPageValue1Text' },
    { title: 'aboutPageValue2Title', text: 'aboutPageValue2Text' },
    { title: 'aboutPageValue3Title', text: 'aboutPageValue3Text' },
    { title: 'aboutPageValue4Title', text: 'aboutPageValue4Text' },
  ],
  who: [
    { title: 'aboutPageWho1Title', text: 'aboutPageWho1Text' },
    { title: 'aboutPageWho2Title', text: 'aboutPageWho2Text' },
    { title: 'aboutPageWho3Title', text: 'aboutPageWho3Text' },
  ],
} as const

export default function About(props: Props) {
  const { locale, t } = useLocale()
  const icons = [ShieldCheck, Globe2, Users, Building2]

  return (
    <MarketingShell branding={props} title={t('aboutPageTitle')} active="about">
      <section className="mkt-hero">
        <div className="mkt-hero__inner">
          <p className="mkt-eyebrow">{t('aboutPageEyebrow')}</p>
          <h1 className="mkt-hero__title">{t('aboutPageHero')}</h1>
          <p className="mkt-hero__lead">{t('aboutPageIntro')}</p>
        </div>
      </section>

      <section className="mkt-section">
        <div className="mkt-prose">
          <h2>{t('aboutPageStoryTitle')}</h2>
          {aboutStructure.story.map((key) => <p key={key}>{t(key)}</p>)}
          {(locale === 'ar' ? props.about_ar : props.about_en) ? (
            <p className="mkt-callout">{locale === 'ar' ? props.about_ar : props.about_en}</p>
          ) : null}
        </div>
      </section>

      <section className="mkt-band">
        <div className="mkt-section">
          <h2 className="mkt-section__title">{t('aboutPageValuesTitle')}</h2>
          <div className="mkt-card-grid">
            {aboutStructure.values.map((value, index) => {
              const Icon = icons[index] ?? CheckCircle2
              return (
                <article key={value.title} className="mkt-card">
                  <span className="mkt-card__icon"><Icon className="h-5 w-5" /></span>
                  <h3>{t(value.title)}</h3>
                  <p>{t(value.text)}</p>
                </article>
              )
            })}
          </div>
        </div>
      </section>

      <section className="mkt-section">
        <h2 className="mkt-section__title">{t('aboutPageWhoTitle')}</h2>
        <div className="mkt-card-grid mkt-card-grid--3">
          {aboutStructure.who.map((item) => (
            <article key={item.title} className="mkt-card">
              <h3>{t(item.title)}</h3>
              <p>{t(item.text)}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="mkt-cta">
        <div className="mkt-cta__inner">
          <h2>{t('aboutPageCtaTitle')}</h2>
          <p>{t('aboutPageCtaText')}</p>
          <div className="mkt-cta__actions">
            <LocalizedLink href={localizedPath(locale, '/solutions')} className="button-primary inline-flex items-center gap-2">
              {t('aboutPageCtaSolutions')}
              <ArrowRight className="h-4 w-4 rtl:rotate-180" />
            </LocalizedLink>
            <LocalizedLink href={localizedPath(locale, '/contact')} className="button-secondary">{t('aboutPageCtaContact')}</LocalizedLink>
          </div>
        </div>
      </section>
    </MarketingShell>
  )
}
