import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import Ticketing from '@/pages/tenant/events/Ticketing'

describe('ticketing controls', () => {
  it('announces localized inventory terminal states', () => {
    render(<Ticketing locale="ar" tickets={[{
      id: 'ticket',
      name: { en: 'General', ar: 'عام' },
      price_minor: 500,
      currency: 'SAR',
      remaining_quantity: 0,
      state: 'sold_out',
    }]} />)

    expect(screen.getByRole('status')).toHaveTextContent('نفدت التذاكر')
    expect(screen.getByRole('main')).toHaveAttribute('dir', 'rtl')
  })
})
