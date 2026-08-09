/**
 * Convert API/form datetime strings into `datetime-local` values without
 * shifting by the browser timezone (the 11 AM → 2 PM bug).
 *
 * Prefer backend wall-clock strings (`YYYY-MM-DDTHH:mm` in event timezone).
 */
export function toDateTimeLocalValue(value: string | null | undefined): string {
  if (!value) {
    return ''
  }

  const match = value.trim().match(/^(\d{4}-\d{2}-\d{2})[T ](\d{2}):(\d{2})/)
  if (!match) {
    return ''
  }

  return `${match[1]}T${match[2]}:${match[3]}`
}

/** True when the string has no Z / numeric offset (event wall-clock payload). */
export function isNaiveWallClockDateTime(value: string): boolean {
  return !/(Z|[+-]\d{2}:?\d{2})$/i.test(value.trim())
}

/** Extract `HH:mm` from a wall-clock / ISO string without browser timezone shift. */
export function toTimeLocalValue(value: string | null | undefined): string {
  const full = toDateTimeLocalValue(value)
  if (!full) {
    return ''
  }

  return full.slice(11, 16)
}

/** Extract `YYYY-MM-DD` without browser timezone shift. */
export function toDateLocalValue(value: string | null | undefined): string {
  return toDateTimeLocalValue(value).slice(0, 10)
}

type IntlParts = {
  year: number
  month: number
  day: number
  hours: number
  minutes: number
}

function wallClockParts(value: string): IntlParts | null {
  const local = toDateTimeLocalValue(value)
  if (!local) {
    const dateOnly = value.trim().match(/^(\d{4})-(\d{2})-(\d{2})$/)
    if (!dateOnly) {
      return null
    }

    return {
      year: Number(dateOnly[1]),
      month: Number(dateOnly[2]),
      day: Number(dateOnly[3]),
      hours: 12,
      minutes: 0,
    }
  }

  const [datePart, timePart] = local.split('T')
  const [year, month, day] = datePart.split('-').map(Number)
  const [hours, minutes] = timePart.split(':').map(Number)

  return { year, month, day, hours, minutes }
}

/** Format using the literal date/time digits (no browser TZ conversion). */
export function formatAsWallClock(
  value: string,
  locale: 'en' | 'ar',
  options: Intl.DateTimeFormatOptions,
): string {
  const parts = wallClockParts(value)
  if (!parts) {
    return ''
  }

  const asUtc = new Date(Date.UTC(parts.year, parts.month - 1, parts.day, parts.hours, parts.minutes))

  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG' : 'en-US', {
    ...options,
    timeZone: 'UTC',
    numberingSystem: 'latn',
  }).format(asUtc)
}

/**
 * Format a wall-clock datetime (`YYYY-MM-DDTHH:mm`) for display without browser TZ shift.
 */
export function formatWallClockDateTime(value: string | null | undefined, locale: 'en' | 'ar'): string {
  if (!value) {
    return ''
  }

  return formatAsWallClock(value, locale, {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

export function formatWallClockDateOnly(value: string | null | undefined, locale: 'en' | 'ar'): string {
  if (!value) {
    return ''
  }

  return formatAsWallClock(value, locale, {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

export function formatWallClockTime(value: string | null | undefined, locale: 'en' | 'ar'): string {
  if (!value) {
    return ''
  }

  return formatAsWallClock(value, locale, {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })
}

/**
 * Build a naive wall-clock datetime (`YYYY-MM-DDTHH:mm`) from a date source + HH:mm.
 * Does not apply browser timezone conversion.
 */
export function combineDateAndTime(
  dateSource: string | null | undefined,
  timeHHmm: string,
): string | null {
  if (!timeHHmm.trim()) {
    return null
  }

  const timeMatch = /^(\d{1,2}):(\d{2})$/.exec(timeHHmm.trim())
  if (!timeMatch) {
    return null
  }

  const hours = Number(timeMatch[1])
  const minutes = Number(timeMatch[2])
  if (hours > 23 || minutes > 59) {
    return null
  }

  const datePart = toDateLocalValue(dateSource)
  if (!/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
    return null
  }

  const pad = (n: number) => n.toString().padStart(2, '0')

  return `${datePart}T${pad(hours)}:${pad(minutes)}`
}

/**
 * Inclusive list of `YYYY-MM-DD` dates between two wall-clock datetimes.
 * Avoids `toISOString()` day-shift bugs.
 */
export function eachWallClockDate(
  startAt: string | null | undefined,
  endAt: string | null | undefined,
): string[] {
  const startDate = toDateLocalValue(startAt)
  const endDate = toDateLocalValue(endAt)
  if (!/^\d{4}-\d{2}-\d{2}$/.test(startDate) || !/^\d{4}-\d{2}-\d{2}$/.test(endDate)) {
    return []
  }

  const pad = (n: number) => n.toString().padStart(2, '0')
  const dates: string[] = []
  let [year, month, day] = startDate.split('-').map(Number)

  while (true) {
    const current = `${pad(year)}-${pad(month)}-${pad(day)}`
    dates.push(current)
    if (current >= endDate) {
      break
    }

    const next = new Date(Date.UTC(year, month - 1, day + 1))
    year = next.getUTCFullYear()
    month = next.getUTCMonth() + 1
    day = next.getUTCDate()
  }

  return dates
}
