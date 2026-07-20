import InviteInactiveIllustration from '@/components/registration/InviteInactiveIllustration'
import RegistrationPageControls from '@/components/registration/RegistrationPageControls'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { Head } from '@inertiajs/react'
import { useLocale } from '@/hooks/useLocale'

type InviteReason = 'inactive' | 'invalid' | 'required'

type Props = {
  locale: 'en' | 'ar'
  reason: InviteReason
  event: {
    slug: string
    name: LocalizedText
  }
}

function copyForReason(reason: InviteReason, t: (key: string) => string) {
  switch (reason) {
    case 'required':
      return {
        title: t('publicRegistrationInviteRequiredTitle'),
        lead: t('publicRegistrationInviteRequiredLead'),
      }
    case 'invalid':
      return {
        title: t('publicRegistrationInviteInvalidTitle'),
        lead: t('publicRegistrationInviteInvalidLead'),
      }
    default:
      return {
        title: t('publicRegistrationInviteInactiveTitle'),
        lead: t('publicRegistrationInviteInactiveLead'),
      }
  }
}

export default function InviteInactive({ locale, reason, event }: Props) {
  const { t, direction } = useLocale()
  const copy = copyForReason(reason, t)

  return (
    <>
      <Head title={copy.title} />
      <RegistrationPageControls locale={locale} />
      <main className="registration-invite min-h-screen px-4 py-10" lang={locale} dir={direction}>
        <div className="registration-invite-card registration-invite-inactive mx-auto max-w-lg text-center">
          <div className="registration-invite-inactive-art mx-auto">
            <InviteInactiveIllustration className="h-full w-full" />
          </div>

          <p className="registration-invite-kicker">
            <LocalizedEventContent value={event.name} locale={locale} />
          </p>

          <h1 className="text-3xl font-semibold text-[var(--ink)]">{copy.title}</h1>
          <p className="mt-4 text-[var(--muted)]">{copy.lead}</p>
          <p className="registration-invite-footnote mt-4">
            {t('publicRegistrationInviteInactiveHint')}
          </p>

          <LocalizedLink href="/" className="button-primary mt-8 inline-flex">
            {t('publicRegistrationInviteInactiveHome')}
          </LocalizedLink>
        </div>
      </main>
    </>
  )
}
