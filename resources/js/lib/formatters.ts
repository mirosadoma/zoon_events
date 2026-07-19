export function formatDate(value: string, locale: 'en' | 'ar', timeZone = 'UTC') {
  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA' : 'en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone,
    numberingSystem: 'latn',
  }).format(new Date(value))
}

export function formatNumber(value: number, locale: 'en' | 'ar') {
  return new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-GB', {
    numberingSystem: 'latn',
  }).format(value)
}
