import { render, screen } from '@testing-library/react'
import { usePage } from '@inertiajs/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Payment from '@/pages/public/registration/Payment'

vi.mock('@inertiajs/react', () => ({
  usePage: vi.fn(),
}))

vi.mock('@/components/registration/RegistrationPageControls', () => ({
  default: () => null,
}))

describe('paid registration states', () => {
  beforeEach(() => {
    vi.mocked(usePage).mockReturnValue({
      props: { locale: 'en', direction: 'ltr' },
      url: '/en/events/demo/register/payment/ord_demo',
    } as unknown as ReturnType<typeof usePage>)
  })

  it('renders immutable totals and demo card payment form', () => {
    render(
      <Payment
        locale="en"
        event={{ slug: 'demo', name: { en: 'Demo Event', ar: 'فعالية' } }}
        publicReference="ord_demo"
        accessToken="token"
        totalMinor={500}
        currency="SAR"
        submitUrl="/en/events/demo/register/payment/ord_demo"
      />,
    )
    expect(screen.getByText(/Total: SAR\s*5\.00/)).toBeInTheDocument()
    expect(screen.getByText(/Demo cards/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Pay now/i })).toBeInTheDocument()
  })

  it('renders Arabic payment form in RTL', () => {
    vi.mocked(usePage).mockReturnValue({
      props: { locale: 'ar', direction: 'rtl' },
      url: '/ar/events/demo/register/payment/ord_demo',
    } as unknown as ReturnType<typeof usePage>)

    const { container } = render(
      <Payment
        locale="ar"
        event={{ slug: 'demo', name: { en: 'Demo Event', ar: 'فعالية' } }}
        publicReference="ord_demo"
        accessToken="token"
        totalMinor={500}
        currency="SAR"
        submitUrl="/ar/events/demo/register/payment/ord_demo"
      />,
    )
    expect(container.querySelector('main')).toHaveAttribute('dir', 'rtl')
    expect(screen.getByRole('button', { name: /ادفع الآن/i })).toBeInTheDocument()
  })
})
