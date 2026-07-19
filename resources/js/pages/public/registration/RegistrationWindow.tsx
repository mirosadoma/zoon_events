import RegistrationPageControls from '@/components/registration/RegistrationPageControls'
import RegistrationWindowIllustration from '@/components/registration/RegistrationWindowIllustration'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { Head } from '@inertiajs/react'
import { useLocale } from '@/hooks/useLocale'
import { formatDateTime } from '@/lib/formatters'

type WindowStatus = 'not_open' | 'closed'

type Props = {
  locale: 'en' | 'ar'
  status: WindowStatus
  event: {
    slug: string
    name: LocalizedText
    registration_opens_at: string | null
    registration_closes_at: string | null
    timezone?: string | null
  }
}

function formatWindowMoment(value: string | null, locale: 'en' | 'ar', timeZone?: string | null): string | null {
  if (!value) return null
  try {
    return formatDateTime(value, locale, timeZone || undefined)
  } catch {
    return null
  }
}

export default function RegistrationWindow({ locale, status, event }: Props) {
  const { t, direction } = useLocale()
  const isClosed = status === 'closed'
  const title = isClosed
    ? t('publicRegistrationWindowClosedTitle')
    : t('publicRegistrationWindowNotOpenTitle')
  const lead = isClosed
    ? t('publicRegistrationWindowClosedLead')
    : t('publicRegistrationWindowNotOpenLead')
  const opensLabel = formatWindowMoment(event.registration_opens_at, locale, event.timezone)
  const closesLabel = formatWindowMoment(event.registration_closes_at, locale, event.timezone)

  return (
    <>
      <Head title={title} />
      <RegistrationPageControls locale={locale} />
      <main className="registration-invite min-h-screen px-4 py-10" lang={locale} dir={direction}>
        <div className="registration-invite-card registration-invite-inactive mx-auto max-w-lg text-center">
          <div className="registration-invite-inactive-art mx-auto">
            <RegistrationWindowIllustration variant={status} className="h-full w-full" />
          </div>

          <p className="registration-invite-kicker">
            <LocalizedEventContent value={event.name} locale={locale} />
          </p>

          <h1 className="text-3xl font-semibold text-[var(--ink)]">{title}</h1>
          <p className="mt-4 text-[var(--muted)]">{lead}</p>

          {!isClosed && opensLabel && (
            <p className="registration-invite-footnote mt-4">
              {t('publicRegistrationWindowOpensOn').replace(':date', opensLabel)}
            </p>
          )}
          {isClosed && closesLabel && (
            <p className="registration-invite-footnote mt-4">
              {t('publicRegistrationWindowClosedOn').replace(':date', closesLabel)}
            </p>
          )}

          <LocalizedLink href="/" className="button-primary mt-8 inline-flex">
            {t('publicRegistrationInviteInactiveHome')}
          </LocalizedLink>
        </div>
      </main>
    </>
  )
}
