import { render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PublicEventAgenda from '@/pages/public/registration/Agenda'

vi.mock('@inertiajs/react', () => ({
  router: { visit: vi.fn() },
  usePage: () => ({ props: { locale: 'en', direction: 'ltr' } }),
  Link: ({ href, children, className }: { href: string; children: React.ReactNode; className?: string }) => (
    <a href={href} className={className}>{children}</a>
  ),
}))

const event = {
  name: { en: 'Synthetic Summit', ar: 'قمة تجريبية' },
  description: { en: 'Safe sample', ar: 'مثال آمن' },
  branding: { brand_reference: 'tenant-brand' },
}

describe('public event agenda page', () => {
  beforeEach(() => {
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    })
    localStorage.clear()
  })

  it('renders agenda timeline and register link', () => {
    render(
      <PublicEventAgenda
        locale="en"
        event={event}
        registerUrl="/events/synthetic-summit/register"
        items={[{
          id: '1',
          title: { en: 'Opening speech', ar: 'كلمة افتتاحية' },
          start_at: '2026-07-12T10:00:00.000Z',
          end_at: '2026-07-12T10:15:00.000Z',
        }]}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Agenda' })).toBeInTheDocument()
    expect(screen.getByText('Opening speech')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Register Now' })).toHaveAttribute('href', '/en/events/synthetic-summit/register')
  })
})
