import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import RegistrationEventHero, { type RegistrationHeroEvent } from '@/components/registration/RegistrationEventHero'
import RegistrationPageControls from '@/components/registration/RegistrationPageControls'
import { useLocale } from '@/hooks/useLocale'
import { formatAsWallClock } from '@/lib/dateTimeLocal'
import { formatTime } from '@/lib/formatters'

type AgendaItem = {
  id: string
  title: LocalizedText
  description?: LocalizedText | null
  start_at: string
  end_at?: string | null
  agenda_date?: string | null
  event_venue_id?: string | null
  zone_id?: string | null
  speaker?: string | null
  venue_name?: LocalizedText | null
  zone_name?: LocalizedText | null
}

type ZoneOption = {
  id: string
  venue_id: string
  name: LocalizedText
  type: string
  capacity: number | null
}

type Props = {
  locale: 'en' | 'ar'
  event: RegistrationHeroEvent
  items: AgendaItem[]
  registerUrl: string
  isPreview?: boolean
  availableDates?: string[]
  zones?: ZoneOption[]
  selectedDate?: string | null
  selectedVenueId?: string | null
  selectedZoneId?: string | null
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
  return formatAsWallClock(`${date}T12:00`, locale, {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }) || date
}

export default function PublicEventAgenda({
  locale,
  event,
  items,
  registerUrl,
  isPreview = false,
  availableDates = [],
  zones = [],
  selectedDate = null,
  selectedVenueId = null,
  selectedZoneId = null,
}: Props) {
  const { t, direction } = useLocale()
  const dates = availableDates.length > 0
    ? availableDates
    : Array.from(new Set(items.map((item) => item.agenda_date).filter(Boolean) as string[])).sort()

  function visitAgenda(next: { date?: string | null; venueId?: string | null; zoneId?: string | null }) {
    if (isPreview) {
      return
    }

    const params = new URLSearchParams(window.location.search)
    const nextDate = next.date === undefined ? selectedDate : next.date
    const nextVenueId = next.venueId === undefined ? selectedVenueId : next.venueId
    const nextZoneId = next.zoneId === undefined ? selectedZoneId : next.zoneId

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

    if (nextZoneId) {
      params.set('zone_id', nextZoneId)
    } else {
      params.delete('zone_id')
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

    visitAgenda({ venueId, zoneId: null, date: null })
  }

  function selectZone(zoneId: string | null) {
    if (zoneId === selectedZoneId) {
      return
    }

    visitAgenda({ zoneId, date: null })
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

            {zones.length > 0 && !isPreview ? (
              <div className="mb-4 flex flex-wrap gap-2" role="tablist" aria-label={t('publicRegistrationAgendaZones')}>
                <button
                  type="button"
                  role="tab"
                  aria-selected={selectedZoneId === null}
                  onClick={() => selectZone(null)}
                  className={`rounded-full px-3 py-1.5 text-sm font-medium transition ${
                    selectedZoneId === null
                      ? 'bg-[var(--brand)] text-white'
                      : 'bg-[var(--surface)] text-[var(--muted)] hover:text-[var(--ink)]'
                  }`}
                >
                  {t('publicRegistrationAgendaAllZones')}
                </button>
                {zones.map((zone) => {
                  const active = zone.id === selectedZoneId
                  return (
                    <button
                      key={zone.id}
                      type="button"
                      role="tab"
                      aria-selected={active}
                      onClick={() => selectZone(zone.id)}
                      className={`rounded-full px-3 py-1.5 text-sm font-medium transition ${
                        active
                          ? 'bg-[var(--brand)] text-white'
                          : 'bg-[var(--surface)] text-[var(--muted)] hover:text-[var(--ink)]'
                      }`}
                    >
                      {zone.name ? <LocalizedEventContent value={zone.name} locale={locale} /> : null}
                    </button>
                  )
                })}
              </div>
            ) : null}

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
                      {item.speaker ? (
                        <span className="mt-1 block text-xs text-[var(--muted)]">
                          {item.speaker}
                        </span>
                      ) : null}
                      {item.description?.[locale] || item.description?.en || item.description?.ar ? (
                        <p className="mt-1 block text-xs text-[var(--muted)]">
                          <LocalizedEventContent value={item.description} locale={locale} />
                        </p>
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
