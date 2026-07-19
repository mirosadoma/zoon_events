import { CalendarDays, ChevronRight } from 'lucide-react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import VisitorShell, { VisitorPageHeader, VisitorPanel } from '@/layouts/VisitorShell'
import { useLocale } from '@/hooks/useLocale'

type EventRow = {
  attendee_id: string
  event_id: string
  event_name: string
  event_slug?: string | null
  starts_at?: string | null
  ends_at?: string | null
  registration_status: string
  order_reference?: string | null
  registered_at?: string | null
}

type Props = {
  events: EventRow[]
}

export default function VisitorEvents({ events }: Props) {
  const { locale, t } = useLocale()

  return (
    <VisitorShell title={t('visitorMyEvents')}>
      <VisitorPageHeader
        title={t('visitorMyEvents')}
        lead={t('visitorMyEventsLead')}
      />

      {events.length === 0 ? (
        <VisitorPanel className="visitor-empty">
          <span className="visitor-empty__icon" aria-hidden>
            <CalendarDays className="h-6 w-6" />
          </span>
          <p className="visitor-empty__title">{t('visitorNoEvents')}</p>
          <p className="visitor-empty__text">{t('visitorNoEventsHint')}</p>
        </VisitorPanel>
      ) : (
        <ul className="visitor-event-list">
          {events.map((event) => (
            <li key={event.attendee_id}>
              <LocalizedLink
                href={`/visitor/events/${event.event_id}`}
                className="visitor-event-card"
              >
                <div className="visitor-event-card__body">
                  <div className="visitor-event-card__copy">
                    <h2>{event.event_name}</h2>
                    {event.order_reference ? (
                      <p className="visitor-event-card__ref">{event.order_reference}</p>
                    ) : null}
                    {event.starts_at ? (
                      <p className="visitor-event-card__meta">
                        {new Date(event.starts_at).toLocaleString(locale === 'ar' ? 'ar-EG' : 'en-US')}
                      </p>
                    ) : null}
                  </div>
                  <div className="visitor-event-card__aside">
                    <span className="visitor-status">{event.registration_status}</span>
                    <ChevronRight className="visitor-event-card__chevron h-5 w-5 rtl:rotate-180" aria-hidden />
                  </div>
                </div>
              </LocalizedLink>
            </li>
          ))}
        </ul>
      )}
    </VisitorShell>
  )
}
