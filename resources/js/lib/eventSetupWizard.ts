import type { AppLocale } from '@/lib/localePath'
import { allowsPaidTicketing, requiresTicketing } from '@/lib/eventOptions'
import en from '@/locales/en'
import ar from '@/locales/ar'

export type EventSetupWizardStep = 'type' | 'details' | 'schedule' | 'branding' | 'review'

export type EventSetupWizardForm = {
  slug: string
  name_en: string
  name_ar: string
  description_en: string
  description_ar: string
  tier: string
  event_type: string
  registration_mode: string
  timezone: string
  capacity: string
  brand_reference: string
  domain_reference: string
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
      description_en: 'Pick the format, audience tier, and whether attendees pay for tickets.',
      description_ar: 'حدد الشكل، فئة الجمهور، وهل الحضور يدفعون تذاكراً.',
    },
    details: {
      title_en: 'Name and capacity',
      title_ar: 'الاسم والسعة',
      description_en: 'Set how the event appears publicly and how many people can register.',
      description_ar: 'حدد كيف تظهر الفعالية للجمهور وعدد المسجّلين المسموح.',
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
      description_en: 'Upload the hero image and connect your white-label domain.',
      description_ar: 'ارفع الصورة الرئيسية واربط نطاق العلامة البيضاء.',
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
    hasMainImage: boolean
    venueCount: number
  },
): Record<string, string> {
  const errors: Record<string, string> = {}
  const required = (options.locale === 'ar' ? ar : en).fieldRequired

  if (step === 'type') {
    if (!form.event_type) errors.event_type = required
    if (!form.tier) errors.tier = required
    if (allowsPaidTicketing(form.tier) && !form.registration_mode) {
      errors.registration_mode = required
    }
  }

  if (step === 'details') {
    if (!form.slug.trim()) errors.slug = required
    if (!form.name_en.trim()) errors['name.en'] = required
    if (!form.name_ar.trim()) errors['name.ar'] = required
    if (!form.capacity.trim() || Number(form.capacity) < 1) errors.capacity = required
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

  if (step === 'branding' && !options.hasMainImage) {
    errors.main_image = options.locale === 'ar'
      ? 'الصورة الرئيسية مطلوبة.'
      : 'Main image is required.'
  }

  return errors
}

export function eventSetupWizardSummary(
  locale: AppLocale,
  form: EventSetupWizardForm,
  labels: {
    eventTypes: Record<string, string>
    tiers: Record<string, string>
    registrationModes: Record<string, string>
  },
): Array<{ label: string; value: string }> {
  const registrationLabel = requiresTicketing(form.tier, form.registration_mode)
    ? labels.registrationModes.paid_ticketing
    : labels.registrationModes.free_registration

  if (locale === 'ar') {
    return [
      { label: 'نوع الفعالية', value: labels.eventTypes[form.event_type] ?? form.event_type },
      { label: 'الفئة', value: labels.tiers[form.tier] ?? form.tier },
      { label: 'التسجيل', value: registrationLabel },
      { label: 'الاسم', value: form.name_ar || form.name_en },
      { label: 'السعة', value: form.capacity },
      { label: 'المنطقة الزمنية', value: form.timezone },
    ]
  }

  return [
    { label: 'Event type', value: labels.eventTypes[form.event_type] ?? form.event_type },
    { label: 'Tier', value: labels.tiers[form.tier] ?? form.tier },
    { label: 'Registration', value: registrationLabel },
    { label: 'Name', value: form.name_en || form.name_ar },
    { label: 'Capacity', value: form.capacity },
    { label: 'Timezone', value: form.timezone },
  ]
}
