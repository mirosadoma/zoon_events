import { render, screen } from '@testing-library/react'
import type { PropsWithChildren } from 'react'
import FoundationDashboard from '@/pages/FoundationDashboard'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: PropsWithChildren) => <div>{children}</div>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

describe('FoundationDashboard', () => {
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
        }}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Dashboard overview' })).toBeInTheDocument()
    expect(screen.getByText('2')).toBeInTheDocument()
    expect(screen.getByText('10')).toBeInTheDocument()
  })
})
