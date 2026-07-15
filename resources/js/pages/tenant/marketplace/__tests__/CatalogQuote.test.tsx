import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import en from '@/locales/en'
import MarketplaceIndex from '@/pages/tenant/marketplace/Index'
import CatalogFilters from '@/pages/tenant/marketplace/Components/CatalogFilters'
import { emptyCatalogFilters } from '@/pages/tenant/marketplace/Components/CatalogFilters'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
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

describe('CatalogQuote', () => {
  it('renders catalog empty state and filters', () => {
    render(<MarketplaceIndex assets={[]} />)
    expect(screen.getByRole('heading', { name: en.catalogTitle })).toBeInTheDocument()
    expect(screen.getByText(en.noCatalogAssets)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: en.applyFilters })).toBeInTheDocument()
  })

  it('renders catalog filters with asset type control', () => {
    render(
      <CatalogFilters
        values={emptyCatalogFilters()}
        onChange={() => undefined}
        onApply={() => undefined}
        onClear={() => undefined}
      />,
    )
    expect(screen.getByLabelText(en.filterAssetType)).toBeInTheDocument()
    expect(screen.getByLabelText(en.filterStart)).toBeInTheDocument()
  })

  it('renders quote panel when assets exist', () => {
    render(
      <MarketplaceIndex
        assets={[{
          id: 'pub_1',
          publication_id: 'pub_1',
          venue_id: 'ven_1',
          venue_name: { en: 'Hall A', ar: 'قاعة أ' },
          asset_type: 'room',
          name: { en: 'Room 1', ar: 'غرفة 1' },
          capabilities: ['wifi'],
          pricing_model: 'per_hour',
          price_minor: 10000,
          currency: 'SAR',
        }]}
      />,
    )
    expect(screen.getByText('Room 1')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: en.submitRentalRequest })).toBeDisabled()
  })
})
