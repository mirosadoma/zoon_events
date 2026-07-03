import { render, screen } from '@testing-library/react'
import type { PropsWithChildren } from 'react'
import FoundationDashboard from '@/pages/FoundationDashboard'

vi.mock('@/layouts/FoundationLayout', () => ({
  default: ({ children }: PropsWithChildren) => <div>{children}</div>,
}))

describe('FoundationDashboard', () => {
  it('renders core foundation pillars', () => {
    render(<FoundationDashboard />)

    expect(screen.getByText(/Project foundation and governance/)).toBeInTheDocument()
    expect(screen.getByText('Isolation first')).toBeInTheDocument()
    expect(screen.getByText('Least privilege')).toBeInTheDocument()
  })
})
