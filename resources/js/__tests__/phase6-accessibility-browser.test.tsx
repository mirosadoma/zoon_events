import { render, screen } from '@testing-library/react'
import axe from 'axe-core'
import { afterEach, describe, expect, it, vi } from 'vitest'
import AdminUsers from '@/pages/admin/Users'
import EventReport from '@/pages/tenant/reports/EventReport'
import WalkUpRegistration from '@/pages/tenant/manual-desk/WalkUp'

let currentLocale: 'en' | 'ar' = 'en'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children, title }: { children: React.ReactNode; title?: string }) => (
    <main dir={currentLocale === 'ar' ? 'rtl' : 'ltr'} lang={currentLocale}>
      {title ? <h1>{title}</h1> : null}
      {children}
    </main>
  ),
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children, className }: { href: string; children: React.ReactNode; className?: string }) => <a className={className} href={href}>{children}</a>,
  router: { get: vi.fn() },
  usePage: () => ({ props: { can: { 'membership.manage': true } } }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: currentLocale, direction: currentLocale === 'ar' ? 'rtl' : 'ltr' }),
}))

vi.mock('@/hooks/useToast', () => ({
  useToast: () => ({ toast: vi.fn() }),
}))

const event = { id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }

describe('phase 6 accessibility and RTL sweep', () => {
  afterEach(() => {
    currentLocale = 'en'
    vi.restoreAllMocks()
  })

  it('renders admin and report pages without serious axe violations', async () => {
    const { container } = render(
      <>
        <AdminUsers
          tenantId="ten_1"
          users={[{
            id: 'mem_1',
            name: 'Alpha User',
            email: 'alpha@example.test',
            status: 'active',
            user_status: 'active',
            created_at: '2026-07-01T00:00:00Z',
          }]}
        />
        <EventReport
          event={event}
          tenantId="ten_1"
          report={{
            registrations: { value: 10, available: true },
            paid_orders: { value: 8, available: true },
            payment_success_rate: { value: 80, available: true },
            credentials_issued: { value: 10, available: true },
            credentials_revoked: { value: 0, available: true },
            wallet_adoption: { value: 40, available: true },
            checkins: { value: 6, available: true },
            first_scan_success_rate: { value: null, available: false },
            checkin_success_rate: { value: 100, available: true },
            badge_prints: { value: 5, available: true },
            acs_entries_accepted: { value: 4, available: true },
            acs_entries_rejected: { value: 1, available: true },
          }}
        />
      </>,
    )

    expect(screen.getByText('Alpha User')).toBeInTheDocument()
    expect(screen.getByText('Registrations')).toBeInTheDocument()

    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
  })

  it('renders manual desk walk-up in Arabic RTL without serious axe violations', async () => {
    currentLocale = 'ar'

    const { container } = render(
      <WalkUpRegistration
        event={event}
        tenantId="ten_1"
        ticketTypes={[{ id: 'ticket_1', code: 'VIP', name: { en: 'VIP', ar: 'كبار الشخصيات' } }]}
      />,
    )

    expect(container.querySelector('[dir="rtl"]')).toBeInTheDocument()
    expect(screen.getAllByText('تسجيل مباشر').length).toBeGreaterThan(0)

    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
  })
})
