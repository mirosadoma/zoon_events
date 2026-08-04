import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import EventReport from '@/pages/tenant/reports/EventReport'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
}))

vi.mock('@/components/reports/EventReportVenuesMap', () => ({
  default: () => <div>Event venues map</div>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => {
      const messages: Record<string, string> = {
        reports: 'Reports',
        overview: 'Overview',
        events: 'Events',
        eventDetail: 'Event detail',
        status: 'Status',
        ticketTypes: 'Ticket types',
        reportExportCsv: 'Export CSV',
        reportTabSummary: 'Summary',
        reportTabFunnel: 'Funnel',
        reportTabRegistration: 'Registration',
        reportTabCheckIn: 'Check-in',
        reportTabVenues: 'Venues',
        reportTabOnsite: 'On-site',
        reportTabsLabel: 'Report sections',
        reportSectionSummary: 'Event summary',
        reportRevenue: 'Revenue',
        reportRegistrations: 'Registrations',
        reportPaidOrders: 'Paid orders',
        reportPaymentSuccessRate: 'Payment success rate',
        reportCredentialsIssued: 'Credentials issued',
        reportCredentialsRevoked: 'Credentials revoked',
        reportWalletAdoption: 'Wallet adoption',
        reportCheckins: 'Check-ins',
        reportCheckedInAttendees: 'Checked-in attendees',
        reportCheckinRate: 'Check-in rate',
        reportAcceptedScans: 'Accepted scans',
        reportRejectedScans: 'Rejected scans',
        reportFirstScanSuccessRate: 'First-scan success rate',
        reportCheckinSuccessRate: 'Check-in success rate',
        reportBadgePrints: 'Badge prints',
        reportBadgeReprints: 'Badge reprints',
        reportAcsAccepted: 'ACS entries accepted',
        reportAcsRejected: 'ACS entries rejected',
        reportKiosksOnline: 'Kiosks online',
        reportKiosksTotal: 'Kiosks total',
        reportMetricUnavailable: 'Not available yet',
        reportFunnelTitle: 'Conversion funnel',
        reportFunnelRegistered: 'Registered',
        reportFunnelPaid: 'Paid',
        reportFunnelCredentialed: 'Credentialed',
        reportFunnelCheckedIn: 'Checked in',
        reportVenuesMapTitle: 'Event venues',
        reportVenuesMapHint: 'Hover or click',
        reportVenuesMapEmpty: 'No venues',
        mapPickerMissingApiKey: 'Missing key',
      }
      return messages[key] ?? key
    },
  }),
}))

const event = { id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }

const baseReport = {
  summary: {
    registrations: { value: 120, available: true },
    paid_orders: { value: 80, available: true },
    payment_success_rate: { value: 66.7, available: true },
    credentials_issued: { value: 100, available: true },
    credentials_revoked: { value: 2, available: true },
    wallet_adoption: { value: 45.5, available: true },
    checked_in_attendees: { value: 90, available: true },
    checkin_rate: { value: 75, available: true },
    accepted_scans: { value: 100, available: true },
    rejected_scans: { value: 10, available: true },
    first_scan_success_rate: { value: null, available: false, label: 'not available yet' },
    checkin_success_rate: { value: 92.1, available: true },
    badge_prints: { value: 75, available: true },
    badge_reprints: { value: 1, available: true },
    acs_entries_accepted: { value: 40, available: true },
    acs_entries_rejected: { value: 3, available: true },
    kiosks_online: { value: 2, available: true },
    kiosks_total: { value: 3, available: true },
    revenue_minor: { value: 10000, available: true },
    currency: 'EGP',
  },
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
  orders_by_status: [],
  categories: [],
  ticket_types: [],
  checkins_by_day: [],
  checkins_by_hour: [],
  funnel: [
    { key: 'registered', count: 120, conversion_from_previous: null },
    { key: 'paid', count: 80, conversion_from_previous: 66.7 },
    { key: 'credentialed', count: 100, conversion_from_previous: 125 },
    { key: 'checked_in', count: 90, conversion_from_previous: 90 },
  ],
  venue_markers: [],
  badge_jobs: { by_status: { queued: 0, printed: 75, failed: 0 }, reprints: 1 },
  top_reject_reasons: [],
  kiosks: { total: 3, online: 2, offline: 1, degraded: 0, retired: 0, registered: 0 },
}

describe('event report page', () => {
  it('renders available metrics and placeholders for unavailable ones', () => {
    render(
      <EventReport
        event={event}
        tenantId="ten_1"
        report={baseReport}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Reports' })).toBeInTheDocument()
    expect(screen.getByText('120')).toBeInTheDocument()
    expect(screen.getByText('45.5%')).toBeInTheDocument()
    expect(screen.getByText('not available yet')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Export CSV' })).toBeInTheDocument()
  })

  it('switches to funnel and venues tabs', () => {
    render(
      <EventReport
        event={event}
        tenantId="ten_1"
        report={baseReport}
      />,
    )

    fireEvent.click(screen.getByRole('tab', { name: 'Funnel' }))
    expect(screen.getByText('Conversion funnel')).toBeInTheDocument()
    expect(screen.getByText('Registered')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('tab', { name: 'Venues' }))
    expect(screen.getByText('Event venues')).toBeInTheDocument()
    expect(screen.getByText('Event venues map')).toBeInTheDocument()
  })
})
