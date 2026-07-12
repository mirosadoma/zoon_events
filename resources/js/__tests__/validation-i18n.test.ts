import { describe, expect, it } from 'vitest'
import { formatValidationFieldMessage } from '@/lib/formatValidationErrors'
import { translateValidationMessage } from '@/lib/translateValidationMessage'

describe('translateValidationMessage', () => {
  it('translates common Laravel rules to Arabic', () => {
    expect(
      translateValidationMessage('The venues.1.latitude field must be between -90 and 90.', 'ar'),
    ).toBe('يجب أن يكون بين -90 و 90.')

    expect(
      translateValidationMessage('The slug field is required.', 'ar'),
    ).toBe('مطلوب.')

    expect(
      translateValidationMessage('The email field must be a valid email address.', 'ar'),
    ).toBe('يجب أن يكون بريداً إلكترونياً صالحاً.')

    expect(
      translateValidationMessage('must be a valid phone number.', 'ar'),
    ).toBe('يجب أن يكون رقم جوال صالحاً.')
  })

  it('translates custom agenda validation to Arabic', () => {
    expect(
      translateValidationMessage('End time must be after start time.', 'ar'),
    ).toBe('يجب أن يكون وقت الانتهاء بعد وقت البداية.')
  })

  it('translates registration form validation messages to Arabic', () => {
    expect(
      translateValidationMessage('Registration fields require Arabic and English labels.', 'ar'),
    ).toBe('تتطلب حقول التسجيل تسميات بالعربية والإنجليزية.')
  })

  it('keeps English messages in English locale', () => {
    expect(
      translateValidationMessage('The slug field is required.', 'en'),
    ).toBe('The slug field is required.')
  })
})

describe('formatValidationFieldMessage', () => {
  it('formats agenda end_at errors in Arabic', () => {
    expect(
      formatValidationFieldMessage(
        'items.0.end_at',
        'End time must be after start time.',
        'ar',
        {
          end_at: { en: 'Ends at', ar: 'ينتهي في' },
        },
      ),
    ).toBe('البند 1 · ينتهي في: يجب أن يكون وقت الانتهاء بعد وقت البداية.')
  })

  it('formats venue latitude errors in Arabic', () => {
    expect(
      formatValidationFieldMessage(
        'venues.1.latitude',
        'The venues.1.latitude field must be between -90 and 90.',
        'ar',
        {
          latitude: { en: 'Latitude', ar: 'خط العرض' },
        },
      ),
    ).toBe('الموقع 2 · خط العرض: يجب أن يكون بين -90 و 90.')
  })
})
