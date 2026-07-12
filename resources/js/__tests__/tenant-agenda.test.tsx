import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import EventAgenda from '@/pages/tenant/events/Agenda'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { reload: vi.fn() },
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr', t: (key: string) => key }),
}))

vi.mock('@/hooks/useToast', () => ({
  useToast: () => ({ toast: vi.fn() }),
}))

describe('tenant event agenda page', () => {
  it('renders agenda form without crashing', () => {
    render(
      <EventAgenda
        tenantId="ten_1"
        event={{ id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }}
        items={[
          {
            id: '1',
            title_en: 'Opening',
            title_ar: 'الافتتاح',
            start_at: '2026-07-12T10:00:00Z',
            end_at: null,
          },
        ]}
      />,
    )

    expect(screen.getByRole('heading', { name: 'eventAgendaTitle' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'eventAgendaSave' })).toBeInTheDocument()
  })
})
