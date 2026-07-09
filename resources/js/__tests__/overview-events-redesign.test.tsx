import { render, screen } from '@testing-library/react'
import { usePage } from '@inertiajs/react'
import type { PropsWithChildren } from 'react'
import { describe, expect, it, vi } from 'vitest'
import FoundationDashboard from '@/pages/FoundationDashboard'

vi.mock('@inertiajs/react', () => ({
  usePage: vi.fn(),
}))

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: PropsWithChildren) => <div>{children}</div>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

const overview = {
  events_total: 4,
  events_published: 2,
  attendees_total: 120,
  orders_total: 80,
  credentials_issued: 95,
  checkins_today: 12,
  kiosks_active: 2,
  gates_active: 3,
  scans_failed: 1,
  recent_audit_events: [
    {
      id: '1',
      actor: 'Demo User',
      action: 'event.published',
      outcome: 'success',
      occurred_at: '2026-07-09T10:00:00Z',
    },
  ],
}

describe('overview events redesign', () => {
  beforeEach(() => {
    vi.mocked(usePage).mockReturnValue({
      props: { auth: { user: { name: 'Demo User' } } },
    } as unknown as ReturnType<typeof usePage>)
  })

  it('renders metric cards and audit timeline', () => {
    render(<FoundationDashboard overview={overview} />)

    expect(screen.getByText('120')).toBeInTheDocument()
    expect(screen.getByText('Recent audit activity')).toBeInTheDocument()
    expect(screen.getByText(/Hello, Demo User/)).toBeInTheDocument()
  })

  it('renders skeleton when overview missing', () => {
    render(<FoundationDashboard />)
    expect(screen.queryByText('Recent audit activity')).not.toBeInTheDocument()
  })
})
