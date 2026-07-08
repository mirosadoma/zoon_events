import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import Ticketing from '@/pages/tenant/events/Ticketing'
import PriceTiers from '@/pages/tenant/ticketing/PriceTiers'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { reload: vi.fn(), visit: vi.fn() },
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

vi.mock('@/hooks/useToast', () => ({
  useToast: () => ({ toast: vi.fn() }),
}))

describe('ticketing and pricing pages', () => {
  it('renders ticket types with inventory state', () => {
    render(
      <Ticketing
        tenantId="ten_1"
        event={{ id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }}
        tickets={[
          {
            id: 'tkt_1',
            code: 'GA',
            name: { en: 'General admission', ar: 'دخول عام' },
            description: { en: '', ar: '' },
            attendee_type: 'general',
            price_minor: 15000,
            currency: 'SAR',
            capacity: 100,
            remaining_quantity: 42,
            status: 'active',
            state: 'available',
          },
        ]}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Ticket types' })).toBeInTheDocument()
    expect(screen.getByText('General admission')).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('Available')
    expect(screen.getByRole('button', { name: 'Create' })).toBeInTheDocument()
  })

  it('renders price tiers table', () => {
    render(
      <PriceTiers
        tenantId="ten_1"
        event={{ id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }}
        ticketTypes={[{ id: 'tkt_1', code: 'GA', name: { en: 'General admission', ar: 'دخول عام' }, currency: 'SAR' }]}
        priceTiers={[
          {
            id: 'tier_1',
            name: 'Early bird',
            ticket_type_id: 'tkt_1',
            price_minor: 10000,
            currency: 'SAR',
            priority: 1,
            status: 'active',
            is_active_now: true,
          },
        ]}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Price tiers' })).toBeInTheDocument()
    expect(screen.getByText('Early bird')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Create' })).toBeInTheDocument()
  })
})
