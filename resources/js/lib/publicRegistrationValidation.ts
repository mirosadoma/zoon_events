import type { PublicFormField } from '@/components/registration/RegistrationField'
import type { FieldLabelMap } from '@/lib/formatValidationErrors'
import { formFieldProps, formFieldSelector } from '@/lib/formatValidationErrors'
import { normalizeRegistrationPhone } from '@/lib/normalizeRegistrationPhone'

export function buildPublicRegistrationFieldLabels(
  fields: PublicFormField[],
  venueLabel: { en: string; ar: string },
  consentLabel: { en: string; ar: string },
  ticketLabel: { en: string; ar: string },
): FieldLabelMap {
  const labels: FieldLabelMap = {
    event_venue_id: venueLabel,
    consent: consentLabel,
    ticket_type: ticketLabel,
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

  if (normalized === 'ticket_type') {
    return '[data-form-field="ticket_type"]'
  }

  return formFieldSelector(normalized)
}

export function publicRegistrationFieldProps(key: string): { 'data-form-field': string } {
  return formFieldProps(normalizePublicRegistrationErrorKey(key))
}

function answerIsEmpty(value: string | boolean | string[] | undefined): boolean {
  if (value === undefined || value === false) {
    return true
  }

  if (Array.isArray(value)) {
    return value.length === 0
  }

  return String(value).trim() === ''
}

export function collectPublicRegistrationClientErrors(
  fields: PublicFormField[],
  answers: Record<string, string | boolean | string[]>,
  options: {
    ticketTypeId: string
    venueRequired: boolean
    venueId: string
    acceptedTerms: boolean
  },
): Record<string, string> {
  const errors: Record<string, string> = {}

  if (!options.ticketTypeId) {
    errors.ticket_type = 'is required.'
  }

  if (options.venueRequired && !options.venueId) {
    errors.event_venue_id = 'is required.'
  }

  if (!options.acceptedTerms) {
    errors.consent = 'You must accept the terms and privacy notice.'
  }

  for (const field of fields) {
    const value = answers[field.key]

    if (field.required && answerIsEmpty(value)) {
      errors[field.key] = 'is required.'
      continue
    }

    if (field.type === 'email' && typeof value === 'string' && value.trim() !== ''
      && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())) {
      errors[field.key] = 'must be a valid email address.'
      continue
    }

    if (field.type === 'phone' && typeof value === 'string' && value.trim() !== '') {
      const normalized = normalizeRegistrationPhone(value)
      if (!/^\+?[0-9]{8,15}$/.test(normalized)) {
        errors[field.key] = 'must be a valid phone number.'
      }
    }
  }

  return errors
}
