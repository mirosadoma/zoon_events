import type { RegistrationHeroVenue } from '@/components/registration/RegistrationEventHero'

function ordinalSuffix(day: number): string {
  if (day >= 11 && day <= 13) {
    return 'th'
  }

  return ({ 1: 'st', 2: 'nd', 3: 'rd' })[day % 10] ?? 'th'
}

export function formatVenuePillLabel(venue: RegistrationHeroVenue, locale: 'en' | 'ar'): string {
  const city = (venue.city[locale] ?? venue.city.en ?? venue.city.ar ?? '').trim()
  const name = (venue.name[locale] ?? venue.name.en ?? venue.name.ar ?? '').trim()
  const venueLabel = locale === 'en' ? name.toUpperCase() : name
  const date = venue.start_at
    ? new Date(venue.start_at).toLocaleDateString(locale === 'ar' ? 'ar-EG' : 'en-GB', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    })
    : ''
  const address = venue.location_address?.trim() ?? ''

  return [city, venueLabel || address, date].filter((part) => part !== '').join(' | ')
}

export function formatVenueSelectLabel(venue: RegistrationHeroVenue, locale: 'en' | 'ar'): string {
  const city = (venue.city[locale] ?? venue.city.en ?? venue.city.ar ?? '').trim()
  const name = (venue.name[locale] ?? venue.name.en ?? venue.name.ar ?? '').trim()
  const venueLabel = locale === 'en' ? name.toUpperCase() : name

  if (!venue.start_at) {
    return [city, venueLabel].filter(Boolean).join(' - ')
  }

  const date = new Date(venue.start_at)

  if (locale === 'ar') {
    const when = date.toLocaleString('ar-EG', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    })

    return `${city} - ${venueLabel} - ${when}`
  }

  const weekday = date.toLocaleDateString('en-GB', { weekday: 'long' })
  const day = date.getDate()
  const monthYear = date.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
  const time = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })

  return `${city} - ${venueLabel} - ${weekday}, ${day}${ordinalSuffix(day)} ${monthYear} at ${time}`
}
