import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import EventReport from '@/pages/tenant/reports/EventReport'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    messages: {
      reports: 'Reports',
      overview: 'Overview',
      events: 'Events',
      eventDetail: 'Event detail',
      reportRegistrations: 'Registrations',
      reportPaidOrders: 'Paid orders',
      reportPaymentSuccessRate: 'Payment success rate',
      reportCredentialsIssued: 'Credentials issued',
      reportCredentialsRevoked: 'Credentials revoked',
      reportWalletAdoption: 'Wallet adoption',
      reportCheckins: 'Check-ins',
      reportFirstScanSuccessRate: 'First-scan success rate',
      reportCheckinSuccessRate: 'Check-in success rate',
      reportBadgePrints: 'Badge prints',
      reportAcsAccepted: 'ACS entries accepted',
      reportAcsRejected: 'ACS entries rejected',
      reportMetricUnavailable: 'Not available yet',
    },
  }),
}))

const event = { id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }

describe('event report page', () => {
  it('renders available metrics and placeholders for unavailable ones', () => {
    render(
      <EventReport
        event={event}
        tenantId="ten_1"
        report={{
          registrations: { value: 120, available: true },
          paid_orders: { value: 80, available: true },
          payment_success_rate: { value: 66.7, available: true },
          credentials_issued: { value: 100, available: true },
          credentials_revoked: { value: 2, available: true },
          wallet_adoption: { value: 45.5, available: true },
          checkins: { value: 90, available: true },
          first_scan_success_rate: { value: null, available: false, label: 'not available yet' },
          checkin_success_rate: { value: 92.1, available: true },
          badge_prints: { value: 75, available: true },
          acs_entries_accepted: { value: 40, available: true },
          acs_entries_rejected: { value: 3, available: true },
        }}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Reports' })).toBeInTheDocument()
    expect(screen.getByText('120')).toBeInTheDocument()
    expect(screen.getByText('45.5%')).toBeInTheDocument()
    expect(screen.getByText('not available yet')).toBeInTheDocument()
  })
})
