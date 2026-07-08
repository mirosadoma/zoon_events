import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import ReasonModal from '@/components/modals/ReasonModal'

describe('ReasonModal', () => {
  it('requires a reason before confirm', () => {
    const onConfirm = vi.fn()

    render(
      <ReasonModal
        open
        title="Revoke credential"
        message="Provide a reason."
        onConfirm={onConfirm}
        onCancel={() => undefined}
      />,
    )

    const confirm = screen.getByRole('button', { name: 'Confirm' })
    expect(confirm).toBeDisabled()

    fireEvent.change(screen.getByLabelText('Reason'), { target: { value: 'Lost device' } })
    fireEvent.click(confirm)

    expect(onConfirm).toHaveBeenCalledWith('Lost device')
  })
})
