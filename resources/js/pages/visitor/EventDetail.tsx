import { ArrowLeft, MapPin } from 'lucide-react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import VisitorShell, { VisitorPanel } from '@/layouts/VisitorShell'
import { useLocale } from '@/hooks/useLocale'
import { formatDateTime } from '@/lib/formatters'

type VenueInfo = {
  id: string
  name: LocalizedText
  city?: LocalizedText
  country?: LocalizedText
  location_address?: string
  start_at?: string | null
  end_at?: string | null
}

type Props = {
  locale: 'en' | 'ar'
  event: {
    id: string
    slug: string
    name: LocalizedText
    description?: LocalizedText | null
    timezone?: string | null
    status?: string
    location_name?: LocalizedText | null
    location_address?: LocalizedText | null
    main_image_url?: string | null
    venues: VenueInfo[]
  }
  registration: {
    attendee_id: string
    status: string
    registered_at?: string | null
    order_reference?: string | null
    attendee_name: string
    category?: {
      id: string
      name: LocalizedText
      color?: string | null
    } | null
    selected_venue?: VenueInfo | null
  }
}

function localizedText(value: LocalizedText | null | undefined, locale: 'en' | 'ar'): string {
  if (!value) return ''
  const text = locale === 'ar' ? (value.ar || value.en) : (value.en || value.ar)
  return (text ?? '').trim()
}

function venuePlaceLine(venue: VenueInfo, locale: 'en' | 'ar'): string {
  return [localizedText(venue.city, locale), localizedText(venue.country, locale)]
    .filter(Boolean)
    .join(', ')
}

export default function VisitorEventDetail({ locale, event, registration }: Props) {
  const { t } = useLocale()
  const description = localizedText(event.description ?? undefined, locale)
  const locationName = localizedText(event.location_name ?? undefined, locale)
  const locationAddress = localizedText(event.location_address ?? undefined, locale)

  return (
    <VisitorShell title={t('visitorEventDetail')}>
      <LocalizedLink href="/visitor" className="visitor-back">
        <ArrowLeft className="h-4 w-4 rtl:rotate-180" aria-hidden />
        <span>{t('visitorBackToEvents')}</span>
      </LocalizedLink>

      <div className="visitor-detail-hero">
        {event.main_image_url ? (
          <div className="visitor-detail-hero__media">
            <img src={event.main_image_url} alt="" />
          </div>
        ) : null}
        <div className="visitor-detail-hero__top">
          <h1 className="visitor-detail-hero__title">
            <LocalizedEventContent value={event.name} locale={locale} />
          </h1>
          <span className="visitor-status">{registration.status}</span>
        </div>
        {description ? (
          <p className="visitor-detail-hero__lead">{description}</p>
        ) : null}
      </div>

      <div className="visitor-detail-stack">
        <VisitorPanel>
          <h2 className="visitor-section-title">{t('visitorRegistrationSection')}</h2>
          <dl className="visitor-detail-grid">
            <div>
              <dt>{t('visitorAttendeeName')}</dt>
              <dd>{registration.attendee_name}</dd>
            </div>
            <div>
              <dt>{t('status')}</dt>
              <dd>{registration.status}</dd>
            </div>
            {registration.category ? (
              <div>
                <dt>{t('visitorCategory')}</dt>
                <dd>
                  <span
                    className="visitor-category-chip"
                    style={registration.category.color
                      ? { background: `color-mix(in srgb, ${registration.category.color} 16%, transparent)`, color: registration.category.color }
                      : undefined}
                  >
                    <LocalizedEventContent value={registration.category.name} locale={locale} />
                  </span>
                </dd>
              </div>
            ) : null}
            {registration.order_reference ? (
              <div>
                <dt>{t('publicRegistrationOrderReference')}</dt>
                <dd className="font-mono">{registration.order_reference}</dd>
              </div>
            ) : null}
            {registration.registered_at ? (
              <div>
                <dt>{t('visitorRegisteredAt')}</dt>
                <dd>
                  {formatDateTime(registration.registered_at, locale, event.timezone || undefined)}
                </dd>
              </div>
            ) : null}
            {event.timezone ? (
              <div>
                <dt>{t('eventHeroTimezone')}</dt>
                <dd>{event.timezone}</dd>
              </div>
            ) : null}
            {locationName || locationAddress ? (
              <div className="sm:col-span-2">
                <dt>{t('visitorEventLocation')}</dt>
                <dd>
                  {locationName || null}
                  {locationName && locationAddress ? ' · ' : null}
                  {locationAddress || null}
                </dd>
              </div>
            ) : null}
          </dl>
        </VisitorPanel>

        {registration.selected_venue ? (
          <VisitorPanel className="visitor-venue-panel visitor-venue-panel--selected">
            <h2 className="visitor-section-title">{t('visitorMyVenue')}</h2>
            <div className="visitor-venue-card is-selected">
              <span className="visitor-venue-card__icon" aria-hidden>
                <MapPin className="h-4 w-4" />
              </span>
              <div>
                <p className="visitor-venue-card__name">
                  <LocalizedEventContent value={registration.selected_venue.name} locale={locale} />
                </p>
                {venuePlaceLine(registration.selected_venue, locale) ? (
                  <p className="visitor-venue-card__meta">{venuePlaceLine(registration.selected_venue, locale)}</p>
                ) : null}
                {registration.selected_venue.location_address ? (
                  <p className="visitor-venue-card__meta">{registration.selected_venue.location_address}</p>
                ) : null}
                {registration.selected_venue.start_at ? (
                  <p className="visitor-venue-card__meta">
                    {formatDateTime(registration.selected_venue.start_at, locale, event.timezone || undefined)}
                    {registration.selected_venue.end_at
                      ? ` – ${formatDateTime(registration.selected_venue.end_at, locale, event.timezone || undefined)}`
                      : ''}
                  </p>
                ) : null}
              </div>
            </div>
          </VisitorPanel>
        ) : null}

        <VisitorPanel>
          <h2 className="visitor-section-title">{t('visitorEventVenues')}</h2>
          {event.venues.length === 0 ? (
            <p className="visitor-empty__text">{t('visitorNoVenues')}</p>
          ) : (
            <ul className="visitor-venue-list">
              {event.venues.map((venue) => {
                const isMine = registration.selected_venue?.id === venue.id
                return (
                  <li key={venue.id} className={`visitor-venue-card${isMine ? ' is-selected' : ''}`}>
                    <span className="visitor-venue-card__icon" aria-hidden>
                      <MapPin className="h-4 w-4" />
                    </span>
                    <div>
                      <div className="visitor-venue-card__heading">
                        <p className="visitor-venue-card__name">
                          <LocalizedEventContent value={venue.name} locale={locale} />
                        </p>
                        {isMine ? (
                          <span className="visitor-venue-card__badge">{t('visitorMyVenueBadge')}</span>
                        ) : null}
                      </div>
                      {venuePlaceLine(venue, locale) ? (
                        <p className="visitor-venue-card__meta">{venuePlaceLine(venue, locale)}</p>
                      ) : null}
                      {venue.location_address ? (
                        <p className="visitor-venue-card__meta">{venue.location_address}</p>
                      ) : null}
                      {venue.start_at ? (
                        <p className="visitor-venue-card__meta">
                          {formatDateTime(venue.start_at, locale, event.timezone || undefined)}
                          {venue.end_at
                            ? ` – ${formatDateTime(venue.end_at, locale, event.timezone || undefined)}`
                            : ''}
                        </p>
                      ) : null}
                    </div>
                  </li>
                )
              })}
            </ul>
          )}
        </VisitorPanel>
      </div>
    </VisitorShell>
  )
}
