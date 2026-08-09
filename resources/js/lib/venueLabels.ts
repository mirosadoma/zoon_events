import type { LocalizedText } from '@/components/registration/LocalizedEventContent'
import { formatAsWallClock, formatWallClockDateOnly, isNaiveWallClockDateTime } from '@/lib/dateTimeLocal'
import { formatDateOnly } from '@/lib/formatters'

/** Minimal venue shape needed for select/pill labels (country unused). */
export type VenueLabelSource = {
  id?: string
  name: LocalizedText
  city: LocalizedText
  location_address?: string
  start_at?: string | null
}

function ordinalSuffix(day: number): string {
  if (day >= 11 && day <= 13) {
    return 'th'
  }

  return ({ 1: 'st', 2: 'nd', 3: 'rd' })[day % 10] ?? 'th'
}

function formatVenueDateOnly(
  startAt: string,
  locale: 'en' | 'ar',
  timeZone?: string,
): string {
  if (isNaiveWallClockDateTime(startAt) || !timeZone) {
    return formatWallClockDateOnly(startAt, locale)
  }

  return formatDateOnly(startAt, locale, timeZone)
}

export function formatVenuePillLabel(
  venue: VenueLabelSource,
  locale: 'en' | 'ar',
  timeZone?: string,
): string {
  const city = (venue.city[locale] ?? venue.city.en ?? venue.city.ar ?? '').trim()
  const name = (venue.name[locale] ?? venue.name.en ?? venue.name.ar ?? '').trim()
  const venueLabel = locale === 'en' ? name.toUpperCase() : name
  const date = venue.start_at
    ? formatVenueDateOnly(venue.start_at, locale, timeZone)
    : ''
  const address = venue.location_address?.trim() ?? ''

  return [city, venueLabel || address, date].filter((part) => part !== '').join(' | ')
}

export function formatVenueSelectLabel(
  venue: VenueLabelSource,
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
    return `${city} - ${venueLabel} - ${formatVenueDateOnly(venue.start_at, locale, timeZone)}`
  }

  const weekday = isNaiveWallClockDateTime(venue.start_at) || !timeZone
    ? formatAsWallClock(venue.start_at, 'en', { weekday: 'long' })
    : new Intl.DateTimeFormat('en-GB', { weekday: 'long', timeZone, numberingSystem: 'latn' })
      .format(new Date(venue.start_at))
  const dayLabel = isNaiveWallClockDateTime(venue.start_at) || !timeZone
    ? formatAsWallClock(venue.start_at, 'en', { day: 'numeric' })
    : new Intl.DateTimeFormat('en-GB', { day: 'numeric', timeZone, numberingSystem: 'latn' })
      .format(new Date(venue.start_at))
  const monthYear = isNaiveWallClockDateTime(venue.start_at) || !timeZone
    ? formatAsWallClock(venue.start_at, 'en', { month: 'long', year: 'numeric' })
    : new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric', timeZone, numberingSystem: 'latn' })
      .format(new Date(venue.start_at))
  const day = Number(dayLabel)

  return `${city} - ${venueLabel} - ${weekday}, ${day}${ordinalSuffix(day)} ${monthYear}`
}
