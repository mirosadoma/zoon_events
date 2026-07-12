import type { AppLocale } from '@/lib/localePath'

type ReadinessEntry = {
  en: string
  ar: string
  href?: (eventId: string) => string
}

export const publishReadinessLabels: Record<string, ReadinessEntry> = {
  name_en: {
    en: 'English event name',
    ar: 'اسم الفعالية بالإنجليزية',
    href: (eventId) => `/tenant/events/${eventId}/edit`,
  },
  name_ar: {
    en: 'Arabic event name',
    ar: 'اسم الفعالية بالعربية',
    href: (eventId) => `/tenant/events/${eventId}/edit`,
  },
  timezone: {
    en: 'Event timezone',
    ar: 'المنطقة الزمنية للفعالية',
    href: (eventId) => `/tenant/events/${eventId}/edit`,
  },
  start_at: {
    en: 'Event start date and time',
    ar: 'تاريخ ووقت بداية الفعالية',
    href: (eventId) => `/tenant/events/${eventId}/edit`,
  },
  end_at: {
    en: 'Event end date and time',
    ar: 'تاريخ ووقت نهاية الفعالية',
    href: (eventId) => `/tenant/events/${eventId}/edit`,
  },
  registration_opens_at: {
    en: 'Registration open date and time',
    ar: 'تاريخ ووقت فتح التسجيل',
    href: (eventId) => `/tenant/events/${eventId}/edit`,
  },
  registration_closes_at: {
    en: 'Registration close date and time',
    ar: 'تاريخ ووقت إغلاق التسجيل',
    href: (eventId) => `/tenant/events/${eventId}/edit`,
  },
  active_form_version_id: {
    en: 'Published registration form',
    ar: 'نموذج تسجيل منشور',
    href: (eventId) => `/tenant/events/${eventId}/registration-form`,
  },
  active_ticket_type: {
    en: 'At least one active ticket type',
    ar: 'نوع تذكرة نشط واحد على الأقل',
    href: (eventId) => `/tenant/events/${eventId}/ticket-types`,
  },
  active_branding: {
    en: 'Active event branding',
    ar: 'هوية بصرية نشطة للفعالية',
    href: (eventId) => `/tenant/events/${eventId}/edit`,
  },
  main_image: {
    en: 'Main event image',
    ar: 'الصورة الرئيسية للفعالية',
    href: (eventId) => `/tenant/events/${eventId}/edit`,
  },
  valid_timezone: {
    en: 'Valid timezone identifier',
    ar: 'معرّف منطقة زمنية صالح',
    href: (eventId) => `/tenant/events/${eventId}/edit`,
  },
  valid_schedule: {
    en: 'Valid schedule (registration opens before it closes, closes before event ends, event starts before it ends)',
    ar: 'جدول زمني صالح (يفتح التسجيل قبل الإغلاق، ويُغلق قبل نهاية الفعالية، وتبدأ الفعالية قبل انتهائها)',
    href: (eventId) => `/tenant/events/${eventId}/edit`,
  },
}

export function publishReadinessLabel(key: string, locale: AppLocale): string {
  const entry = publishReadinessLabels[key]

  if (entry) {
    return entry[locale]
  }

  return key
}

export function publishReadinessHref(key: string, eventId: string): string | undefined {
  return publishReadinessLabels[key]?.href?.(eventId)
}
