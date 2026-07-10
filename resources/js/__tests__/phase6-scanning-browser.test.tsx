import { render, screen } from '@testing-library/react'
import axe from 'axe-core'
import { describe, expect, it, vi } from 'vitest'
import CheckInScanner from '@/pages/tenant/checkin/Scanner'
import ScanEvents from '@/pages/tenant/checkin/ScanEvents'

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

describe('phase 6 scanning browser journeys', () => {
  it('renders scanner form without serious axe violations', async () => {
    const { container } = render(<CheckInScanner event={event} tenantId="ten_1" />)

    expect(screen.getByRole('heading', { name: 'Check-in scanner' })).toBeInTheDocument()
    expect(screen.getByLabelText('QR payload')).toBeRequired()

    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')).toHaveLength(0)
  })

  it('renders scan events with result filter', () => {
    render(
      <ScanEvents
        event={event}
        scanEvents={[
          {
            id: 'scan_1',
            result: 'rejected',
            scanner_type: 'staff_phone',
            offline: false,
            reason: 'credential_revoked',
            scanned_at: '2026-07-01T10:00:00Z',
          },
        ]}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Scan events' })).toBeInTheDocument()
    expect(screen.getByText('credential_revoked')).toBeInTheDocument()
  })
})
