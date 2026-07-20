import { render, screen } from '@testing-library/react'
import axe from 'axe-core'
import { describe, expect, it, vi } from 'vitest'
import BadgePrintJobs from '@/pages/tenant/badges/PrintJobs'
import ManualDesk from '@/pages/tenant/manual-desk/Desk'
import KioskIndex from '@/pages/tenant/kiosk/Index'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  usePage: () => ({ props: { can: { 'kiosk.manage': true, 'badge.reprint': true, 'attendee.walkup.register': true } } }),
  router: { get: vi.fn() },
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => key,
  }),
}))

vi.mock('@/hooks/useLocalizedRouter', () => ({
  useLocalizedRouter: () => ({ get: vi.fn() }),
}))

vi.mock('@/components/routing/LocalizedLink', () => ({
  default: ({ href, children, className }: { href: string; children: React.ReactNode; className?: string }) => (
    <a href={href} className={className}>{children}</a>
  ),
}))

const event = { id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }

describe('phase 6 kiosk and badge browser journeys', () => {
  it('renders kiosk management without serious axe violations', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: [] }),
    }))

    const { container } = render(
      <KioskIndex
        event={event}
        tenantId="ten_1"
        kiosks={[{ id: 'kiosk_1', device_name: 'Lobby', device_code: 'ABC12345', status: 'online', printer_status: 'ready', last_heartbeat_at: null, confirmation_required: false }]}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Kiosk management' })).toBeInTheDocument()
    expect(screen.getByText('Lobby')).toBeInTheDocument()

    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')).toHaveLength(0)
  })

  it('renders badge print jobs with status filter', () => {
    render(
      <BadgePrintJobs
        event={event}
        tenantId="ten_1"
        printJobs={[{ id: 'job_1', attendee_id: 'att_1', attendee_name: 'Ada Lovelace', status: 'printed', failure_reason: null, is_reprint: false, reprint_reason: null, original_print_job_id: null, printed_at: '2026-07-01T10:00:00Z' }]}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Badge print jobs' })).toBeInTheDocument()
    expect(screen.getByLabelText('Status')).toBeInTheDocument()
  })

  it('renders manual desk search form', () => {
    render(<ManualDesk event={event} tenantId="ten_1" ticketTypes={[]} />)

    expect(screen.getByRole('heading', { name: 'Manual desk' })).toBeInTheDocument()
    expect(screen.getByLabelText('Search')).toBeInTheDocument()
  })
})
