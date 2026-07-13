/** Extract a signed credential token from raw QR text or URLs. */
export function normalizeScanPayload(raw: string): string {
  const trimmed = raw.trim()

  if (trimmed === '') {
    return ''
  }

  const tokenMatch = trimmed.match(/zt1\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/)

  return tokenMatch?.[0] ?? trimmed
}
