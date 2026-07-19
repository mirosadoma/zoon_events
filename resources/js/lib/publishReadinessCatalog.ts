import type { AppLocale } from '@/lib/localePath'
import en from '@/locales/en'
import ar from '@/locales/ar'

function msg(locale: AppLocale) {
  return locale === 'ar' ? ar : en
}

export type PublishReadinessContext = {
  status?: string
  requiresTicketing?: boolean
}

type ReadinessEntry = {
  en: string
  ar: string
  href?: (eventId: string, context?: PublishReadinessContext) => string | undefined
}

function editSection(eventId: string, section: string): string {
  return `/tenant/events/${eventId}/edit#${section}`
}

export const publishReadinessLabels: Record<string, ReadinessEntry> = {
  name_en: {
    en: 'English event name',
    ar: 'اسم الفعالية بالإنجليزية',
    href: (eventId) => editSection(eventId, 'event-setup-details'),
  },
  name_ar: {
    en: 'Arabic event name',
    ar: 'اسم الفعالية بالعربية',
    href: (eventId) => editSection(eventId, 'event-setup-details'),
  },
  timezone: {
    en: 'Event timezone',
    ar: 'المنطقة الزمنية للفعالية',
    href: (eventId) => editSection(eventId, 'event-setup-details'),
  },
  start_at: {
    en: 'Event start date and time',
    ar: 'تاريخ ووقت بداية الفعالية',
    href: (eventId) => editSection(eventId, 'event-setup-schedule'),
  },
  end_at: {
    en: 'Event end date and time',
    ar: 'تاريخ ووقت نهاية الفعالية',
    href: (eventId) => editSection(eventId, 'event-setup-schedule'),
  },
  registration_opens_at: {
    en: 'Registration open date and time',
    ar: 'تاريخ ووقت فتح التسجيل',
    href: (eventId) => editSection(eventId, 'event-setup-schedule'),
  },
  registration_closes_at: {
    en: 'Registration close date and time',
    ar: 'تاريخ ووقت إغلاق التسجيل',
    href: (eventId) => editSection(eventId, 'event-setup-schedule'),
  },
  published_agenda: {
    en: 'Published agenda',
    ar: 'أجندة منشورة',
    href: (eventId) => `/tenant/events/${eventId}/agenda`,
  },
  active_form_version_id: {
    en: 'Published registration form',
    ar: 'نموذج تسجيل منشور',
    href: (eventId) => `/tenant/events/${eventId}/registration-form`,
  },
  active_ticket_type: {
    en: 'At least one active ticket type',
    ar: 'نوع تذكرة نشط واحد على الأقل',
    href: (eventId, context) => (
      context?.requiresTicketing === false
        ? editSection(eventId, 'event-setup-type')
        : `/tenant/events/${eventId}/ticket-types`
    ),
  },
  event_categories: {
    en: 'At least one category with venue and dates assigned',
    ar: 'قسم واحد على الأقل مع موقع وتواريخ معيّنة',
    href: (eventId) => `/tenant/events/${eventId}/categories`,
  },
  active_branding: {
    en: 'Brand reference and domain reference',
    ar: 'مرجع العلامة التجارية ونطاق الفعالية',
    href: (eventId) => editSection(eventId, 'event-setup-branding'),
  },
  active_badge_template: {
    en: 'Active custom badge template',
    ar: 'قالب شارة مخصص نشط',
    href: (eventId) => `/tenant/events/${eventId}/badge-templates`,
  },
  valid_timezone: {
    en: 'Valid timezone identifier',
    ar: 'معرّف منطقة زمنية صالح',
    href: (eventId) => editSection(eventId, 'event-setup-details'),
  },
  valid_schedule: {
    en: 'Valid schedule (registration opens before it closes, closes before event ends)',
    ar: 'جدول زمني صالح (يفتح التسجيل قبل الإغلاق ويُغلق قبل نهاية الفعالية)',
    href: (eventId) => editSection(eventId, 'event-setup-schedule'),
  },
  invalid_status: {
    en: 'Event status does not allow publishing',
    ar: 'حالة الفعالية لا تسمح بالنشر',
    href: (eventId, context) => publishReadinessLabels[`status_${context?.status ?? ''}`]?.href?.(eventId, context),
  },
  status_published: {
    en: 'This event is already published',
    ar: 'هذه الفعالية منشورة بالفعل',
    href: (eventId) => `/tenant/events/${eventId}/registration-preview`,
  },
  status_registration_open: {
    en: 'Registration is already open — publishing is no longer available',
    ar: 'التسجيل مفتوح بالفعل — لا يمكن إعادة النشر',
    href: (eventId) => `/tenant/events/${eventId}/registration-form`,
  },
  status_registration_closed: {
    en: 'Registration is closed — event has moved past the publish step',
    ar: 'التسجيل مغلق — الفعالية تجاوزت مرحلة النشر',
    href: (eventId) => `/tenant/events/${eventId}`,
  },
  status_live: {
    en: 'Event is live — publishing is not available',
    ar: 'الفعالية جارية الآن — النشر غير متاح',
    href: (eventId) => `/tenant/events/${eventId}/check-in-dashboard`,
  },
  status_completed: {
    en: 'Event is completed',
    ar: 'الفعالية منتهية',
    href: (eventId) => `/tenant/events/${eventId}/reports`,
  },
  status_cancelled: {
    en: 'Event is cancelled — create a new event to publish again',
    ar: 'الفعالية ملغاة — أنشئ فعالية جديدة للنشر مجدداً',
    href: () => '/tenant/events/create',
  },
  status_archived: {
    en: 'Event is archived',
    ar: 'الفعالية مؤرشفة',
    href: (eventId) => `/tenant/events/${eventId}`,
  },
}

