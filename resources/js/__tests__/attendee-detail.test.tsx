import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import AttendeeDetailPage from '@/pages/tenant/attendees/Detail'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

describe('attendee detail', () => {
  it('renders attendee profile with linked credential', () => {
    render(
      <AttendeeDetailPage
        event={{ id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }}
        attendee={{
          id: 'attendee_1',
          label: 'ndee_1',
          status: 'checked_in',
          locale: 'en',
          order_id: 'order_1',
          registered_at: '2026-07-01T10:00:00Z',
          credential: {
            id: 'cred_1',
            status: 'active',
            issued_at: '2026-07-01T10:05:00Z',
          },
        }}
      />,
    )

    expect(screen.getByRole('heading', { name: 'ndee_1' })).toBeInTheDocument()
    expect(screen.getByText('Attendee profile')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'cred_1' })).toHaveAttribute('href', '/tenant/events/evt_1/credentials/cred_1')
  })
})
