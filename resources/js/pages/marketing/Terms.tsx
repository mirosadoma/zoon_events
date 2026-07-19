import MarketingShell, { type MarketingBranding } from '@/layouts/MarketingShell'
import { useLocale } from '@/hooks/useLocale'

type Props = MarketingBranding

const termsStructure = {
  sections: [
    {
      heading: 'termsPageSection1Heading',
      paragraphs: ['termsPageSection1Para1'],
    },
    {
      heading: 'termsPageSection2Heading',
      paragraphs: ['termsPageSection2Para1'],
    },
    {
      heading: 'termsPageSection3Heading',
      paragraphs: ['termsPageSection3Para1'],
    },
    {
      heading: 'termsPageSection4Heading',
      paragraphs: ['termsPageSection4Para1'],
    },
    {
      heading: 'termsPageSection5Heading',
      paragraphs: ['termsPageSection5Para1'],
    },
    {
      heading: 'termsPageSection6Heading',
      paragraphs: ['termsPageSection6Para1'],
    },
    {
      heading: 'termsPageSection7Heading',
      paragraphs: ['termsPageSection7Para1'],
    },
    {
      heading: 'termsPageSection8Heading',
      paragraphs: ['termsPageSection8Para1'],
    },
    {
      heading: 'termsPageSection9Heading',
      paragraphs: ['termsPageSection9Para1'],
    },
  ],
} as const

export default function Terms(props: Props) {
  const { t } = useLocale()

  return (
    <MarketingShell branding={props} title={t('termsPageTitle')} active="terms">
      <section className="mkt-hero mkt-hero--compact">
        <div className="mkt-hero__inner">
          <p className="mkt-eyebrow">{t('termsPageEyebrow')}</p>
          <h1 className="mkt-hero__title">{t('termsPageHero')}</h1>
          <p className="mkt-hero__meta">{t('termsPageUpdated')}</p>
        </div>
      </section>

      <section className="mkt-section">
        <div className="mkt-legal">
          {termsStructure.sections.map((section) => (
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
