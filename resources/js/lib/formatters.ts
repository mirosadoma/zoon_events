import {
  formatAsWallClock,
  formatWallClockDateOnly,
  formatWallClockDateTime,
  formatWallClockTime,
  isNaiveWallClockDateTime,
} from '@/lib/dateTimeLocal'

function formatInstant(
  value: string,
  locale: 'en' | 'ar',
  options: Intl.DateTimeFormatOptions,
  timeZone: string,
): string {
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) {
    return ''
  }

  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG' : 'en-US', {
    ...options,
    timeZone,
    numberingSystem: 'latn',
  }).format(parsed)
}

/**
 * Format event / venue datetimes project-wide.
 *
 * - Naive wall-clock (`YYYY-MM-DDTHH:mm` from EventWallClockDateTime::toInput): keep digits.
 * - Offset / Z ISO without an explicit timezone: keep digits (payloads should already be
 *   event-local via EventWallClockDateTime::toIso8601 — never browser-shift).
 * - Offset / Z ISO WITH event timezone: convert via Intl in that zone.
 */
export function formatDate(value: string, locale: 'en' | 'ar', timeZone?: string) {
  if (!timeZone || isNaiveWallClockDateTime(value)) {
    return formatAsWallClock(value, locale, {
      dateStyle: 'medium',
      timeStyle: 'short',
    })
  }

  return formatInstant(value, locale, { dateStyle: 'medium', timeStyle: 'short' }, timeZone)
}

export function formatDateOnly(value: string, locale: 'en' | 'ar', timeZone?: string) {
  if (!timeZone || isNaiveWallClockDateTime(value)) {
    return formatWallClockDateOnly(value, locale)
  }

  return formatInstant(value, locale, {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }, timeZone)
}

export function formatDateTime(value: string, locale: 'en' | 'ar', timeZone?: string) {
  if (!timeZone || isNaiveWallClockDateTime(value)) {
    return formatWallClockDateTime(value, locale)
  }

  return formatInstant(value, locale, { dateStyle: 'medium', timeStyle: 'short' }, timeZone)
}

export function formatTime(value: string, locale: 'en' | 'ar', timeZone?: string) {
  if (!timeZone || isNaiveWallClockDateTime(value)) {
    return formatWallClockTime(value, locale)
  }

  return formatInstant(value, locale, {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  }, timeZone)
}

export function formatNumber(value: number, locale: 'en' | 'ar') {
  return new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-GB', {
    numberingSystem: 'latn',
  }).format(value)
}
