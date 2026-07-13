import { fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PublicRegistrationEvent from '@/pages/public/registration/Event'

vi.mock('@inertiajs/react', () => ({
  router: { visit: vi.fn() },
  usePage: () => ({ props: { locale: 'en', direction: 'ltr' } }),
}))

const event = {
  name: { en: 'Synthetic Summit', ar: 'قمة تجريبية' },
  description: { en: 'Safe sample', ar: 'مثال آمن' },
  branding: { brand_reference: 'tenant-brand' },
}
const form = {
  fields: [{
    key: 'email',
    type: 'email' as const,
    label_en: 'Email',
    label_ar: 'البريد الإلكتروني',
    required: true,
  }],
  privacy_notice_version: 'privacy-v1',
  terms_version: 'terms-v1',
}

describe('public event registration shell', () => {
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

  it('renders accessible English LTR content and consent evidence', () => {
    const { container } = render(<PublicRegistrationEvent locale="en" event={event} form={form} />)
    expect(container.querySelector('main')).toHaveAttribute('dir', 'ltr')
    expect(screen.getByRole('heading', { name: 'Synthetic Summit' })).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: /Email/i })).toBeRequired()
    expect(screen.getByText(/I accept the terms and privacy notice/i)).toBeInTheDocument()
  })

  it('renders Arabic RTL labels without changing stable field keys', () => {
    const { container } = render(<PublicRegistrationEvent locale="ar" event={event} form={form} />)
    expect(container.querySelector('main')).toHaveAttribute('dir', 'rtl')
    expect(screen.getByRole('heading', { name: 'قمة تجريبية' })).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: /البريد الإلكتروني/ })).toHaveAttribute('name', 'email')
  })

  it('renders organizer preview without submit action', () => {
    render(<PublicRegistrationEvent locale="en" event={event} form={form} isPreview />)

    expect(screen.getByRole('status')).toHaveTextContent(/display only/i)
    expect(screen.queryByRole('button', { name: 'Complete registration' })).not.toBeInTheDocument()
    expect(screen.getByLabelText('Email')).toBeDisabled()
    expect(screen.getByRole('button', { name: 'التبديل إلى العربية' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Dark mode' })).toBeInTheDocument()
  })

  it('highlights the first invalid field with a validation tour popover', async () => {
    render(
      <PublicRegistrationEvent
        locale="en"
        event={event}
        form={{ ...form, version_id: 'form_v1' }}
        submitUrl="/en/events/summit/register"
        ticketTypes={[{
          id: 'ticket_1',
          code: 'STD',
          name: { en: 'Standard', ar: 'عادي' },
          price_minor: 0,
          currency: 'USD',
        }]}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Complete registration' }))

    expect(await screen.findByRole('alertdialog')).toBeInTheDocument()
    expect(screen.getByText(/Email: is required/i)).toBeInTheDocument()
  })

  it('renders language and theme controls', () => {
    render(<PublicRegistrationEvent locale="ar" event={event} form={form} />)

    expect(screen.getByRole('button', { name: 'Switch to English' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'الوضع الداكن' })).toBeInTheDocument()
  })

  it('renders fixed venue select under ticket section when venues exist', () => {
    render(
      <PublicRegistrationEvent
        locale="en"
        event={{
          ...event,
          venues: [{
            id: 'venue_1',
            name: { en: 'At Le Meridien', ar: 'لو مريديان' },
            city: { en: 'Khobar', ar: 'الخبر' },
            country: { en: 'Saudi Arabia', ar: 'السعودية' },
            start_at: '2026-07-20T09:30:00.000Z',
          }],
        }}
        form={{ ...form, version_id: 'form_v1' }}
        ticketTypes={[{
          id: 'ticket_1',
          code: 'STD',
          name: { en: 'Standard', ar: 'عادي' },
          price_minor: 0,
          currency: 'USD',
        }]}
      />,
    )

    expect(screen.getByLabelText('Location - Date')).toBeRequired()
    expect(screen.getByRole('option', { name: /Khobar - AT LE MERIDIEN/i })).toBeInTheDocument()
  })
})
