import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import Ticketing from '@/pages/tenant/events/Ticketing'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'ar', direction: 'rtl' }),
}))

describe('ticketing controls', () => {
  it('announces localized inventory terminal states', () => {
    render(
      <Ticketing
        event={{ id: 'evt_1', name: { en: 'General', ar: 'عام' } }}
        tickets={[{
          id: 'ticket',
          code: 'GA',
          name: { en: 'General', ar: 'عام' },
          price_minor: 500,
          currency: 'SAR',
          remaining_quantity: 0,
          state: 'sold_out',
        }]}
      />,
    )

    expect(screen.getByRole('status')).toHaveTextContent('نفدت التذاكر')
  })
})
