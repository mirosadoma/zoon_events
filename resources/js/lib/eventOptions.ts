export const EVENT_TIERS = [
  {
    value: 'corporate',
    label_en: 'Corporate',
    label_ar: 'مؤسسي',
    description_en: 'Internal teams, invite lists, and controlled access.',
    description_ar: 'فرق داخلية، قوائم دعوة، ووصول محكوم.',
  },
  {
    value: 'public',
    label_en: 'Public',
    label_ar: 'عام',
    description_en: 'Open registration for broad audiences.',
    description_ar: 'تسجيل مفتوح لجمهور واسع.',
  },
  {
    value: 'vip',
    label_en: 'VIP',
    label_ar: 'VIP',
    description_en: 'Invite-only with elevated arrival experience.',
    description_ar: 'بدعوة فقط مع تجربة وصول مميزة.',
  },
  {
    value: 'vvip',
    label_en: 'VVIP',
    label_ar: 'VVIP',
    description_en: 'Highest assurance with strict identity controls.',
    description_ar: 'أعلى مستوى أمان مع ضوابط هوية صارمة.',
  },
] as const

export type EventTier = (typeof EVENT_TIERS)[number]['value']

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

export function allowsPaidTicketing(tier: string): boolean {
  return tier === 'public'
}

export function requiresTicketing(tier: string, registrationMode: string): boolean {
  return tier === 'public' && registrationMode === 'paid_ticketing'
}
