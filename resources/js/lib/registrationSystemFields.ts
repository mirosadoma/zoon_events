export const REGISTRATION_SYSTEM_FIELD_KEYS = ['full_name', 'email', 'phone'] as const

export type RegistrationSystemFieldKey = typeof REGISTRATION_SYSTEM_FIELD_KEYS[number]

export type RegistrationSystemFieldRow = {
  key: RegistrationSystemFieldKey
  type: string
  label_en: string
  label_ar: string
  required: true
  system: true
}

export const REGISTRATION_SYSTEM_FIELDS: RegistrationSystemFieldRow[] = [
  {
    key: 'full_name',
    type: 'text',
    label_en: 'Full name',
    label_ar: 'الاسم الكامل',
    required: true,
    system: true,
  },
  {
    key: 'email',
    type: 'email',
    label_en: 'Email',
    label_ar: 'البريد الإلكتروني',
    required: true,
    system: true,
  },
  {
    key: 'phone',
    type: 'phone',
    label_en: 'Phone number',
    label_ar: 'رقم الجوال',
    required: true,
    system: true,
  },
]

export function isRegistrationSystemFieldKey(key: string): key is RegistrationSystemFieldKey {
  return REGISTRATION_SYSTEM_FIELD_KEYS.includes(key as RegistrationSystemFieldKey)
}

export function splitRegistrationFields<T extends { key: string }>(fields: T[]): {
  systemFields: RegistrationSystemFieldRow[]
  customFields: T[]
} {
  return {
    systemFields: REGISTRATION_SYSTEM_FIELDS,
    customFields: fields.filter((field) => !isRegistrationSystemFieldKey(field.key)),
  }
}

export const ADDABLE_REGISTRATION_FIELD_TYPES = [
  'text',
  'select',
  'number',
  'date',
  'multi_select',
  'radio',
  'checkbox',
  'consent',
  'hidden',
] as const

export const SYSTEM_FIELD_TYPE_LABELS: Record<string, { en: string; ar: string }> = {
  text: { en: 'Text', ar: 'نص' },
  email: { en: 'Email', ar: 'بريد' },
  phone: { en: 'Phone', ar: 'هاتف' },
}

export const ADDABLE_FIELD_TYPE_LABELS: Record<string, { en: string; ar: string }> = {
  text: { en: 'Text', ar: 'نص' },
  select: { en: 'Select', ar: 'قائمة' },
  number: { en: 'Number', ar: 'رقم' },
  date: { en: 'Date', ar: 'تاريخ' },
  multi_select: { en: 'Multi select', ar: 'اختيار متعدد' },
  radio: { en: 'Radio', ar: 'اختيار واحد' },
  checkbox: { en: 'Checkbox', ar: 'مربع اختيار' },
  consent: { en: 'Consent', ar: 'موافقة' },
  hidden: { en: 'Hidden', ar: 'مخفي' },
}
