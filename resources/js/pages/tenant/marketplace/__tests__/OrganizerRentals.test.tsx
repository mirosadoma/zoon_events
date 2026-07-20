import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import en from '@/locales/en'
import RentalsIndex from '@/pages/tenant/marketplace/Rentals/Index'
import RentalShow from '@/pages/tenant/marketplace/Rentals/Show'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { get: vi.fn(), reload: vi.fn() },
  usePage: () => ({ props: { locale: 'en', direction: 'ltr', can: { 'rentals.approve': true, 'marketplace.manage': true } } }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => (en as unknown as Record<string, string>)[key] ?? key,
    localizedPath: (path: string) => `/en${path}`,
  }),
}))

vi.mock('@/components/layout/PermissionGate', () => ({
  default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

describe('OrganizerRentals', () => {
  it('renders rentals empty state', () => {
    render(<RentalsIndex rentals={[]} />)
    expect(screen.getByRole('heading', { name: en.myRentals })).toBeInTheDocument()
    expect(screen.getByText(en.noRentals)).toBeInTheDocument()
  })

  it('renders rental list row with status badge', () => {
    render(
      <RentalsIndex
        rentals={[{
          id: 'rent_1',
          viewer_role: 'organizer',
          event_name: { en: 'Summit', ar: 'القمة' },
          venue_name: { en: 'Hall A', ar: 'قاعة أ' },
          window_start: '2026-07-01T08:00:00Z',
          window_end: '2026-07-01T18:00:00Z',
          currency: 'SAR',
          total_minor: 50000,
          status: 'requested',
        }]}
      />,
    )
    expect(screen.getByText('Summit')).toBeInTheDocument()
    expect(screen.getByText('Requested')).toBeInTheDocument()
  })

  it('renders rental detail timeline section', () => {
    render(
      <RentalShow
        rental={{
          id: 'rent_1',
          viewer_role: 'organizer',
          event_name: { en: 'Summit', ar: 'القمة' },
          venue_name: { en: 'Hall A', ar: 'قاعة أ' },
          window_start: '2026-07-01T08:00:00Z',
          window_end: '2026-07-01T18:00:00Z',
          currency: 'SAR',
          total_minor: 50000,
          status: 'approved',
          timeline: [{ id: 'evt_1', kind: 'submitted', occurred_at: '2026-07-01T07:00:00Z', summary: 'Submitted' }],
        }}
      />,
    )
    expect(screen.getByRole('heading', { name: en.rentalDetails })).toBeInTheDocument()
    expect(screen.getByText('Submitted')).toBeInTheDocument()
  })
})
