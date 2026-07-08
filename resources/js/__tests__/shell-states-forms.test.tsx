import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { EmptyState, ErrorState } from '@/components/feedback/States'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'

describe('shared states and submit loader', () => {
  it('renders empty and error states', () => {
    render(<EmptyState title="No events" detail="Create your first event." />)
    expect(screen.getByRole('heading', { name: 'No events' })).toBeInTheDocument()

    render(<ErrorState title="Failed" detail="Try again." />)
    expect(screen.getByRole('alert')).toBeInTheDocument()
  })

  it('disables submit button while loading and guards duplicate submit', () => {
    const onClick = vi.fn()

    render(<SubmitButtonWithLoader label="Save" loading type="button" onClick={onClick} />)

    const button = screen.getByRole('button', { name: 'Save' })
    expect(button).toBeDisabled()

    render(<SubmitButtonWithLoader label="Publish" type="button" onClick={onClick} />)
    const publish = screen.getByRole('button', { name: 'Publish' })
    fireEvent.click(publish)
    fireEvent.click(publish)
    expect(onClick).toHaveBeenCalledTimes(1)
  })
})
