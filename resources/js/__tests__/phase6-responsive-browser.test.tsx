import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import KioskMode from '@/pages/kiosk/Mode'
import ManualDesk from '@/pages/tenant/manual-desk/Desk'
import WalkUpRegistration from '@/pages/tenant/manual-desk/WalkUp'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <main className="max-w-full overflow-x-hidden">{children}</main>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children, className }: { href: string; children: React.ReactNode; className?: string }) => <a className={className} href={href}>{children}</a>,
  usePage: () => ({
    props: {
      can: {
        'attendee.walkup.register': true,
        'badge.print': true,
        'badge.reprint': true,
      },
    },
  }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

const event = { id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }
const tickets = [{ id: 'ticket_1', code: 'VIP', name: { en: 'VIP', ar: 'كبار الشخصيات' } }]

describe('phase 6 responsive surfaces', () => {
  it('keeps kiosk mode controls constrained for mobile widths', () => {
    const { container } = render(
      <div style={{ width: 390 }}>
        <KioskMode deviceCode="KIOSK-1" kiosk={{ id: 'kiosk_1', device_name: 'Lobby kiosk', confirmation_required: false }} event={event} />
      </div>,
    )

    expect(screen.getByLabelText('Scan QR code').closest('form')).toHaveClass('max-w-lg')
    expect(screen.getByLabelText('Lookup fallback').closest('form')).toHaveClass('max-w-lg')
    expect(container.querySelector('.min-h-screen')).toHaveClass('p-6')
  })

  it('uses wrapping controls on manual desk tablet/mobile layouts', () => {
    render(
      <div style={{ width: 390 }}>
        <ManualDesk event={event} tenantId="ten_1" ticketTypes={tickets} />
      </div>,
    )

    expect(screen.getByLabelText('Search').closest('form')).toHaveClass('flex-wrap')
    expect(screen.getByRole('link', { name: 'Walk-up registration' })).toBeInTheDocument()
  })

  it('keeps walk-up registration form inside the shared content flow', () => {
    render(
      <div style={{ width: 390 }}>
        <WalkUpRegistration event={event} tenantId="ten_1" ticketTypes={tickets} />
      </div>,
    )

    expect(screen.getByLabelText('Ticket type')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Back to desk' })).toHaveClass('button-secondary')
  })
})
