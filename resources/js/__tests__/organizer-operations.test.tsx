import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import axe from 'axe-core'
import Orders from '@/pages/tenant/events/Orders'
import Attendees from '@/pages/tenant/events/Attendees'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

const event = { id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }

describe('organizer operations', () => {
  it('renders minimized order and delivery state without buyer PII', () => {
    render(
      <Orders
        event={event}
        orders={[{ id: '1', reference: 'ord_safe', status: 'paid', total: '500 SAR', notification_status: 'pending' }]}
      />,
    )
    expect(screen.getByText('ord_safe')).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('Queued')
    expect(screen.queryByText(/@/)).not.toBeInTheDocument()
  })

  it('renders minimized attendee state', () => {
    render(
      <Attendees
        event={event}
        attendees={[{ id: 'attendee-safe', label: 'dee-safe', status: 'registered', locale: 'ar' }]}
      />,
    )
    expect(screen.getByText('dee-safe')).toBeInTheDocument()
    expect(screen.queryByText(/email/i)).not.toBeInTheDocument()
  })

  it('renders equivalent Arabic RTL operations without serious accessibility violations', async () => {
    vi.doMock('@/hooks/useLocale', () => ({
      useLocale: () => ({ locale: 'ar', direction: 'rtl' }),
    }))

    const { container } = render(
      <>
        <Orders
          event={event}
          orders={[{ id: '1', reference: 'ord_safe', status: 'paid', total: '500 SAR', notification_status: 'delivered' }]}
        />
        <Attendees
          event={event}
          attendees={[{ id: 'attendee-safe', label: 'dee-safe', status: 'registered', locale: 'ar' }]}
        />
      </>,
    )
    expect(screen.getByRole('heading', { name: 'Orders' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Attendees' })).toBeInTheDocument()
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
  })
})
