import RegistrationPageControls from '@/components/registration/RegistrationPageControls'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import { useLocale } from '@/hooks/useLocale'

type Props = {
  locale: 'en' | 'ar'
  event: {
    slug: string
    name: LocalizedText
  }
  registerUrl: string
}

export default function PaymentFailed({ locale, event, registerUrl }: Props) {
  const { t, direction } = useLocale()

  return (
    <>
      <RegistrationPageControls locale={locale} />
      <main className="registration-invite min-h-screen px-4 py-10" lang={locale} dir={direction}>
        <div className="registration-invite-card mx-auto max-w-lg text-center">
          <p className="registration-invite-kicker">
            <LocalizedEventContent value={event.name} locale={locale} />
          </p>
          <h1 className="text-3xl font-semibold text-[var(--ink)]">
            {t('publicRegistrationPaymentFailedTitle')}
          </h1>
          <p className="mt-4 text-[var(--muted)]">
            {t('publicRegistrationPaymentFailedLead')}
          </p>
          <a href={registerUrl} className="button-primary mt-8 inline-flex">
            {t('publicRegistrationPaymentFailedBack')}
          </a>
        </div>
      </main>
    </>
  )
}
