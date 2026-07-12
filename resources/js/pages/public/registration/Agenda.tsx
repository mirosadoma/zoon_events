import LocalizedLink from '@/components/routing/LocalizedLink'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import RegistrationEventHero, { type RegistrationHeroEvent } from '@/components/registration/RegistrationEventHero'
import RegistrationPageControls from '@/components/registration/RegistrationPageControls'
import { useLocale } from '@/hooks/useLocale'

type AgendaItem = {
  id: string
  title: LocalizedText
  start_at: string
  end_at?: string | null
}

type Props = {
  locale: 'en' | 'ar'
  event: RegistrationHeroEvent
  items: AgendaItem[]
  registerUrl: string
  isPreview?: boolean
}

function formatAgendaClock(iso: string, locale: 'en' | 'ar'): string {
  return new Date(iso)
    .toLocaleTimeString(locale === 'ar' ? 'ar-EG' : 'en-US', {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    })
    .replace(/\s/g, '')
    .toUpperCase()
}

function formatAgendaRange(item: AgendaItem, locale: 'en' | 'ar'): string {
  const start = formatAgendaClock(item.start_at, locale)

  if (!item.end_at) {
    return `${start} — …`
  }

  return `${start} – ${formatAgendaClock(item.end_at, locale)}`
}

export default function PublicEventAgenda({ locale, event, items, registerUrl, isPreview = false }: Props) {
  const { t, direction } = useLocale()

  return (
    <>
      <RegistrationPageControls locale={locale} />
      <main className={`registration-invite registration-invite-agenda${isPreview ? ' registration-invite-preview' : ''}`} lang={locale} dir={direction}>
        <div className="registration-agenda-shell">
          {isPreview ? (
            <div className="registration-preview-banner registration-agenda-preview-banner" role="status">
              {t('publicRegistrationPreviewBanner')}
            </div>
          ) : null}
          <RegistrationEventHero locale={locale} event={event} />

          <section className="registration-agenda-panel" aria-labelledby="event-agenda-title">
            <h2 id="event-agenda-title" className="registration-agenda-title">
              {t('publicRegistrationAgendaLabel')}
            </h2>

            {items.length > 0 ? (
              <ol className="registration-agenda-timeline">
                {items.map((item) => (
                  <li key={item.id} className="registration-agenda-item">
                    <span className="registration-agenda-marker" aria-hidden />
                    <div className="registration-agenda-item-body">
                      <span className="registration-agenda-time">{formatAgendaRange(item, locale)}</span>
                      <span className="registration-agenda-label">
                        <LocalizedEventContent value={item.title} locale={locale} />
                      </span>
                    </div>
                  </li>
                ))}
              </ol>
            ) : (
              <p className="registration-agenda-empty">
                {t('publicRegistrationAgendaEmpty')}
              </p>
            )}

            <LocalizedLink href={registerUrl} className="registration-agenda-register">
              {isPreview ? t('publicRegistrationPreviewRegistration') : t('publicRegistrationRegisterNow')}
            </LocalizedLink>
          </section>
        </div>
      </main>
    </>
  )
}
