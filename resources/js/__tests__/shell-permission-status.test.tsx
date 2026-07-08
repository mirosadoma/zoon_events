import { render, screen } from '@testing-library/react'
import { usePage } from '@inertiajs/react'
import { describe, expect, it, vi } from 'vitest'
import PermissionGate from '@/components/layout/PermissionGate'
import StatusBadge from '@/components/status/StatusBadge'

vi.mock('@inertiajs/react', () => ({
  usePage: vi.fn(),
}))

describe('permission gate and status badge', () => {
  it('hides gated content when permission is denied', () => {
    vi.mocked(usePage).mockReturnValue({ props: { can: { 'event.manage': false } } } as unknown as ReturnType<typeof usePage>)

    render(
      <PermissionGate permission="event.manage" fallback={<span>Hidden</span>}>
        <span>Visible</span>
      </PermissionGate>,
    )

    expect(screen.getByText('Hidden')).toBeInTheDocument()
    expect(screen.queryByText('Visible')).not.toBeInTheDocument()
  })

  it('renders status badge variants', () => {
    render(<StatusBadge status="published" />)
    expect(screen.getByText('published')).toBeInTheDocument()
  })
})
