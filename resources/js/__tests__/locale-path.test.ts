import { describe, expect, it } from 'vitest'
import { localizedPath } from '@/lib/localePath'

describe('localizedPath', () => {
  it('preserves query strings when localizing paths', () => {
    expect(localizedPath('en', '/dashboard/search?q=Zon'))
      .toBe('/en/dashboard/search?q=Zon')
  })

  it('preserves existing locale prefixes and query strings', () => {
    expect(localizedPath('ar', '/en/dashboard/search?q=Summit'))
      .toBe('/ar/dashboard/search?q=Summit')
  })
})
