import RegistrationPageControls from '@/components/registration/RegistrationPageControls'
import RegistrationSoldOutIllustration from '@/components/registration/RegistrationSoldOutIllustration'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { Head } from '@inertiajs/react'
import { useLocale } from '@/hooks/useLocale'

type Props = {
  locale: 'en' | 'ar'
  event: {
    slug: string
    name: LocalizedText
  }
}

export default function SoldOut({ locale, event }: Props) {
  const { t, direction } = useLocale()

  return (
    <>
      <Head title={t('publicRegistrationSoldOutTitle')} />
      <RegistrationPageControls locale={locale} />
      <main className="registration-invite min-h-screen px-4 py-10" lang={locale} dir={direction}>
        <div className="registration-invite-card registration-invite-inactive mx-auto max-w-lg text-center">
          <div className="registration-invite-inactive-art mx-auto">
            <RegistrationSoldOutIllustration className="h-full w-full" />
          </div>

          <p className="registration-invite-kicker">
            <LocalizedEventContent value={event.name} locale={locale} />
          </p>

          <h1 className="text-3xl font-semibold text-[var(--ink)]">
            {t('publicRegistrationSoldOutTitle')}
          </h1>
          <p className="mt-4 text-[var(--muted)]">
            {t('publicRegistrationSoldOutLead')}
          </p>
          <p className="registration-invite-footnote mt-4">
            {t('publicRegistrationSoldOutHint')}
          </p>

          <LocalizedLink href="/" className="button-primary mt-8 inline-flex">
            {t('publicRegistrationInviteInactiveHome')}
          </LocalizedLink>
        </div>
      </main>
    </>
  )
}
