import { render, screen } from '@testing-library/react'
import axe from 'axe-core'
import { describe, expect, it, vi } from 'vitest'
import EventDetail from '@/pages/tenant/events/Detail'
import RegistrationBuilder from '@/pages/tenant/registration/Builder'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

vi.mock('@/components/layout/PermissionGate', () => ({
  default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

const event = {
  id: 'evt_1',
  name: { en: 'Summit', ar: 'القمة' },
  status: 'draft',
  tier: 'public',
  timezone: 'Africa/Cairo',
  capacity: 250,
}

describe('phase 6 events browser journeys', () => {
  it('renders event detail with publish controls without serious axe violations', async () => {
    const { container } = render(
      <EventDetail
        event={event}
        tabs={[
          { label: 'Registration form', href: '/tenant/events/evt_1/registration-form' },
          { label: 'Ticket types', href: '/tenant/events/evt_1/ticket-types' },
        ]}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Summit' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Publish' })).toBeInTheDocument()

    const results = await axe.run(container)
    expect(results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')).toHaveLength(0)
  })

  it('renders registration builder empty state', () => {
    render(<RegistrationBuilder event={event} fields={[]} />)

    expect(screen.getByRole('heading', { name: 'Registration form' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Preview' })).toHaveAttribute('href', '/tenant/events/evt_1/registration-preview')
  })
})
