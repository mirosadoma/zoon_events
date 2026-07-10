import { render, screen } from '@testing-library/react'
import axe from 'axe-core'
import { describe, expect, it, vi } from 'vitest'
import AcsOverview from '@/pages/tenant/acs/Index'
import AcsZones from '@/pages/tenant/acs/Zones'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  usePage: () => ({ props: { can: { 'acs.emergency.manage': true } } }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

const event = { id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }

describe('phase 6 ACS browser journeys', () => {
  it('renders ACS overview without serious axe violations', async () => {
    const { container } = render(
      <AcsOverview
        event={event}
        tenantId="ten_1"
        overview={{
          zones_total: 1,
          lanes_total: 2,
          rules_total: 3,
          integration_status: 'online',
          active_emergency: false,
          gates_offline: 0,
          latest_gate_events: [],
        }}
      />,
    )

    expect(screen.getByRole('heading', { name: 'ACS overview' })).toBeInTheDocument()
    expect(screen.getByText('1')).toBeInTheDocument()

    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')).toHaveLength(0)
  })

  it('renders zone create form for journey 12', () => {
    render(<AcsZones event={event} tenantId="ten_1" zones={[]} />)

    expect(screen.getByRole('heading', { name: 'ACS zones' })).toBeInTheDocument()
    expect(screen.getByLabelText('Name')).toBeRequired()
    expect(screen.getByLabelText('External zone ID')).toBeRequired()
  })
})
