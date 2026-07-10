export const ATTENDEE_TYPES = [
  { value: 'general', label_en: 'General', label_ar: 'عام' },
  { value: 'vip', label_en: 'VIP', label_ar: 'VIP' },
  { value: 'vvip', label_en: 'VVIP', label_ar: 'VVIP' },
  { value: 'staff', label_en: 'Staff', label_ar: 'طاقم' },
  { value: 'corporate', label_en: 'Corporate', label_ar: 'مؤسسي' },
  { value: 'vendor', label_en: 'Vendor', label_ar: 'مورد' },
] as const

export const CURRENCIES = [
  { code: 'SAR', label_en: 'Saudi Riyal', label_ar: 'ريال سعودي' },
  { code: 'EGP', label_en: 'Egyptian Pound', label_ar: 'جنيه مصري' },
  { code: 'AED', label_en: 'UAE Dirham', label_ar: 'درهم إماراتي' },
  { code: 'USD', label_en: 'US Dollar', label_ar: 'دولار أمريكي' },
  { code: 'EUR', label_en: 'Euro', label_ar: 'يورو' },
  { code: 'GBP', label_en: 'British Pound', label_ar: 'جنيه إسترليني' },
  { code: 'KWD', label_en: 'Kuwaiti Dinar', label_ar: 'دينار كويتي' },
  { code: 'QAR', label_en: 'Qatari Riyal', label_ar: 'ريال قطري' },
  { code: 'BHD', label_en: 'Bahraini Dinar', label_ar: 'دينار بحريني' },
  { code: 'OMR', label_en: 'Omani Rial', label_ar: 'ريال عُماني' },
  { code: 'JOD', label_en: 'Jordanian Dinar', label_ar: 'دينار أردني' },
] as const

export function currencyLabel(code: string, locale: 'en' | 'ar'): string {
  const match = CURRENCIES.find((currency) => currency.code === code)

  if (!match) return code

  return locale === 'ar' ? match.label_ar : match.label_en
}
