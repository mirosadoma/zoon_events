import { useLocale } from '@/hooks/useLocale'

export type ConsentDisclosure = {
  en: string
  ar: string
}

export type ConsentDisclosures = {
  what: ConsentDisclosure
  why: ConsentDisclosure
  retention: ConsentDisclosure
  who: ConsentDisclosure
  processing_mode: ConsentDisclosure
  deletion: ConsentDisclosure
}

type Props = {
  locale: 'en' | 'ar'
  disclosures: ConsentDisclosures
  residencyMode: string
  noticeVersion: string
}

export default function ConsentNotice({ locale, disclosures, residencyMode, noticeVersion }: Props) {
  const { t } = useLocale()
  const rtl = locale === 'ar'

  const items = [
    { key: 'what', label: t('identityConsentWhat') },
    { key: 'why', label: t('identityConsentWhy') },
    { key: 'retention', label: t('identityConsentRetention') },
    { key: 'who', label: t('identityConsentWhoCanAccess') },
    { key: 'processing_mode', label: t('identityConsentProcessingMode') },
    { key: 'deletion', label: t('identityConsentDeletion') },
  ] as const

  return (
    <section
      aria-labelledby="identity-consent-title"
      className="identity-consent-notice"
      lang={locale}
      dir={rtl ? 'rtl' : 'ltr'}
    >
      <h2 id="identity-consent-title">{t('identityConsentTitle')}</h2>
      <p>{t('identityConsentDescription')}</p>
      <dl>
        {items.map((item) => (
          <div key={item.key}>
            <dt>{item.label}</dt>
            <dd>{disclosures[item.key][locale]}</dd>
          </div>
        ))}
        <div>
          <dt>{t('identityConsentProcessingMode')}</dt>
          <dd>{residencyMode}</dd>
        </div>
        <div>
          <dt>{t('identityConsentNoticeVersion')}</dt>
          <dd>{noticeVersion}</dd>
        </div>
      </dl>
    </section>
  )
}
