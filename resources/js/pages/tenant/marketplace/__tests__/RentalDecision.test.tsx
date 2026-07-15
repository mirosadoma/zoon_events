import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import en from '@/locales/en'
import RentalDecisionActions from '@/pages/tenant/marketplace/Components/RentalDecisionActions'
import DecisionReasonDialog from '@/pages/tenant/marketplace/Components/DecisionReasonDialog'

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => (en as unknown as Record<string, string>)[key] ?? key,
    localizedPath: (path: string) => `/en${path}`,
  }),
}))

vi.mock('@/components/layout/PermissionGate', () => ({
  default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

describe('RentalDecision', () => {
  it('shows owner approve and reject controls for requested rentals', () => {
    render(
      <RentalDecisionActions
        status="requested"
        viewerRole="owner"
        onApprove={vi.fn()}
        onReject={vi.fn()}
      />,
    )
    expect(screen.getByRole('button', { name: en.approveRental })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: en.rejectRental })).toBeInTheDocument()
  })

  it('shows organizer cancel for approved rentals', () => {
    render(
      <RentalDecisionActions
        status="approved"
        viewerRole="organizer"
        onCancel={vi.fn()}
      />,
    )
    expect(screen.getByRole('button', { name: en.cancelRental })).toBeInTheDocument()
  })

  it('requires reason before confirming reject dialog', () => {
    const onConfirm = vi.fn()
    render(
      <DecisionReasonDialog
        open
        kind="reject"
        onConfirm={onConfirm}
        onCancel={vi.fn()}
      />,
    )
    const confirmButton = screen.getByRole('button', { name: en.confirm })
    expect(confirmButton).toBeDisabled()
    fireEvent.change(screen.getByRole('textbox', { name: /Reason/i }), { target: { value: 'Schedule conflict' } })
    expect(confirmButton).not.toBeDisabled()
  })
})
