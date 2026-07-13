import type { Locale } from '@/hooks/useLocale'
import { translateValidationMessage } from '@/lib/translateValidationMessage'

export type FieldLabelMap = Record<string, { en: string; ar: string }>

export function formFieldSelector(apiKey: string): string | null {
  if (!apiKey) {
    return null
  }

  return `[data-form-field="${apiKey.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"]`
}

function humanizeFieldKey(key: string, locale: Locale): string {
  const normalized = key.replace(/\./g, ' ').replace(/_/g, ' ')
  return normalized.replace(/\b\w/g, (char) => char.toUpperCase())
}

function arrayItemLabel(group: string, itemNumber: number, locale: Locale): string {
  if (group === 'items') {
    return locale === 'ar' ? `البند ${itemNumber}` : `Item ${itemNumber}`
  }

  if (group === 'fields') {
    return locale === 'ar' ? `الحقل ${itemNumber}` : `Field ${itemNumber}`
  }

  if (group === 'venues') {
    return locale === 'ar' ? `الموقع ${itemNumber}` : `Venue ${itemNumber}`
  }

  return locale === 'ar' ? `العنصر ${itemNumber}` : `Entry ${itemNumber}`
}

export function formatValidationFieldMessage(
  apiKey: string,
  message: string,
  locale: Locale,
  fieldLabels?: FieldLabelMap,
): string {
  const directLabel = fieldLabels?.[apiKey]?.[locale]
  const translatedMessage = translateValidationMessage(message, locale)
  if (directLabel) {
    return `${directLabel}: ${translatedMessage}`
  }

  const arrayMatch = apiKey.match(/^(\w+)\.(\d+)\.(.+)$/)
  if (arrayMatch) {
    const [, group, index, rest] = arrayMatch
    const itemNumber = Number(index) + 1
    const itemLabel = arrayItemLabel(group, itemNumber, locale)
    const fieldLabel = fieldLabels?.[rest]?.[locale]
      ?? fieldLabels?.[apiKey]?.[locale]
      ?? humanizeFieldKey(rest, locale)

    return `${itemLabel} · ${fieldLabel}: ${translatedMessage}`
  }

  const humanized = humanizeFieldKey(apiKey, locale)
  return `${humanized}: ${translatedMessage}`
}

export function validationMessagesFromErrors(
  errors: Record<string, string>,
  locale: Locale,
  formatter: (key: string, message: string, locale: Locale) => string = (_key, message) => message,
): string[] {
  return Object.entries(errors).map(([key, message]) => formatter(key, message, locale))
}

export function firstValidationTarget(
  errors: Record<string, string>,
  selectorForKey: (key: string) => string | null,
): string | null {
  for (const key of Object.keys(errors)) {
    const selector = selectorForKey(key)
    if (selector) {
      return selector
    }
  }

  return null
}

export function formFieldProps(key: string): { 'data-form-field': string } {
  return { 'data-form-field': key }
}

export function normalizeInertiaErrors(
  errors?: Record<string, string | string[]> | null,
): Record<string, string> {
  if (!errors) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(errors)
      .map(([key, value]) => [
        key,
        Array.isArray(value) ? (value[0] ?? '') : value,
      ])
      .filter((entry): entry is [string, string] => entry[1] !== ''),
  )
}

const AGENDA_FIELD_LABELS: FieldLabelMap = {
  title_en: { en: 'Title (EN)', ar: 'العنوان (EN)' },
  title_ar: { en: 'Title (AR)', ar: 'العنوان (AR)' },
  start_at: { en: 'Starts at', ar: 'يبدأ في' },
  end_at: { en: 'Ends at', ar: 'ينتهي في' },
}

export function agendaFieldSelector(apiKey: string, formIndexByPayloadIndex?: number[]): string | null {
  const match = apiKey.match(/^items\.(\d+)\.(\w+)$/)
  if (!match) {
    return formFieldSelector(apiKey)
  }

  const payloadIndex = Number(match[1])
  const formIndex = formIndexByPayloadIndex?.[payloadIndex] ?? payloadIndex

  return formFieldSelector(`items.${formIndex}.${match[2]}`)
}

export function formatAgendaValidationMessage(
  apiKey: string,
  message: string,
  locale: Locale,
  formIndexByPayloadIndex?: number[],
): string {
  const match = apiKey.match(/^items\.(\d+)\.(.+)$/)
  if (!match) {
    return formatValidationFieldMessage(apiKey, message, locale, AGENDA_FIELD_LABELS)
  }

  const payloadIndex = Number(match[1])
  const formIndex = formIndexByPayloadIndex?.[payloadIndex] ?? payloadIndex
  const remappedKey = `items.${formIndex}.${match[2]}`

  return formatValidationFieldMessage(remappedKey, message, locale, AGENDA_FIELD_LABELS)
}

export function remapAgendaApiErrors(
  errors: Record<string, string>,
  formIndexByPayloadIndex: number[],
): Record<string, string> {
  const remapped: Record<string, string> = {}

  for (const [key, message] of Object.entries(errors)) {
    const match = key.match(/^items\.(\d+)\.(\w+)$/)
    if (!match) {
      remapped[key] = message
      continue
    }

    const payloadIndex = Number(match[1])
    const formIndex = formIndexByPayloadIndex[payloadIndex] ?? payloadIndex
    remapped[`items.${formIndex}.${match[2]}`] = message
  }

  return remapped
}

