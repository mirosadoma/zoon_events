import { render, screen } from '@testing-library/react'
import { usePage } from '@inertiajs/react'
import type { ReactElement } from 'react'
import { describe, expect, it, vi } from 'vitest'
import Sidebar from '@/components/layout/Sidebar'
import { ShellLayoutProvider } from '@/contexts/ShellLayoutContext'

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  usePage: vi.fn(),
}))

function renderWithShell(ui: ReactElement) {
  return render(<ShellLayoutProvider>{ui}</ShellLayoutProvider>)
}

describe('shell nav permissions', () => {
  it('hides administration links without membership permissions', () => {
    vi.mocked(usePage).mockReturnValue({
      props: {
        can: {
          'tenant.view': true,
          'event.view': true,
          'membership.view': false,
          'role.view': false,
        },
      },
      url: '/en/dashboard',
    } as unknown as ReturnType<typeof usePage>)

    renderWithShell(<Sidebar />)

    expect(screen.getByRole('link', { name: 'Main Page' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Users' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Roles & permissions' })).not.toBeInTheDocument()
  })
})
