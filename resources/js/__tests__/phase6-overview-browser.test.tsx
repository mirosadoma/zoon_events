import { render, screen } from '@testing-library/react'
import axe from 'axe-core'
import { describe, expect, it, vi } from 'vitest'
import FoundationDashboard from '@/pages/FoundationDashboard'
import Profile from '@/pages/Profile'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

const overview = {
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
}

describe('phase 6 overview browser journey', () => {
  it('renders overview metrics in English LTR without serious axe violations', async () => {
    const { container } = render(<FoundationDashboard overview={overview} />)

    expect(screen.getByRole('heading', { name: 'Dashboard overview' })).toBeInTheDocument()
    expect(screen.getByText('2')).toBeInTheDocument()

    const results = await axe.run(container)
    expect(results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')).toHaveLength(0)
  })

  it('renders profile page with account fields', () => {
    render(
      <Profile
        profile={{
          name: 'Amina',
          email: 'amina@example.com',
          role: 'Organizer',
          tenant: { id: 'ten_1', name: 'Demo Tenant', slug: 'demo' },
          last_login_at: '2026-07-07T10:00:00Z',
        }}
      />,
    )

    expect(screen.getByText('amina@example.com')).toBeInTheDocument()
    expect(screen.getByText('Demo Tenant')).toBeInTheDocument()
  })
})
