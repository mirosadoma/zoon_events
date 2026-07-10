import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { FreeCheckout } from '@/components/registration/FreeCheckout'
import Confirmation from '@/pages/public/registration/Confirmation'

describe('free registration journey', () => {
  it('validates and submits English free checkout with separate marketing consent', () => {
    const submit = vi.fn()
    render(<FreeCheckout locale="en" onSubmit={submit} />)
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'attendee@example.test' } })
    fireEvent.click(screen.getByRole('button', { name: 'Register' }))
    expect(submit).toHaveBeenCalledWith({ email: 'attendee@example.test', marketing: false })
  })

  it('renders Arabic RTL confirmation accessibly', () => {
    const { container } = render(<Confirmation locale="ar" reference="ord_synthetic" />)
    expect(container.querySelector('main')).toHaveAttribute('dir', 'rtl')
    expect(screen.getByRole('heading', { name: 'اكتمل التسجيل' })).toBeInTheDocument()
    expect(screen.getByText(/ord_synthetic/)).toBeInTheDocument()
  })
})
