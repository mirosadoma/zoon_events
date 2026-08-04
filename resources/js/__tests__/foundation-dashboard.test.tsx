import { render, screen } from '@testing-library/react'
import type { PropsWithChildren } from 'react'
import { usePage } from '@inertiajs/react'
import FoundationDashboard from '@/pages/FoundationDashboard'

vi.mock('@inertiajs/react', () => ({
  usePage: vi.fn(),
}))

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: PropsWithChildren) => <div>{children}</div>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr', t: (key: string) => key }),
}))

vi.mock('@/hooks/useLocalizedRouter', () => ({
  useLocalizedRouter: () => ({ visit: vi.fn() }),
}))

vi.mock('@/components/dashboard/PublishedVenuesMap', () => ({
  default: () => <div>Published venues map</div>,
}))

vi.mock('@/components/routing/LocalizedLink', () => ({
  default: ({ href, children }: PropsWithChildren<{ href: string }>) => <a href={href}>{children}</a>,
}))

describe('FoundationDashboard', () => {
  beforeEach(() => {
    vi.mocked(usePage).mockReturnValue({
      props: { auth: { user: { name: 'Demo User' } } },
    } as unknown as ReturnType<typeof usePage>)
  })

  it('renders overview metrics when data is provided', () => {
    render(
      <FoundationDashboard
        overview={{
          events_total: 2,
          events_published: 1,
          attendees_total: 10,
          orders_total: 5,
          credentials_issued: 8,
          checkins_today: 3,
          kiosks_active: 1,
          gates_active: 0,
          scans_failed: 0,
          recent_audit_events: [],
          registrations_by_day: Array.from({ length: 14 }, (_, i) => ({ date: `2026-08-${String(i + 1).padStart(2, '0')}`, count: i === 0 ? 2 : 0 })),
          checkins_by_day: Array.from({ length: 14 }, (_, i) => ({ date: `2026-08-${String(i + 1).padStart(2, '0')}`, count: 0 })),
          funnel: { registered: 10, paid: 5, credentialed: 8, checked_in: 3 },
          events_comparison: [
            {
              id: 'evt_1',
              name: { en: 'Summit', ar: 'القمة' },
              status: 'published',
              attendees: 10,
              checked_in: 3,
              checkin_rate: 30,
              revenue_minor: 150000,
              currency: 'EGP',
            },
          ],
          published_venue_markers: [],
        }}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Dashboard overview' })).toBeInTheDocument()
    expect(screen.getByText('Hello, Demo User')).toBeInTheDocument()
    expect(screen.getByText('Published venues map')).toBeInTheDocument()
    expect(screen.getByText('Activity trends')).toBeInTheDocument()
    expect(screen.getByText('Tenant conversion funnel')).toBeInTheDocument()
    expect(screen.getByText('Events comparison')).toBeInTheDocument()
    expect(screen.getByText('Summit')).toBeInTheDocument()
  })
})
