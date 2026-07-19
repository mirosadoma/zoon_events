export function formatDate(value: string, locale: 'en' | 'ar', timeZone = 'UTC') {
  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA' : 'en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone,
    numberingSystem: 'latn',
  }).format(new Date(value))
}

export function formatDateOnly(value: string, locale: 'en' | 'ar', timeZone?: string) {
  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG' : 'en-GB', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    ...(timeZone ? { timeZone } : {}),
    numberingSystem: 'latn',
  }).format(new Date(value))
}

export function formatDateTime(value: string, locale: 'en' | 'ar', timeZone?: string) {
  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG' : 'en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
    ...(timeZone ? { timeZone } : {}),
    numberingSystem: 'latn',
  }).format(new Date(value))
}

export function formatTime(value: string, locale: 'en' | 'ar', timeZone?: string) {
  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG' : 'en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
    ...(timeZone ? { timeZone } : {}),
    numberingSystem: 'latn',
  }).format(new Date(value))
}

export function formatNumber(value: number, locale: 'en' | 'ar') {
  return new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-GB', {
    numberingSystem: 'latn',
  }).format(value)
}
