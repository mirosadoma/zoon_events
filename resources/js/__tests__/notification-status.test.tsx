import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { NotificationStatus } from '@/components/orders/NotificationStatus'

describe('notification status', () => {
  it('shows only safe delivery categories', () => {
    render(<NotificationStatus status="temporary_failure" />)
    expect(screen.getByRole('status')).toHaveTextContent('Retrying')
    expect(screen.getByRole('status')).toHaveAccessibleName('Confirmation delivery status')
  })

  it('does not echo unknown provider values', () => {
    render(<NotificationStatus status="provider: secret destination" />)
    expect(screen.getByRole('status')).toHaveTextContent('Unavailable')
    expect(screen.queryByText(/secret destination/i)).not.toBeInTheDocument()
  })
})
