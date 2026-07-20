import MarketingShell, { type MarketingBranding } from '@/layouts/MarketingShell'
import { useLocale } from '@/hooks/useLocale'

type Props = MarketingBranding

const privacyStructure = {
  sections: [
    {
      heading: 'privacyPageSection1Heading',
      paragraphs: ['privacyPageSection1Para1', 'privacyPageSection1Para2'],
    },
    {
      heading: 'privacyPageSection2Heading',
      paragraphs: ['privacyPageSection2Para1', 'privacyPageSection2Para2'],
    },
    {
      heading: 'privacyPageSection3Heading',
      paragraphs: ['privacyPageSection3Para1'],
    },
    {
      heading: 'privacyPageSection4Heading',
      paragraphs: ['privacyPageSection4Para1'],
    },
    {
      heading: 'privacyPageSection5Heading',
      paragraphs: ['privacyPageSection5Para1'],
    },
    {
      heading: 'privacyPageSection6Heading',
      paragraphs: ['privacyPageSection6Para1'],
    },
    {
      heading: 'privacyPageSection7Heading',
      paragraphs: ['privacyPageSection7Para1'],
    },
  ],
} as const

export default function Privacy(props: Props) {
  const { t } = useLocale()

  return (
    <MarketingShell branding={props} title={t('privacyPageTitle')} active="privacy">
      <section className="mkt-hero mkt-hero--compact">
        <div className="mkt-hero__inner">
          <p className="mkt-eyebrow">{t('privacyPageEyebrow')}</p>
          <h1 className="mkt-hero__title">{t('privacyPageHero')}</h1>
          <p className="mkt-hero__meta">{t('privacyPageUpdated')}</p>
        </div>
      </section>

      <section className="mkt-section">
        <div className="mkt-legal">
          {privacyStructure.sections.map((section) => (
            <article key={section.heading}>
              <h2>{t(section.heading)}</h2>
              {section.paragraphs.map((key) => <p key={key}>{t(key)}</p>)}
            </article>
          ))}
        </div>
      </section>
    </MarketingShell>
  )
}
