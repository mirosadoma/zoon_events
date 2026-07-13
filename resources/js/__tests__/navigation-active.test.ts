import { describe, expect, it } from 'vitest'
import { isNavItemActive } from '@/lib/navigation'

describe('isNavItemActive', () => {
  it('highlights scanner on scanner page', () => {
    expect(isNavItemActive('/en/tenant/events/1/scanner', '/tenant/events/1/scanner')).toBe(true)
  })

  it('does not highlight events list on event subpages', () => {
    expect(isNavItemActive('/en/tenant/events/1/scanner', '/tenant/events')).toBe(false)
  })

  it('highlights events list only on list and create pages', () => {
    expect(isNavItemActive('/en/tenant/events', '/tenant/events')).toBe(true)
    expect(isNavItemActive('/en/tenant/events/create', '/tenant/events')).toBe(true)
    expect(isNavItemActive('/en/tenant/events/1', '/tenant/events')).toBe(false)
  })

  it('highlights event detail only on exact event root', () => {
    expect(isNavItemActive('/en/tenant/events/1', '/tenant/events/1')).toBe(true)
    expect(isNavItemActive('/en/tenant/events/1/scanner', '/tenant/events/1')).toBe(false)
  })

  it('highlights nested routes under a section root', () => {
    expect(isNavItemActive('/en/tenant/events/1/acs/zones', '/tenant/events/1/acs')).toBe(true)
  })
})
