import { describe, expect, it } from 'vitest'
import { isValidRegistrationPhone, normalizeRegistrationPhone } from '@/lib/normalizeRegistrationPhone'

describe('normalizeRegistrationPhone', () => {
  it('keeps local saudi mobile numbers', () => {
    expect(normalizeRegistrationPhone('0512312312')).toBe('0512312312')
    expect(isValidRegistrationPhone('0512312312')).toBe(true)
  })

  it('strips spaces and punctuation', () => {
    expect(normalizeRegistrationPhone('(05) 123-12312')).toBe('0512312312')
  })

  it('converts saudi international prefix to local', () => {
    expect(normalizeRegistrationPhone('+966 51 231 2312')).toBe('0512312312')
    expect(isValidRegistrationPhone('+966512312312')).toBe(true)
  })

  it('rejects invalid saudi local numbers', () => {
    expect(isValidRegistrationPhone('01276069689')).toBe(false)
    expect(isValidRegistrationPhone('05123')).toBe(false)
    expect(isValidRegistrationPhone('0612312312')).toBe(false)
  })
})
