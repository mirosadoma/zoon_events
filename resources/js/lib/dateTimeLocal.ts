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

/** Extract `HH:mm` from a wall-clock / ISO string without browser timezone shift. */
export function toTimeLocalValue(value: string | null | undefined): string {
  const full = toDateTimeLocalValue(value)
  if (!full) {
    return ''
  }

  return full.slice(11, 16)
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

  const datePart = toDateTimeLocalValue(dateSource).slice(0, 10)
  if (!/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
    return null
  }

  const pad = (n: number) => n.toString().padStart(2, '0')

  return `${datePart}T${pad(hours)}:${pad(minutes)}`
}
