import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { CredentialDialog } from '@/components/credentials/CredentialDialog'

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

vi.mock('@/components/layout/PermissionGate', () => ({
  default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

describe('credential actions', () => {
  it('requires a reason before confirming revoke', () => {
    const onRevoked = vi.fn()

    render(<CredentialDialog status="active" onRevoked={onRevoked} />)

    fireEvent.click(screen.getByRole('button', { name: 'Revoke credential' }))
    expect(screen.getByRole('dialog')).toBeInTheDocument()

    const confirm = screen.getByRole('button', { name: 'Confirm revoke' })
    expect(confirm).toBeDisabled()

    fireEvent.change(screen.getByLabelText('Reason'), { target: { value: 'Lost device' } })
    fireEvent.click(confirm)

    expect(onRevoked).toHaveBeenCalledWith('Lost device')
  })

  it('confirms reissue without a reason', () => {
    const onReissued = vi.fn()

    render(<CredentialDialog status="revoked" onReissued={onReissued} />)

    fireEvent.click(screen.getByRole('button', { name: 'Reissue credential' }))
    fireEvent.click(screen.getByRole('button', { name: 'Reissue' }))

    expect(onReissued).toHaveBeenCalled()
  })
})
