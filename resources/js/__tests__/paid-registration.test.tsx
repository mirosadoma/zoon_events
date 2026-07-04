import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import Payment from '@/pages/public/registration/Payment'

describe('paid registration states', () => {
  it('renders immutable totals and an accessible English action', () => {
    render(<Payment locale="en" totalMinor={500} currency="SAR" state="action_required" actionUrl="https://pay.example.test" />)
    expect(screen.getByText(/Total: SAR\s*5\.00/)).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('Continue to secure payment')
    expect(screen.getByRole('link')).toHaveAttribute('href', 'https://pay.example.test')
  })

  it('renders Arabic unknown recovery state in RTL', () => {
    const { container } = render(<Payment locale="ar" totalMinor={500} currency="SAR" state="unknown" />)
    expect(container.querySelector('main')).toHaveAttribute('dir', 'rtl')
    expect(screen.getByRole('status')).toHaveTextContent('يجري التحقق من حالة الدفع')
  })
})
