export const EVENT_TIERS = [
  {
    value: 'public',
    label_en: 'Public',
    label_ar: 'عام',
    description_en: 'Open registration for anyone with the link.',
    description_ar: 'تسجيل مفتوح لأي شخص لديه الرابط.',
  },
  {
    value: 'private',
    label_en: 'Private',
    label_ar: 'خاص',
    description_en: 'Invite-only access with controlled registration.',
    description_ar: 'وصول بدعوة فقط مع تسجيل محكوم.',
  },
] as const

export type EventTierOption = (typeof EVENT_TIERS)[number]['value']
export type EventTier = EventTierOption | 'both'

export const EVENT_TIER_BOTH_LABEL = {
  value: 'both' as const,
  label_en: 'Public & Private',
  label_ar: 'عام وخاص',
}

export const EVENT_TYPES = [
  {
    value: 'seminar',
    label_en: 'Seminar',
    label_ar: 'ندوة',
    description_en: 'Focused sessions with a smaller audience.',
    description_ar: 'جلسات مركزة لجمهور أصغر.',
  },
  {
    value: 'conference',
    label_en: 'Conference',
    label_ar: 'مؤتمر',
    description_en: 'Multi-track program with broader attendance.',
    description_ar: 'برنامج متعدد المسارات وحضور أوسع.',
  },
  {
    value: 'workshop',
    label_en: 'Workshop',
    label_ar: 'ورشة عمل',
    description_en: 'Hands-on training with limited seats.',
    description_ar: 'تدريب عملي بعدد مقاعد محدود.',
  },
  {
    value: 'corporate_gathering',
    label_en: 'Corporate gathering',
    label_ar: 'تجمع مؤسسي',
    description_en: 'Company-wide or partner-facing gatherings.',
    description_ar: 'تجمعات داخلية أو مع شركاء.',
  },
] as const

export type EventType = (typeof EVENT_TYPES)[number]['value']

export const REGISTRATION_MODES = [
  {
    value: 'free_registration',
    label_en: 'Free registration',
    label_ar: 'تسجيل مجاني',
    description_en: 'Attendees register without ticket checkout.',
    description_ar: 'يسجّل الحضور بدون دفع تذاكر.',
  },
  {
    value: 'paid_ticketing',
    label_en: 'Paid ticketing',
    label_ar: 'تذاكر مدفوعة',
    description_en: 'Sell tickets with pricing tiers.',
    description_ar: 'بيع تذاكر مع مستويات أسعار.',
  },
] as const

export type RegistrationMode = (typeof REGISTRATION_MODES)[number]['value']

export type EventCapabilities = {
  requires_ticketing?: boolean
  requires_price_tiers?: boolean
  allows_paid_ticketing?: boolean
}

export function encodeEventTiers(selected: EventTierOption[]): EventTier {
  const hasPublic = selected.includes('public')
  const hasPrivate = selected.includes('private')

  if (hasPublic && hasPrivate) {
    return 'both'
  }

  if (hasPrivate) {
    return 'private'
  }

  return 'public'
}

export function decodeEventTiers(tier: string): EventTierOption[] {
  if (tier === 'both') {
    return ['public', 'private']
  }

  if (tier === 'private') {
    return ['private']
  }

  return ['public']
}

export function labelForEventTier(tier: string | undefined, locale: 'en' | 'ar'): string {
  if (!tier) {
    return '—'
  }

  if (tier === 'both') {
    return locale === 'ar' ? EVENT_TIER_BOTH_LABEL.label_ar : EVENT_TIER_BOTH_LABEL.label_en
  }

  const match = EVENT_TIERS.find((option) => option.value === tier)

  return match ? (locale === 'ar' ? match.label_ar : match.label_en) : tier
}

export function allowsPaidTicketing(_tier: string): boolean {
  return false
}

export function requiresTicketing(_tier: string, _registrationMode: string): boolean {
  return false
}
