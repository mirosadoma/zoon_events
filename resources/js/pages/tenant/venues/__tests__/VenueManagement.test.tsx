import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import en from '@/locales/en'
import VenuesIndex from '@/pages/tenant/venues/Index'
import VenueShow from '@/pages/tenant/venues/Show'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { get: vi.fn() },
  usePage: () => ({ props: { locale: 'en', direction: 'ltr', can: { 'venue.manage': true } } }),
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

describe('VenueManagement', () => {
  it('renders venue index empty state with create action', () => {
    render(<VenuesIndex venues={[]} />)
    expect(screen.getByRole('heading', { name: en.venueManagement })).toBeInTheDocument()
    expect(screen.getByText(en.noVenues)).toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: en.createVenue })[0]).toHaveAttribute('href', '/en/tenant/venues/create')
  })

  it('renders venue list rows with status badges', () => {
    render(
      <VenuesIndex
        venues={[{
          id: 'ven_1',
          name: { en: 'Hall A', ar: 'قاعة أ' },
          city_code: 'RUH',
          country_code: 'SA',
          status: 'active',
          active_asset_count: 2,
          published_asset_count: 1,
        }]}
      />,
    )
    expect(screen.getByRole('link', { name: 'Hall A' })).toBeInTheDocument()
    expect(screen.getByText('Active')).toBeInTheDocument()
  })

  it('renders venue detail empty state when venue is missing', () => {
    render(<VenueShow venue={null} />)
    expect(screen.getByText(en.noVenues)).toBeInTheDocument()
  })

  it('renders venue detail with assets empty state', () => {
    render(
      <VenueShow
        venue={{
          id: 'ven_1',
          name: { en: 'Hall A', ar: 'قاعة أ' },
          status: 'draft',
          assets: [],
        }}
      />,
    )
    expect(screen.getByRole('heading', { name: 'Hall A' })).toBeInTheDocument()
    expect(screen.getByText(en.noAssets)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: en.saveVenue })).toBeInTheDocument()
  })
})
