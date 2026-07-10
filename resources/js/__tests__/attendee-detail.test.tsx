import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import AttendeeDetailPage from '@/pages/tenant/attendees/Detail'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { reload: vi.fn(), visit: vi.fn() },
  usePage: () => ({ props: { session: { tenant: { id: 'ten_1' } } } }),
}))

vi.mock('@/lib/apiFetch', () => ({
  apiFetch: vi.fn(),
  ApiFetchError: class ApiFetchError extends Error {
    status = 500
    constructor(message: string) {
      super(message)
    }
  },
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr', t: (key: string) => key }),
}))

vi.mock('@/hooks/useToast', () => ({
  useToast: () => ({ toast: vi.fn() }),
}))

vi.mock('@/components/layout/PermissionGate', () => ({
  default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

describe('attendee detail', () => {
  it('renders attendee profile with linked credential', () => {
    render(
      <AttendeeDetailPage
        tenantId="ten_1"
        event={{ id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }}
        attendee={{
          id: 'attendee_1',
          label: 'ndee_1',
          status: 'checked_in',
          locale: 'en',
          order_id: 'order_1',
          registered_at: '2026-07-01T10:00:00Z',
          credential: {
            id: '42',
            status: 'active',
            issued_at: '2026-07-01T10:05:00Z',
          },
        }}
      />,
    )

    expect(screen.getByRole('heading', { name: 'ndee_1' })).toBeInTheDocument()
    expect(screen.getByText('Attendee profile')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: '42' })).toHaveAttribute('href', '/en/tenant/events/evt_1/credentials/42')
    expect(screen.getByRole('button', { name: 'Print badge' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Manual check-in' })).toBeInTheDocument()
  })
})
