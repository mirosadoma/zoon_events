import type { CSSProperties, ReactNode } from 'react'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import { formatVenuePillLabel } from '@/lib/venueLabels'
import { formatDateTime } from '@/lib/formatters'
import en from '@/locales/en'
import ar from '@/locales/ar'

export type RegistrationHeroVenue = {
  id: string
  name: LocalizedText
  city: LocalizedText
  country: LocalizedText
  location_address?: string
  start_at?: string | null
  end_at?: string | null
}

export type RegistrationHeroEvent = {
  name: LocalizedText
  description: LocalizedText
  timezone?: string | null
  start_at?: string | null
  end_at?: string | null
  branding: { brand_reference: string | null; domain_reference?: string | null }
  main_image?: string | null
  images?: string[]
  venues?: RegistrationHeroVenue[]
}

type Props = {
  locale: 'en' | 'ar'
  event: RegistrationHeroEvent
  isPreview?: boolean
  /** When true, shows the classic event header (used by agenda). Registration form drives content from builder fields instead. */
  showEventHeader?: boolean
  cardStyle?: CSSProperties
  hasCustomBackground?: boolean
  children?: ReactNode
  /** When set, venue pills are buttons that filter the agenda. */
  selectedVenueId?: string | null
  onSelectVenue?: (venueId: string) => void
}

function EventMediaPreview({
  locale,
  mainImage,
  images,
}: {
  locale: 'en' | 'ar'
  mainImage: string | null
  images: string[]
}) {
  if (!mainImage && images.length === 0) {
    return null
  }

  return (
    <div className="registration-event-media">
      {mainImage ? (
        <img
          src={mainImage}
          alt=""
          className="registration-event-main-image"
        />
      ) : null}
      {images.length > 0 ? (
        <div
          className="registration-event-gallery"
          aria-label={(locale === 'ar' ? ar : en).registrationEventPhotos}
        >
          {images.map((url) => (
            <img
              key={url}
              src={url}
              alt=""
              className="registration-event-gallery-image"
              loading="lazy"
            />
          ))}
        </div>
      ) : null}
    </div>
  )
}

function EventVenueSchedule({
  locale,
  venues,
  startAt,
  endAt,
  timeZone,
  selectedVenueId = null,
  onSelectVenue,
}: {
  locale: 'en' | 'ar'
  venues: RegistrationHeroVenue[]
  startAt?: string | null
  endAt?: string | null
  timeZone?: string | null
  selectedVenueId?: string | null
  onSelectVenue?: (venueId: string) => void
}) {
  const rtl = locale === 'ar'
  const zone = timeZone || undefined
  const interactive = typeof onSelectVenue === 'function'

  if (venues.length > 0) {
    return (
      <div
        className="registration-event-venues"
        role={interactive ? 'tablist' : undefined}
        aria-label={rtl ? 'أماكن الفعالية' : 'Event venues'}
      >
        {venues.map((venue) => {
          const label = formatVenuePillLabel(venue, locale, zone)
          const active = selectedVenueId === venue.id

          if (!interactive) {
            return (
              <p
                key={venue.id}
                className={`registration-event-venue-pill${active ? ' registration-event-venue-pill--active' : ''}`}
              >
                {label}
              </p>
            )
          }

          return (
            <button
              key={venue.id}
              type="button"
              role="tab"
              aria-selected={active}
              className={`registration-event-venue-pill registration-event-venue-pill--button${active ? ' registration-event-venue-pill--active' : ''}`}
              onClick={() => onSelectVenue(venue.id)}
            >
              {label}
            </button>
          )
        })}
      </div>
    )
  }

  if (!startAt) {
    return null
  }

  return (
    <p className="registration-invite-schedule">
      {formatDateTime(startAt, locale, zone)}
      {endAt ? ` — ${formatDateTime(endAt, locale, zone)}` : ''}
    </p>
  )
}

export default function RegistrationEventHero({
  locale,
  event,
  isPreview = false,
  showEventHeader = false,
  cardStyle,
  hasCustomBackground = false,
  children,
  selectedVenueId = null,
  onSelectVenue,
}: Props) {
  const rtl = locale === 'ar'

  return (
    <div className="registration-invite-hero">
      <div
        className={`registration-invite-card${hasCustomBackground ? ' registration-invite-card--custom-bg' : ''}`}
        style={cardStyle}
      >
        {isPreview ? (
          <div className="registration-preview-banner" role="status">
            {rtl
              ? 'معاينة للمنظم — عرض فقط. التسجيل الحقيقي يتم عبر رابط الزوار.'
              : 'Organizer preview — display only. Real registration uses the visitor link.'}
          </div>
        ) : null}
        {showEventHeader ? (
          <header className="registration-invite-header">
            <EventMediaPreview
              locale={locale}
              mainImage={event.main_image ?? null}
              images={event.images ?? []}
            />
            {event.branding.brand_reference ? (
              <p className="registration-invite-brand">{event.branding.brand_reference}</p>
            ) : null}
            <p className="registration-invite-kicker">
              {rtl ? 'دعوة للتسجيل' : 'You are invited'}
            </p>
            <h1><LocalizedEventContent value={event.name} locale={locale} /></h1>
            <p className="registration-invite-lead"><LocalizedEventContent value={event.description} locale={locale} /></p>
            <EventVenueSchedule
              locale={locale}
              venues={event.venues ?? []}
              startAt={event.start_at}
              endAt={event.end_at}
              timeZone={event.timezone}
              selectedVenueId={selectedVenueId}
              onSelectVenue={onSelectVenue}
            />
          </header>
        ) : null}
        {children}
      </div>
    </div>
  )
}
