import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import axe from 'axe-core'
import Orders from '@/pages/tenant/events/Orders'
import Attendees from '@/pages/tenant/events/Attendees'

describe('organizer operations', () => {
  it('renders minimized order and delivery state without buyer PII', () => {
    render(<Orders orders={[{ id: '1', reference: 'ord_safe', status: 'paid', total: '500 SAR', notification_status: 'pending' }]} />)
    expect(screen.getByText('ord_safe')).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('Queued')
    expect(screen.queryByText(/@/)).not.toBeInTheDocument()
  })

  it('renders minimized attendee state', () => {
    render(<Attendees attendees={[{ id: 'attendee-safe', status: 'registered', locale: 'ar' }]} />)
    expect(screen.getByText(/attendee-safe/)).toBeInTheDocument()
    expect(screen.queryByText(/email/i)).not.toBeInTheDocument()
  })

  it('renders equivalent Arabic RTL operations without serious accessibility violations', async () => {
    const { container } = render(
      <>
        <Orders locale="ar" orders={[{ id: '1', reference: 'ord_safe', status: 'paid', total: '500 SAR', notification_status: 'delivered' }]} />
        <Attendees locale="ar" attendees={[{ id: 'attendee-safe', status: 'registered', locale: 'ar' }]} />
      </>,
    )
    expect(screen.getByRole('heading', { name: 'الطلبات' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'الحضور' })).toBeInTheDocument()
    expect(container.querySelectorAll('[dir="rtl"]')).toHaveLength(2)
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
  })
})
