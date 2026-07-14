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
  router: {
    get: vi.fn(),
  },
  usePage: () => ({ props: { locale: 'en', direction: 'ltr' } }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => ({
      attendees: 'Attendees',
      orders: 'Orders',
      overview: 'Overview',
      search: 'Search',
      searchAttendee: 'Name, email, or phone',
      checkInStatus: 'Check-in status',
      allStatuses: 'All statuses',
      noAttendees: 'No attendees yet',
      noAttendeesDetail: 'Attendees will appear after registration.',
      notAvailable: 'Not available',
      attendeeName: 'Name',
      attendeeEmail: 'Email',
      attendeePhone: 'Phone',
      checkIn: 'Check-in',
      exportExcel: 'Export Excel',
      previousPage: 'Previous',
      nextPage: 'Next',
      pageOf: 'Page :page of :total',
      noOrders: 'No orders yet',
      noOrdersDetail: 'Orders will appear here after registration.',
      searchReference: 'Search reference',
      orderStatus: 'Order status',
    } as Record<string, string>)[key] ?? key,
    localizedPath: (path: string) => `/en${path}`,
  }),
}))

vi.mock('@/hooks/useLocalizedRouter', () => ({
  useLocalizedRouter: () => ({
    get: vi.fn(),
  }),
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
    expect(screen.getAllByText('ord_safe').length).toBeGreaterThan(0)
    expect(screen.getByRole('status')).toHaveTextContent('Queued')
    expect(screen.queryByText(/@/)).not.toBeInTheDocument()
  })

  it('renders minimized attendee state with export action', () => {
    render(
      <Attendees
        event={event}
        attendees={[{ id: 'attendee-safe', label: 'dee-safe', display_name: 'dee-safe', status: 'not_checked_in', locale: 'ar' }]}
        filters={{ search: '', status: '' }}
        pagination={{ page: 1, per_page: 25, total: 1, last_page: 1 }}
      />,
    )
    expect(screen.getByText('dee-safe')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Export Excel' })).toHaveAttribute(
      'href',
      '/en/tenant/events/evt_1/attendees/export',
    )
  })

  it('includes active filters in the Excel export link', () => {
    render(
      <Attendees
        event={event}
        attendees={[{ id: 'attendee-safe', label: 'dee-safe', display_name: 'dee-safe', status: 'checked_in', locale: 'ar' }]}
        filters={{ search: 'sara', status: 'checked_in' }}
        pagination={{ page: 2, per_page: 25, total: 30, last_page: 2 }}
      />,
    )

    const href = screen.getByRole('link', { name: 'Export Excel' }).getAttribute('href') ?? ''
    expect(href).toContain('/en/tenant/events/evt_1/attendees/export?')
    expect(href).toContain('search=sara')
    expect(href).toContain('status=checked_in')
    expect(href).not.toContain('page=')
  })

  it('renders equivalent Arabic RTL operations without serious accessibility violations', async () => {
    vi.doMock('@/hooks/useLocale', () => ({
      useLocale: () => ({
        locale: 'ar',
        direction: 'rtl',
        t: (key: string) => key,
        localizedPath: (path: string) => `/ar${path}`,
      }),
    }))

    const { container } = render(
      <>
        <Orders
          event={event}
          orders={[{ id: '1', reference: 'ord_safe', status: 'paid', total: '500 SAR', notification_status: 'delivered' }]}
        />
        <Attendees
          event={event}
          attendees={[{ id: 'attendee-safe', label: 'dee-safe', display_name: 'dee-safe', status: 'not_checked_in', locale: 'ar' }]}
        />
      </>,
    )
    expect(screen.getByRole('heading', { name: 'Orders' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Attendees' })).toBeInTheDocument()
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
  })
})
