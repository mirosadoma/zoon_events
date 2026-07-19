import type { AppLocale } from '@/lib/localePath'
import type { EventTierOption } from '@/lib/eventOptions'
import { encodeEventTiers } from '@/lib/eventOptions'
import en from '@/locales/en'
import ar from '@/locales/ar'

export type EventSetupWizardStep = 'type' | 'details' | 'schedule' | 'branding' | 'review'

export type EventSetupWizardForm = {
  slug: string
  name_en: string
  name_ar: string
  description_en: string
  description_ar: string
  tiers: EventTierOption[]
  event_type: string
  timezone: string
  domain_reference: string
  text_color: string
  background_color: string
  organizer_user_id: string
}

export function eventSetupWizardSteps(locale: AppLocale): Array<{ key: EventSetupWizardStep; label: string }> {
  if (locale === 'ar') {
    return [
      { key: 'type', label: 'نوع الفعالية' },
      { key: 'details', label: 'التفاصيل' },
      { key: 'schedule', label: 'الجدول' },
      { key: 'branding', label: 'الهوية' },
      { key: 'review', label: 'المراجعة' },
    ]
  }

  return [
    { key: 'type', label: 'Event type' },
    { key: 'details', label: 'Details' },
    { key: 'schedule', label: 'Schedule' },
    { key: 'branding', label: 'Branding' },
    { key: 'review', label: 'Review' },
  ]
}

export function eventSetupWizardStepCopy(
  step: EventSetupWizardStep,
  locale: AppLocale,
): { title: string; description: string } {
  const copy: Record<EventSetupWizardStep, { title_en: string; title_ar: string; description_en: string; description_ar: string }> = {
    type: {
      title_en: 'Choose the event profile',
      title_ar: 'اختر ملف الفعالية',
      description_en: 'Pick the format and type. You can select Public, Private, or both.',
      description_ar: 'حدد الشكل والنوع. يمكنك اختيار عام أو خاص أو كليهما.',
    },
    details: {
      title_en: 'Name and details',
      title_ar: 'الاسم والتفاصيل',
      description_en: 'Set how the event appears publicly.',
      description_ar: 'حدد كيف تظهر الفعالية للجمهور.',
    },
    schedule: {
      title_en: 'Schedule and venues',
      title_ar: 'الجدول والمواقع',
      description_en: 'Add where the event happens and when registration opens.',
      description_ar: 'أضف مكان الفعالية ومواعيد فتح التسجيل.',
    },
    branding: {
      title_en: 'Branding and visuals',
      title_ar: 'الهوية والصور',
      description_en: 'Add logos, text and background colors, then upload the hero image.',
      description_ar: 'أضف الشعارات وألوان النص والخلفية ثم ارفع الصورة الرئيسية.',
    },
    review: {
      title_en: 'Review and create',
      title_ar: 'مراجعة وإنشاء',
      description_en: 'Confirm the setup before creating the event workspace.',
      description_ar: 'راجع الإعداد قبل إنشاء مساحة الفعالية.',
    },
  }

  const row = copy[step]

  return {
    title: locale === 'ar' ? row.title_ar : row.title_en,
    description: locale === 'ar' ? row.description_ar : row.description_en,
  }
}

export function validateEventSetupWizardStep(
  step: EventSetupWizardStep,
  form: EventSetupWizardForm,
  options: {
    locale: AppLocale
    requiresOrganizerSelection: boolean
    venueCount: number
  },
): Record<string, string> {
  const errors: Record<string, string> = {}
  const required = (options.locale === 'ar' ? ar : en).fieldRequired

  if (step === 'type') {
    if (!form.event_type) errors.event_type = required
    if (form.tiers.length === 0) errors.tier = required
  }

  if (step === 'details') {
    if (!form.slug.trim()) errors.slug = required
    if (!form.name_en.trim()) errors['name.en'] = required
    if (!form.name_ar.trim()) errors['name.ar'] = required
    if (options.requiresOrganizerSelection && !form.organizer_user_id) {
      errors.organizer_user_id = required
    }
  }

  if (step === 'schedule') {
    if (!form.timezone) errors.timezone = required
    if (options.venueCount < 1) {
      errors.venues = options.locale === 'ar'
        ? 'أضف موقعاً واحداً على الأقل مع الجدول الزمني.'
        : 'Add at least one venue with a schedule.'
    }
  }

  return errors
}

export function eventSetupWizardSummary(
  locale: AppLocale,
  form: EventSetupWizardForm,
  labels: {
    eventTypes: Record<string, string>
    tiers: Record<string, string>
  },
): Array<{ label: string; value: string }> {
  const tierValue = encodeEventTiers(form.tiers)
  const tierLabel = labels.tiers[tierValue]
    ?? form.tiers.map((tier) => labels.tiers[tier] ?? tier).join(locale === 'ar' ? ' و ' : ' & ')

  if (locale === 'ar') {
    return [
      { label: 'شكل الفعالية', value: labels.eventTypes[form.event_type] ?? form.event_type },
      { label: 'النوع', value: tierLabel },
      { label: 'الاسم', value: form.name_ar || form.name_en },
      { label: 'المنطقة الزمنية', value: form.timezone },
    ]
  }

  return [
    { label: 'Event format', value: labels.eventTypes[form.event_type] ?? form.event_type },
    { label: 'Type', value: tierLabel },
    { label: 'Name', value: form.name_en || form.name_ar },
    { label: 'Timezone', value: form.timezone },
  ]
}
