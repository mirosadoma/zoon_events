import { render, screen } from '@testing-library/react'
import { usePage } from '@inertiajs/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { FreeCheckout } from '@/components/registration/FreeCheckout'
import Confirmation from '@/pages/public/registration/Confirmation'
import { fireEvent } from '@testing-library/react'

vi.mock('@inertiajs/react', () => ({
  usePage: vi.fn(),
  router: { visit: vi.fn() },
}))

vi.mock('@/components/registration/RegistrationPageControls', () => ({
  default: () => null,
}))

describe('free registration journey', () => {
  beforeEach(() => {
    vi.mocked(usePage).mockReturnValue({
      props: { locale: 'ar', direction: 'rtl' },
      url: '/ar/events/demo/register/confirmation/ord_synthetic',
    } as unknown as ReturnType<typeof usePage>)
  })

  it('validates and submits English free checkout with separate marketing consent', () => {
    const submit = vi.fn()
    render(<FreeCheckout locale="en" onSubmit={submit} />)
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'attendee@example.test' } })
    fireEvent.click(screen.getByRole('button', { name: 'Register' }))
    expect(submit).toHaveBeenCalledWith({ email: 'attendee@example.test', marketing: false })
  })

  it('renders Arabic RTL confirmation accessibly', () => {
    const { container } = render(
      <Confirmation
        locale="ar"
        reference="ord_synthetic"
        eventName="فعالية تجريبية"
        attendeeName="عمرو"
        qrPayload="ord_synthetic"
      />,
    )
    expect(container.querySelector('main')).toHaveAttribute('dir', 'rtl')
    expect(screen.getByRole('heading', { name: /مرحباً عمرو/ })).toBeInTheDocument()
    expect(screen.getByText(/ord_synthetic/)).toBeInTheDocument()
    expect(screen.getByText('تم التسجيل بنجاح')).toBeInTheDocument()
  })
})
