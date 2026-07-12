import type { ReactNode } from 'react'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import { formatVenuePillLabel } from '@/lib/venueLabels'

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
  children?: ReactNode
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
          aria-label={locale === 'ar' ? 'صور الفعالية' : 'Event photos'}
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
}: {
  locale: 'en' | 'ar'
  venues: RegistrationHeroVenue[]
  startAt?: string | null
  endAt?: string | null
}) {
  const rtl = locale === 'ar'

  if (venues.length > 0) {
    return (
      <div className="registration-event-venues" aria-label={rtl ? 'أماكن الفعالية' : 'Event venues'}>
        {venues.map((venue) => (
          <p key={venue.id} className="registration-event-venue-pill">
            {formatVenuePillLabel(venue, locale)}
          </p>
        ))}
      </div>
    )
  }

  if (!startAt) {
    return null
  }

  return (
    <p className="registration-invite-schedule">
      {new Date(startAt).toLocaleString(rtl ? 'ar-EG' : 'en-US')}
      {endAt ? ` — ${new Date(endAt).toLocaleString(rtl ? 'ar-EG' : 'en-US')}` : ''}
    </p>
  )
}

export default function RegistrationEventHero({ locale, event, isPreview = false, children }: Props) {
  const rtl = locale === 'ar'

  return (
    <div className="registration-invite-hero">
      <div className="registration-invite-card">
        {isPreview ? (
          <div className="registration-preview-banner" role="status">
            {rtl
              ? 'معاينة للمنظم — عرض فقط. التسجيل الحقيقي يتم عبر رابط الزوار.'
              : 'Organizer preview — display only. Real registration uses the visitor link.'}
          </div>
        ) : null}
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
            {isPreview
              ? (rtl ? 'معاينة صفحة التسجيل' : 'Registration page preview')
              : (rtl ? 'دعوة للتسجيل' : 'You are invited')}
          </p>
          <h1><LocalizedEventContent value={event.name} locale={locale} /></h1>
          <p className="registration-invite-lead"><LocalizedEventContent value={event.description} locale={locale} /></p>
          <EventVenueSchedule
            locale={locale}
            venues={event.venues ?? []}
            startAt={event.start_at}
            endAt={event.end_at}
          />
        </header>
        {children}
      </div>
    </div>
  )
}