export function isPublishStatusBlocker(key: string): boolean {
  return key === 'invalid_status' || key.startsWith('status_')
}

export function resolvePublishReadinessKey(key: string, context?: PublishReadinessContext): string {
  if (key === 'invalid_status' && context?.status) {
    const statusKey = `status_${context.status}`

    if (publishReadinessLabels[statusKey]) {
      return statusKey
    }
  }

  return key
}

export function splitPublishReadiness(items: string[], context?: PublishReadinessContext): {
  requirements: string[]
  statusBlockers: string[]
} {
  const requirements: string[] = []
  const statusBlockers: string[] = []

  for (const item of items) {
    const resolved = resolvePublishReadinessKey(item, context)

    if (isPublishStatusBlocker(resolved)) {
      statusBlockers.push(resolved)
    } else {
      requirements.push(resolved)
    }
  }

  return {
    requirements: [...new Set(requirements)],
    statusBlockers: [...new Set(statusBlockers)],
  }
}

export function publishReadinessLabel(
  key: string,
  locale: AppLocale,
  context?: PublishReadinessContext,
): string {
  const resolved = resolvePublishReadinessKey(key, context)
  const entry = publishReadinessLabels[resolved]

  if (entry) {
    return entry[locale]
  }

  return key
}

export function publishReadinessHref(
  key: string,
  eventId: string,
  context?: PublishReadinessContext,
): string | undefined {
  const resolved = resolvePublishReadinessKey(key, context)

  return publishReadinessLabels[resolved]?.href?.(eventId, context)
}

export function canPublishEventStatus(status: string): boolean {
  return status === 'draft' || status === 'configured'
}

/** Statuses where publish is done or no longer the goal — not a setup failure. */
export function isPostPublishStatus(status: string): boolean {
  return [
    'published',
    'registration_open',
    'registration_closed',
    'live',
    'completed',
  ].includes(status)
}

export type PublishReadinessBadgeKind = 'ready' | 'blocked' | 'published' | 'unavailable'

export function publishReadinessBadgeKind(
  readiness: string[],
  context: PublishReadinessContext,
): PublishReadinessBadgeKind {
  const status = context.status ?? ''

  if (status === 'published') {
    return 'published'
  }

  if (isPostPublishStatus(status)) {
    return 'unavailable'
  }

  if (!canPublishEventStatus(status)) {
    return 'unavailable'
  }

  return isReadyToPublish(readiness, context) ? 'ready' : 'blocked'
}

export function isReadyToPublish(
  readiness: string[],
  context: PublishReadinessContext,
): boolean {
  if (!canPublishEventStatus(context.status ?? '')) {
    return false
  }

  const { requirements, statusBlockers } = splitPublishReadiness(readiness, context)

  return requirements.length === 0 && statusBlockers.length === 0
}

export function publishReadinessTooltip(
  readiness: string[],
  locale: AppLocale,
  context: PublishReadinessContext,
): string {
  const kind = publishReadinessBadgeKind(readiness, context)

  if (kind === 'ready') {
    return msg(locale).publishReadyMessage
  }

  if (kind === 'published') {
    return publishReadinessLabel('status_published', locale, context)
  }

  const { requirements, statusBlockers } = splitPublishReadiness(readiness, context)
  const reasons = [...statusBlockers, ...requirements].map((item) => publishReadinessLabel(item, locale, context))

  if (reasons.length === 0) {
    return msg(locale).publishBlockedMessage
  }

  if (kind === 'unavailable') {
    return reasons.join(' · ')
  }

  return `${msg(locale).publishCannotPrefix}${reasons.join(' · ')}`
}

export function publishBlockedMessage(
  readiness: string[],
  locale: AppLocale,
  context: PublishReadinessContext,
): string {
  if (isReadyToPublish(readiness, context)) {
    return msg(locale).publishReadyMessage
  }

  const { requirements, statusBlockers } = splitPublishReadiness(readiness, context)

  if (!canPublishEventStatus(context.status ?? '') && statusBlockers.length > 0) {
    const reason = publishReadinessLabel(statusBlockers[0], locale, context)

    return msg(locale).publishCannotBecause.replace(
      ':reason',
      `${reason.charAt(0).toLowerCase()}${reason.slice(1)}`,
    )
  }

  if (requirements.length > 0) {
    return msg(locale).eventDetailCannotPublish.replace(':count', String(requirements.length))
  }

  return msg(locale).publishBlockedMessage
}
