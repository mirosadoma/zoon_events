export function formatMoney(minor: number, currency: string, locale: 'en' | 'ar') {
  return new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-SA', {
    style: 'currency',
    currency,
  }).format(minor / 100)
}
