import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import axe from 'axe-core'
import Credentials from '@/pages/tenant/events/Credentials'

describe('credential lifecycle UI', () => {
  it('requires a reason and respects lifecycle permissions', () => {
    render(<Credentials canRevoke canReissue={false} />)
    expect(screen.getByRole('button', { name: 'Revoke' })).toBeDisabled()
    fireEvent.change(screen.getByLabelText('Reason'), { target: { value: 'Attendee request' } })
    expect(screen.getByRole('button', { name: 'Revoke' })).toBeEnabled()
    expect(screen.queryByRole('button', { name: 'Reissue' })).not.toBeInTheDocument()
  })

  it('renders the Arabic lifecycle dialog in RTL accessibly', async () => {
    const { container } = render(<Credentials locale="ar" canRevoke canReissue />)
    expect(screen.getByRole('heading', { name: 'الاعتمادات' })).toBeInTheDocument()
    expect(screen.getByLabelText('السبب')).toBeRequired()
    expect(container.querySelector('main')).toHaveAttribute('dir', 'rtl')
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
  })
})
