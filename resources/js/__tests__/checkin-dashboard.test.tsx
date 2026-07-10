import { render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import CheckInDashboard from '@/pages/tenant/checkin/Dashboard'
import { CHECK_IN_SUMMARY_POLL_INTERVAL_MS } from '@/lib/checkin-polling'

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

describe('check-in dashboard', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('renders loading then populated state and polls on an interval', async () => {
    const fetchMock = vi.mocked(fetch)
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        data: {
          registered_count: 10,
          checked_in_count: 4,
          rejected_count: 1,
          duplicate_count: 2,
          last_scan_at: '2027-01-10T12:00:00Z',
        },
      }),
    } as Response)

    render(<CheckInDashboard event={event} tenantId="ten_1" pollIntervalMs={100} />)

    expect(screen.getByRole('status')).toHaveTextContent('Loading…')

    await waitFor(() => {
      expect(screen.getByTestId('checked-in-count')).toHaveTextContent('4')
    })

    expect(fetchMock.mock.calls[0][0]).toBe('/api/v1/tenant/events/evt_1/check-in-summary')
    expect(fetchMock.mock.calls[0][1]).toMatchObject({
      credentials: 'include',
      headers: expect.objectContaining({ 'X-Tenant-ID': 'ten_1' }),
    })
  })

  it('renders empty state when no scans have been recorded', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: true,
      json: async () => ({
        data: {
          registered_count: 0,
          checked_in_count: 0,
          rejected_count: 0,
          duplicate_count: 0,
          last_scan_at: null,
        },
      }),
    } as Response)

    render(<CheckInDashboard event={event} tenantId="ten_1" initialSummary={null} pollIntervalMs={100} />)

    await waitFor(() => {
      expect(screen.getByText('No scans yet')).toBeInTheDocument()
    })
  })

  it('documents the polling interval in one shared constant', () => {
    expect(CHECK_IN_SUMMARY_POLL_INTERVAL_MS).toBe(5000)
  })
})
