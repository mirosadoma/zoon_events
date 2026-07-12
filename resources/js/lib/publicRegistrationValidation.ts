import type { PublicFormField } from '@/components/registration/RegistrationField'
import type { FieldLabelMap } from '@/lib/formatValidationErrors'
import { formFieldProps, formFieldSelector } from '@/lib/formatValidationErrors'

export function buildPublicRegistrationFieldLabels(
  fields: PublicFormField[],
  venueLabel: { en: string; ar: string },
  consentLabel: { en: string; ar: string },
): FieldLabelMap {
  const labels: FieldLabelMap = {
    event_venue_id: venueLabel,
    consent: consentLabel,
  }

  for (const field of fields) {
    labels[field.key] = { en: field.label_en, ar: field.label_ar }
  }

  return labels
}

export function normalizePublicRegistrationErrorKey(key: string): string {
  if (key.startsWith('answers.')) {
    return key.slice('answers.'.length)
  }

  if (key.startsWith('buyer.') || key.startsWith('attendee.')) {
    const field = key.split('.').pop() ?? key

    return field === 'first_name' || field === 'last_name' ? 'full_name' : field
  }

  if (key.startsWith('consents.')) {
    return 'consent'
  }

  return key
}

export function remapPublicRegistrationApiErrors(errors: Record<string, string>): Record<string, string> {
  const remapped: Record<string, string> = {}

  for (const [key, message] of Object.entries(errors)) {
    remapped[normalizePublicRegistrationErrorKey(key)] = message
  }

  return remapped
}

export function publicRegistrationFieldSelector(apiKey: string): string | null {
  const normalized = normalizePublicRegistrationErrorKey(apiKey)

  if (normalized === 'consent') {
    return '[data-form-field="consent"]'
  }

  return formFieldSelector(normalized)
}

export function publicRegistrationFieldProps(key: string): { 'data-form-field': string } {
  return formFieldProps(normalizePublicRegistrationErrorKey(key))
}
