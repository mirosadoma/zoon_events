import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import ConfirmModal from '@/components/modals/ConfirmModal'
import ReasonModal from '@/components/modals/ReasonModal'

describe('design system: SubmitButtonWithLoader', () => {
  it('disables while loading', () => {
    render(<SubmitButtonWithLoader label="Save" loading />)
    expect(screen.getByRole('button', { name: /save/i })).toBeDisabled()
  })

  it('guards duplicate clicks', () => {
    const onClick = vi.fn()

    render(<SubmitButtonWithLoader label="Submit" onClick={onClick} type="button" />)
    const button = screen.getByRole('button', { name: /submit/i })

    fireEvent.click(button)
    fireEvent.click(button)

    expect(onClick).toHaveBeenCalledOnce()
  })
})

describe('design system: ConfirmModal', () => {
  it('calls onConfirm when confirmed', () => {
    const onConfirm = vi.fn()
    const onCancel = vi.fn()

    render(
      <ConfirmModal
        open
        title="Publish event"
        message="Are you sure?"
        onConfirm={onConfirm}
        onCancel={onCancel}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }))
    expect(onConfirm).toHaveBeenCalledOnce()
  })
})

describe('design system: ReasonModal', () => {
  it('requires a reason before confirm', () => {
    const onConfirm = vi.fn()

    render(
      <ReasonModal
        open
        title="Revoke credential"
        message="Provide a reason"
        confirmLabel="Revoke"
        onConfirm={onConfirm}
        onCancel={() => {}}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Revoke' }))
    expect(onConfirm).not.toHaveBeenCalled()

    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Lost badge' } })
    fireEvent.click(screen.getByRole('button', { name: 'Revoke' }))
    expect(onConfirm).toHaveBeenCalledWith('Lost badge')
  })
})
