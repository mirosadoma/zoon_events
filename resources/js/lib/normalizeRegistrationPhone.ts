/**
 * Public registration phones must be a local Saudi mobile:
 * exactly 10 digits starting with 05 (e.g. 0512312312).
 */
export function normalizeRegistrationPhone(value: string): string {
  const trimmed = value.trim()
  if (trimmed === '') {
    return ''
  }

  let digits = trimmed.replace(/\D+/g, '')
  if (digits === '') {
    return ''
  }

  if (digits.startsWith('00')) {
    digits = digits.slice(2)
  }

  if (digits.startsWith('9665') && digits.length === 12) {
    return `0${digits.slice(3)}`
  }

  return digits
}

export function isValidRegistrationPhone(value: string): boolean {
  return /^05[0-9]{8}$/.test(normalizeRegistrationPhone(value))
}