export function buildAgendaPayload(items: Array<{
  id?: string
  title_en: string
  title_ar: string
  start_at: string
  end_at: string
}>, fromLocalDateTime: (value: string) => string | null): {
  payload: Array<{
    id?: string
    title_en: string
    title_ar: string
    start_at: string | null
    end_at: string | null
  }>
  formIndexByPayloadIndex: number[]
} {
  const formIndexByPayloadIndex: number[] = []
  const payload = items.flatMap((row, formIndex) => {
    if (row.title_en.trim() === '' || row.title_ar.trim() === '' || row.start_at.trim() === '') {
      return []
    }

    formIndexByPayloadIndex.push(formIndex)

    return [{
      id: row.id,
      title_en: row.title_en.trim(),
      title_ar: row.title_ar.trim(),
      start_at: fromLocalDateTime(row.start_at),
      end_at: fromLocalDateTime(row.end_at),
    }]
  })

  return { payload, formIndexByPayloadIndex }
}

export const EVENT_SETUP_FIELD_LABELS: FieldLabelMap = {
  slug: { en: 'Slug', ar: 'المعرّف' },
  timezone: { en: 'Timezone', ar: 'المنطقة الزمنية' },
  tier: { en: 'Tier', ar: 'المستوى' },
  organizer_user_id: { en: 'Organizer', ar: 'المنظم' },
  'name.en': { en: 'Name (EN)', ar: 'الاسم (EN)' },
  'name.ar': { en: 'Name (AR)', ar: 'الاسم (AR)' },
  capacity: { en: 'Capacity', ar: 'السعة' },
  brand_reference: { en: 'Brand reference', ar: 'مرجع العلامة' },
  domain_reference: { en: 'Domain reference', ar: 'مرجع النطاق' },
  'description.en': { en: 'Description (EN)', ar: 'الوصف (EN)' },
  'description.ar': { en: 'Description (AR)', ar: 'الوصف (AR)' },
  main_image: { en: 'Main image', ar: 'الصورة الرئيسية' },
  country_id: { en: 'Country', ar: 'الدولة' },
  city_id: { en: 'City', ar: 'المدينة' },
  location_address: { en: 'Address', ar: 'العنوان' },
  latitude: { en: 'Latitude', ar: 'خط العرض' },
  longitude: { en: 'Longitude', ar: 'خط الطول' },
  start_at: { en: 'Event starts', ar: 'بداية الفعالية' },
  end_at: { en: 'Event ends', ar: 'نهاية الفعالية' },
  registration_opens_at: { en: 'Registration opens', ar: 'فتح التسجيل' },
  registration_closes_at: { en: 'Registration closes', ar: 'إغلاق التسجيل' },
}

export const REGISTRATION_BUILDER_FIELD_LABELS: FieldLabelMap = {
  name: { en: 'Form name', ar: 'اسم النموذج' },
  privacy_notice_version: { en: 'Privacy notice version', ar: 'إصدار إشعار الخصوصية' },
  terms_version: { en: 'Terms version', ar: 'إصدار الشروط' },
  fields: { en: 'Fields', ar: 'الحقول' },
  label_en: { en: 'English label', ar: 'التسمية بالإنجليزية' },
  label_ar: { en: 'Arabic label', ar: 'التسمية بالعربية' },
  type: { en: 'Type', ar: 'النوع' },
  key: { en: 'Field key', ar: 'مفتاح الحقل' },
  options: { en: 'Options', ar: 'الخيارات' },
}

export function registrationFieldSelector(apiKey: string): string | null {
  return formFieldSelector(apiKey)
}

export function remapRegistrationApiErrors(
  errors: Record<string, string>,
  systemFieldCount: number,
): Record<string, string> {
  const remapped: Record<string, string> = {}

  for (const [key, message] of Object.entries(errors)) {
    const match = key.match(/^fields\.(\d+)\.(.+)$/)
    if (!match) {
      remapped[key] = message
      continue
    }

    const apiIndex = Number(match[1])
    const customIndex = apiIndex - systemFieldCount
    if (customIndex < 0) {
      remapped[key] = message
      continue
    }

    remapped[`fields.${customIndex}.${match[2]}`] = message
  }

  return remapped
}

export function formatRegistrationValidationMessage(
  apiKey: string,
  message: string,
  locale: Locale,
): string {
  return formatValidationFieldMessage(apiKey, message, locale, REGISTRATION_BUILDER_FIELD_LABELS)
}

export const ROLE_FIELD_LABELS: FieldLabelMap = {
  name_en: { en: 'Role name (EN)', ar: 'اسم الدور (EN)' },
  name_ar: { en: 'Role name (AR)', ar: 'اسم الدور (AR)' },
  description: { en: 'Description', ar: 'الوصف' },
  name: { en: 'Role name', ar: 'اسم الدور' },
  permissions: { en: 'Permissions', ar: 'الصلاحيات' },
}

export const USER_INVITE_FIELD_LABELS: FieldLabelMap = {
  name: { en: 'Name', ar: 'الاسم' },
  email: { en: 'Email', ar: 'البريد الإلكتروني' },
  password: { en: 'Password', ar: 'كلمة المرور' },
  preferred_locale: { en: 'Preferred locale', ar: 'اللغة المفضلة' },
}

export const LOGIN_FIELD_LABELS: FieldLabelMap = {
  email: { en: 'Email', ar: 'البريد الإلكتروني' },
  password: { en: 'Password', ar: 'كلمة المرور' },
}
