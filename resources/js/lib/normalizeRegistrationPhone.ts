export function normalizeRegistrationPhone(value: string): string {
  const trimmed = value.trim()
  if (trimmed === '') {
    return ''
  }

  const digits = trimmed.replace(/\D+/g, '')
  if (digits === '') {
    return ''
  }

  if (digits.startsWith('00')) {
    return `+${digits.slice(2)}`
  }

  if (trimmed.startsWith('+')) {
    return `+${digits}`
  }

  return digits
}
