import { render, screen } from '@testing-library/react'
import axe from 'axe-core'
import { describe, expect, it, vi } from 'vitest'
import Orders from '@/pages/tenant/events/Orders'
import CredentialDetailPage from '@/pages/tenant/credentials/Detail'

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

const event = { id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }

describe('phase 6 credentials browser journeys', () => {
  it('renders orders list with filters', async () => {
    const { container } = render(
      <Orders
        event={event}
        orders={[
          {
            id: 'ord_1',
            reference: 'ORD-1001',
            status: 'paid',
            total: '500.00 SAR',
            notification_status: 'delivered',
          },
        ]}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Orders' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'ORD-1001' })).toHaveAttribute('href', '/tenant/events/evt_1/orders/ord_1')

    const results = await axe.run(container)
    expect(results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')).toHaveLength(0)
  })

  it('renders credential detail with revoke action', () => {
    render(
      <CredentialDetailPage
        event={event}
        credential={{
          id: 'cred_1',
          code: 'ABCD1234',
          attendee_id: 'attendee_1',
          status: 'active',
          issued_at: '2026-07-01T10:00:00Z',
          expires_at: '2026-08-01T10:00:00Z',
        }}
      />,
    )

    expect(screen.getByRole('heading', { name: 'ABCD1234' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Revoke credential' })).toBeInTheDocument()
  })
})
