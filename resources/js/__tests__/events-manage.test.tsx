import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import EventList from '@/pages/tenant/events/List'
import EventSetup from '@/pages/tenant/events/EventSetup'

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

describe('events manage flow', () => {
  it('renders events list with create action', () => {
    render(
      <EventList
        events={[
          {
            id: 'evt_1',
            name: { en: 'Summit', ar: 'القمة' },
            status: 'draft',
            tier: 'public',
            timezone: 'Africa/Cairo',
            capacity: 100,
          },
        ]}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Events' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'New event' })).toHaveAttribute('href', '/tenant/events/create')
    expect(screen.getByRole('link', { name: 'Summit' })).toHaveAttribute('href', '/tenant/events/evt_1')
  })

  it('renders create-event setup shell', () => {
    render(
      <EventSetup
        event={{
          id: null,
          name: { en: 'New event', ar: 'فعالية جديدة' },
          status: 'draft',
          tier: 'public',
          readiness: ['Save the event before publishing.'],
        }}
        can={{ manage: true, publish: false }}
      />,
    )

    expect(screen.getByRole('heading', { name: 'New event' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Save changes' })).toBeInTheDocument()
  })
})
