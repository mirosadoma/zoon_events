import type { Locale } from '@/hooks/useLocale'
import { lookupValidationCatalog } from '@/lib/validationMessageCatalog'
import en from '@/locales/en'
import ar from '@/locales/ar'

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
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationIsRequired,
  },
  {
    match: /^must be a string\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationMustBeString,
  },
  {
    match: /^must be an integer\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationMustBeInteger,
  },
  {
    match: /^must be a number\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationMustBeNumber,
  },
  {
    match: /^must be a valid email address\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationMustBeValidEmailAddress,
  },
  {
    match: /^must be a valid URL\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationMustBeValidUrl,
  },
  {
    match: /^must be a valid date\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationMustBeValidDate,
  },
  {
    match: /^must be a date after (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustBeDateAfter.replace(':value', g[1]),
  },
  {
    match: /^must be a date before (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustBeDateBefore.replace(':value', g[1]),
  },
  {
    match: /^must be a date after or equal to (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustBeDateAfterOrEqual.replace(':value', g[1]),
  },
  {
    match: /^must be a date before or equal to (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustBeDateBeforeOrEqual.replace(':value', g[1]),
  },
  {
    match: /^must be after (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustBeAfter.replace(':value', g[1]),
  },
  {
    match: /^must be before (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustBeBefore.replace(':value', g[1]),
  },
  {
    match: /^must be between ([\d.-]+) and ([\d.-]+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustBeBetween
      .replace(':min', g[1])
      .replace(':max', g[2]),
  },
  {
    match: /^must be at least ([\d.-]+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustBeAtLeast.replace(':value', g[1]),
  },
  {
    match: /^must not be greater than ([\d.-]+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustNotBeGreaterThan.replace(':value', g[1]),
  },
  {
    match: /^must be greater than ([\d.-]+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustBeGreaterThan.replace(':value', g[1]),
  },
  {
    match: /^must be less than ([\d.-]+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustBeLessThan.replace(':value', g[1]),
  },
  {
    match: /^must have at least (\d+) characters\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustHaveAtLeastCharacters.replace(':count', g[1]),
  },
  {
    match: /^must not be greater than (\d+) characters\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustNotBeGreaterThanCharacters.replace(':count', g[1]),
  },
  {
    match: /^has already been taken\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationHasAlreadyBeenTaken,
  },
  {
    match: /^The selected value is invalid\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationSelectedValueIsInvalid,
  },
  {
    match: /^must match the format (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustMatchFormat.replace(':format', g[1]),
  },
  {
    match: /^must be accepted\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationMustBeAccepted,
  },
  {
    match: /^must be true or false\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationMustBeTrueOrFalse,
  },
  {
    match: /^must be an array\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationMustBeArray,
  },
  {
    match: /^must be a file\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationMustBeFile,
  },
  {
    match: /^must be an image\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationMustBeImage,
  },
  {
    match: /^must be a file of type: (.+)\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustBeFileOfType.replace(':type', g[1]),
  },
  {
    match: /^must not be greater than (\d+) kilobytes\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationMustNotBeGreaterThanKilobytes.replace(':count', g[1]),
  },
  {
    match: /^must be a valid phone number\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationMustBeValidPhoneNumber,
  },
  {
    match: /^is invalid\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationIsInvalid,
  },
  {
    match: /^exceeds its maximum length\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationExceedsMaximumLength,
  },
  {
    match: /^is outside its allowed range\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationIsOutsideAllowedRange,
  },
  {
    match: /^This field is not accepted\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationFieldIsNotAccepted,
  },
  {
    match: /^You must accept the terms and privacy notice\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationMustAcceptTermsAndPrivacyNotice,
  },
  {
    match: /^End time must be after start time\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationEndTimeMustBeAfterStartTime,
  },
  {
    match: /^The password field confirmation does not match\.?$/i,
    translate: (_g, locale) => (locale === 'ar' ? ar : en).validationPasswordConfirmationDoesNotMatch,
  },
  {
    match: /^The password must be at least (\d+) characters\.?$/i,
    translate: (g, locale) => (locale === 'ar' ? ar : en).validationPasswordMustBeAtLeastCharacters.replace(':count', g[1]),
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
