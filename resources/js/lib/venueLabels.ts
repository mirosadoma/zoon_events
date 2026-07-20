import type { RegistrationHeroVenue } from '@/components/registration/RegistrationEventHero'
import { formatDateOnly, formatDateTime } from '@/lib/formatters'

function ordinalSuffix(day: number): string {
  if (day >= 11 && day <= 13) {
    return 'th'
  }

  return ({ 1: 'st', 2: 'nd', 3: 'rd' })[day % 10] ?? 'th'
}

export function formatVenuePillLabel(
  venue: RegistrationHeroVenue,
  locale: 'en' | 'ar',
  timeZone?: string,
): string {
  const city = (venue.city[locale] ?? venue.city.en ?? venue.city.ar ?? '').trim()
  const name = (venue.name[locale] ?? venue.name.en ?? venue.name.ar ?? '').trim()
  const venueLabel = locale === 'en' ? name.toUpperCase() : name
  const date = venue.start_at
    ? formatDateOnly(venue.start_at, locale, timeZone)
    : ''
  const address = venue.location_address?.trim() ?? ''

  return [city, venueLabel || address, date].filter((part) => part !== '').join(' | ')
}

export function formatVenueSelectLabel(
  venue: RegistrationHeroVenue,
  locale: 'en' | 'ar',
  timeZone?: string,
): string {
  const city = (venue.city[locale] ?? venue.city.en ?? venue.city.ar ?? '').trim()
  const name = (venue.name[locale] ?? venue.name.en ?? venue.name.ar ?? '').trim()
  const venueLabel = locale === 'en' ? name.toUpperCase() : name

  if (!venue.start_at) {
    return [city, venueLabel].filter(Boolean).join(' - ')
  }

  if (locale === 'ar') {
    return `${city} - ${venueLabel} - ${formatDateTime(venue.start_at, locale, timeZone)}`
  }

  const date = new Date(venue.start_at)
  const weekday = date.toLocaleDateString('en-GB', { weekday: 'long', ...(timeZone ? { timeZone } : {}) })
  const day = Number(date.toLocaleDateString('en-GB', { day: 'numeric', ...(timeZone ? { timeZone } : {}) }))
  const monthYear = date.toLocaleDateString('en-GB', { month: 'long', year: 'numeric', ...(timeZone ? { timeZone } : {}) })
  const time = date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
    ...(timeZone ? { timeZone } : {}),
  })

  return `${city} - ${venueLabel} - ${weekday}, ${day}${ordinalSuffix(day)} ${monthYear} at ${time}`
}
