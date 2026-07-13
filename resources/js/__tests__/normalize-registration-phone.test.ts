import { describe, expect, it } from 'vitest'
import { normalizeRegistrationPhone } from '@/lib/normalizeRegistrationPhone'

describe('normalizeRegistrationPhone', () => {
  it('keeps local egyptian mobile numbers', () => {
    expect(normalizeRegistrationPhone('01276069689')).toBe('01276069689')
  })

  it('strips spaces and punctuation', () => {
    expect(normalizeRegistrationPhone('(012) 760-69689')).toBe('01276069689')
  })
})
