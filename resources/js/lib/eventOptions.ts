export const EVENT_TIERS = [
  { value: 'corporate', label_en: 'Corporate', label_ar: 'مؤسسي' },
  { value: 'public', label_en: 'Public', label_ar: 'عام' },
  { value: 'vip', label_en: 'VIP', label_ar: 'VIP' },
  { value: 'vvip', label_en: 'VVIP', label_ar: 'VVIP' },
] as const

export type EventTier = (typeof EVENT_TIERS)[number]['value']
