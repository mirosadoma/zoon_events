import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import RegistrationEventHero, { type RegistrationHeroEvent } from '@/components/registration/RegistrationEventHero'
import RegistrationPageControls from '@/components/registration/RegistrationPageControls'
import { useLocale } from '@/hooks/useLocale'
import { formatTime } from '@/lib/formatters'

type AgendaItem = {
  id: string
  title: LocalizedText
  start_at: string
  end_at?: string | null
  agenda_date?: string | null
  event_venue_id?: string | null
  venue_name?: LocalizedText | null
}

type Props = {
  locale: 'en' | 'ar'
  event: RegistrationHeroEvent
  items: AgendaItem[]
  registerUrl: string
  isPreview?: boolean
  availableDates?: string[]
  selectedDate?: string | null
  selectedVenueId?: string | null
}

function formatAgendaClock(iso: string, locale: 'en' | 'ar', timeZone?: string | null): string {
  return formatTime(iso, locale, timeZone || undefined)
    .replace(/\s/g, '')
    .toUpperCase()
}

function formatAgendaRange(item: AgendaItem, locale: 'en' | 'ar', timeZone?: string | null): string {
  const start = formatAgendaClock(item.start_at, locale, timeZone)

  if (!item.end_at) {
    return `${start} — …`
  }

  return `${start} – ${formatAgendaClock(item.end_at, locale, timeZone)}`
}

function formatDayLabel(date: string, locale: 'en' | 'ar'): string {
  const parsed = new Date(`${date}T12:00:00`)
  if (Number.isNaN(parsed.getTime())) {
    return date
  }

  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar' : 'en-GB', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(parsed)
}

export default function PublicEventAgenda({
  locale,
  event,
  items,
  registerUrl,
  isPreview = false,
  availableDates = [],
  selectedDate = null,
  selectedVenueId = null,
}: Props) {
  const { t, direction } = useLocale()
  const dates = availableDates.length > 0
    ? availableDates
    : Array.from(new Set(items.map((item) => item.agenda_date).filter(Boolean) as string[])).sort()

  function visitAgenda(next: { date?: string | null; venueId?: string | null }) {
    if (isPreview) {
      return
    }

    const params = new URLSearchParams(window.location.search)
    const nextDate = next.date === undefined ? selectedDate : next.date
    const nextVenueId = next.venueId === undefined ? selectedVenueId : next.venueId

    if (nextDate) {
      params.set('date', nextDate)
    } else {
      params.delete('date')
    }

    if (nextVenueId) {
      params.set('venue_id', nextVenueId)
    } else {
      params.delete('venue_id')
    }

    const query = params.toString()
    router.get(`${window.location.pathname}${query ? `?${query}` : ''}`, {}, {
      preserveScroll: true,
      preserveState: false,
      replace: true,
    })
  }

  function selectDate(date: string) {
    if (date === selectedDate) {
      return
    }

    visitAgenda({ date })
  }

  function selectVenue(venueId: string) {
    if (venueId === selectedVenueId) {
      return
    }

    visitAgenda({ venueId, date: null })
  }

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
          <RegistrationEventHero
            locale={locale}
            event={event}
            showEventHeader
            selectedVenueId={selectedVenueId}
            onSelectVenue={isPreview ? undefined : selectVenue}
          />

          <section className="registration-agenda-panel" aria-labelledby="event-agenda-title">
            <h2 id="event-agenda-title" className="registration-agenda-title">
              {t('publicRegistrationAgendaLabel')}
            </h2>

            {dates.length > 1 && !isPreview ? (
              <div className="mb-5 flex flex-wrap gap-2" role="tablist" aria-label={t('publicRegistrationAgendaDays')}>
                {dates.map((date) => {
                  const active = date === selectedDate
                  return (
                    <button
                      key={date}
                      type="button"
                      role="tab"
                      aria-selected={active}
                      onClick={() => selectDate(date)}
                      className={`rounded-full px-3 py-1.5 text-sm font-medium transition ${
                        active
                          ? 'bg-[var(--brand)] text-white'
                          : 'bg-[var(--surface)] text-[var(--muted)] hover:text-[var(--ink)]'
                      }`}
                    >
                      {formatDayLabel(date, locale)}
                    </button>
                  )
                })}
              </div>
            ) : null}

            {selectedDate && dates.length === 1 ? (
              <p className="mb-4 text-sm text-[var(--muted)]">
                {formatDayLabel(selectedDate, locale)}
              </p>
            ) : null}

            {items.length > 0 ? (
              <ol className="registration-agenda-timeline">
                {items.map((item) => (
                  <li key={item.id} className="registration-agenda-item">
                    <span className="registration-agenda-marker" aria-hidden />
                    <div className="registration-agenda-item-body">
                      <span className="registration-agenda-time">{formatAgendaRange(item, locale, event.timezone)}</span>
                      <span className="registration-agenda-label">
                        <LocalizedEventContent value={item.title} locale={locale} />
                      </span>
                      {item.venue_name ? (
                        <span className="mt-1 block text-xs text-[var(--muted)]">
                          <LocalizedEventContent value={item.venue_name} locale={locale} />
                        </span>
                      ) : null}
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
