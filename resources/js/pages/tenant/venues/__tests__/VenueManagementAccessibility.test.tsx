import { render, screen } from '@testing-library/react'
import axe from 'axe-core'
import { describe, expect, it, vi } from 'vitest'
import ar from '@/locales/ar'
import en from '@/locales/en'
import VenuesIndex from '@/pages/tenant/venues/Index'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <main>{children}</main>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { get: vi.fn() },
  usePage: () => ({ props: { locale: 'en', direction: 'ltr', can: {} } }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => (en as unknown as Record<string, string>)[key] ?? key,
    localizedPath: (path: string) => `/en${path}`,
  }),
}))

vi.mock('@/hooks/useLocalizedRouter', () => ({
  useLocalizedRouter: () => ({ get: vi.fn() }),
}))

vi.mock('@/components/layout/PermissionGate', () => ({
  default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

describe('VenueManagementAccessibility', () => {
  it('renders venue index without serious axe violations', async () => {
    const { container } = render(<VenuesIndex venues={[]} />)
    expect(screen.getByRole('heading', { name: en.venueManagement })).toBeInTheDocument()
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
  })

  it('keeps Arabic locale keys available for venue management', () => {
    expect(ar.venueManagement).not.toBe(en.venueManagement)
    expect(ar.noVenues).not.toBe(en.noVenues)
    expect(Object.keys(ar.statusLabels)).toEqual(Object.keys(en.statusLabels))
  })
})
