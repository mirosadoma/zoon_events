import { describe, expect, it } from 'vitest'
import {
  collectPublicRegistrationClientErrors,
  publicRegistrationFieldSelector,
  remapPublicRegistrationApiErrors,
} from '@/lib/publicRegistrationValidation'

describe('public registration validation', () => {
  it('maps api answer keys to form field selectors', () => {
    expect(publicRegistrationFieldSelector('answers.phone')).toBe('[data-form-field="phone"]')
    expect(publicRegistrationFieldSelector('consents.terms')).toBe('[data-form-field="consent"]')
    expect(publicRegistrationFieldSelector('event_venue_id')).toBe('[data-form-field="event_venue_id"]')
  })

  it('remaps nested api errors to public field keys', () => {
    expect(remapPublicRegistrationApiErrors({
      'answers.company': 'is required.',
      'consents.terms': 'You must accept the terms and privacy notice.',
    })).toEqual({
      company: 'is required.',
      consent: 'You must accept the terms and privacy notice.',
    })
  })

  it('collects client-side errors for consent, venue, and required fields', () => {
    const errors = collectPublicRegistrationClientErrors(
      [
        {
          key: 'full_name',
          type: 'text',
          label_en: 'Full name',
          label_ar: 'الاسم',
          required: true,
        },
        {
          key: 'email',
          type: 'email',
          label_en: 'Email',
          label_ar: 'البريد',
          required: true,
        },
      ],
      { full_name: 'Amr Sadoma' },
      {
        ticketTypeId: '1',
        venueRequired: true,
        venueId: '',
        acceptedTerms: false,
      },
    )

    expect(errors).toMatchObject({
      event_venue_id: 'is required.',
      consent: 'You must accept the terms and privacy notice.',
      email: 'is required.',
    })
  })
})
