import type { Locale } from '@/hooks/useLocale'
import { lookupValidationCatalog } from '@/lib/validationMessageCatalog'

function stripLaravelFieldPrefix(message: string): string {
  return message
    .replace(/^The .+? field /i, '')
    .replace(/^The selected .+? is /i, 'The selected value is ')
    .replace(/^The .+? must /i, 'Must ')
    .replace(/\.+$/, '')
    .trim()
}

type RulePattern = {
  match: RegExp
  translate: (groups: RegExpMatchArray, locale: Locale) => string
}

const RULE_PATTERNS: RulePattern[] = [
  {
    match: /^is required\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'مطلوب.' : 'is required.'),
  },
  {
    match: /^must be a string\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب أن يكون نصاً.' : 'must be a string.'),
  },
  {
    match: /^must be an integer\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب أن يكون رقماً صحيحاً.' : 'must be an integer.'),
  },
  {
    match: /^must be a number\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب أن يكون رقماً.' : 'must be a number.'),
  },
  {
    match: /^must be a valid email address\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب أن يكون بريداً إلكترونياً صالحاً.' : 'must be a valid email address.'),
  },
  {
    match: /^must be a valid URL\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب أن يكون رابطاً صالحاً.' : 'must be a valid URL.'),
  },
  {
    match: /^must be a valid date\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب أن يكون تاريخاً صالحاً.' : 'must be a valid date.'),
  },
  {
    match: /^must be a date after (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن يكون تاريخاً بعد ${g[1]}.` : `must be a date after ${g[1]}.`),
  },
  {
    match: /^must be a date before (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن يكون تاريخاً قبل ${g[1]}.` : `must be a date before ${g[1]}.`),
  },
  {
    match: /^must be a date after or equal to (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن يكون تاريخاً في ${g[1]} أو بعده.` : `must be a date after or equal to ${g[1]}.`),
  },
  {
    match: /^must be a date before or equal to (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن يكون تاريخاً في ${g[1]} أو قبله.` : `must be a date before or equal to ${g[1]}.`),
  },
  {
    match: /^must be after (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن يكون بعد ${g[1]}.` : `must be after ${g[1]}.`),
  },
  {
    match: /^must be before (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن يكون قبل ${g[1]}.` : `must be before ${g[1]}.`),
  },
  {
    match: /^must be between ([\d.-]+) and ([\d.-]+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن يكون بين ${g[1]} و ${g[2]}.` : `must be between ${g[1]} and ${g[2]}.`),
  },
  {
    match: /^must be at least ([\d.-]+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن لا يقل عن ${g[1]}.` : `must be at least ${g[1]}.`),
  },
  {
    match: /^must not be greater than ([\d.-]+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن لا يزيد عن ${g[1]}.` : `must not be greater than ${g[1]}.`),
  },
  {
    match: /^must be greater than ([\d.-]+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن يكون أكبر من ${g[1]}.` : `must be greater than ${g[1]}.`),
  },
  {
    match: /^must be less than ([\d.-]+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن يكون أقل من ${g[1]}.` : `must be less than ${g[1]}.`),
  },
  {
    match: /^must have at least (\d+) characters\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن يحتوي على ${g[1]} أحرف على الأقل.` : `must have at least ${g[1]} characters.`),
  },
  {
    match: /^must not be greater than (\d+) characters\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب ألا يزيد عن ${g[1]} حرفاً.` : `must not be greater than ${g[1]} characters.`),
  },
  {
    match: /^has already been taken\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'مُستخدم بالفعل.' : 'has already been taken.'),
  },
  {
    match: /^The selected value is invalid\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'القيمة المختارة غير صالحة.' : 'The selected value is invalid.'),
  },
  {
    match: /^must match the format (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن يطابق الصيغة ${g[1]}.` : `must match the format ${g[1]}.`),
  },
  {
    match: /^must be accepted\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب الموافقة.' : 'must be accepted.'),
  },
  {
    match: /^must be true or false\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب أن تكون القيمة صحيحة أو خاطئة.' : 'must be true or false.'),
  },
  {
    match: /^must be an array\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب أن يكون قائمة.' : 'must be an array.'),
  },
  {
    match: /^must be a file\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب أن يكون ملفاً.' : 'must be a file.'),
  },
  {
    match: /^must be an image\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب أن تكون صورة.' : 'must be an image.'),
  },
  {
    match: /^must be a file of type: (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن يكون ملفاً من النوع: ${g[1]}.` : `must be a file of type: ${g[1]}.`),
  },
  {
    match: /^must not be greater than (\d+) kilobytes\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب ألا يزيد حجمه عن ${g[1]} كيلوبايت.` : `must not be greater than ${g[1]} kilobytes.`),
  },
  {
    match: /^must be a valid phone number\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب أن يكون رقم جوال صالحاً.' : 'must be a valid phone number.'),
  },
  {
    match: /^is invalid\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'غير صالح.' : 'is invalid.'),
  },
  {
    match: /^exceeds its maximum length\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يتجاوز الحد الأقصى للطول.' : 'exceeds its maximum length.'),
  },
  {
    match: /^is outside its allowed range\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'خارج النطاق المسموح.' : 'is outside its allowed range.'),
  },
  {
    match: /^This field is not accepted\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'هذا الحقل غير مقبول.' : 'This field is not accepted.'),
  },
  {
    match: /^You must accept the terms and privacy notice\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب الموافقة على الشروط وسياسة الخصوصية.' : 'You must accept the terms and privacy notice.'),
  },
  {
    match: /^End time must be after start time\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'يجب أن يكون وقت الانتهاء بعد وقت البداية.' : 'End time must be after start time.'),
  },
  {
    match: /^The password field confirmation does not match\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? 'تأكيد كلمة المرور غير متطابق.' : 'The password field confirmation does not match.'),
  },
  {
    match: /^The password must be at least (\d+) characters\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? `يجب أن تحتوي كلمة المرور على ${g[1]} أحرف على الأقل.` : `The password must be at least ${g[1]} characters.`),
  },
]

export function translateValidationMessage(message: string, locale: Locale): string {
  const trimmed = message.trim()
  if (trimmed === '') {
    return trimmed
  }

  if (locale === 'en') {
    return trimmed
  }

  const catalogMatch = lookupValidationCatalog(trimmed)
  if (catalogMatch !== null) {
    return catalogMatch
  }

  const normalized = stripLaravelFieldPrefix(trimmed)

  const catalogNormalized = lookupValidationCatalog(normalized)
  if (catalogNormalized !== null) {
    return catalogNormalized
  }

  for (const pattern of RULE_PATTERNS) {
    const match = normalized.match(pattern.match)
    if (match) {
      return pattern.translate(match, locale)
    }
  }

  for (const pattern of RULE_PATTERNS) {
    const match = trimmed.match(pattern.match)
    if (match) {
      return pattern.translate(match, locale)
    }
  }

  return normalized
}
