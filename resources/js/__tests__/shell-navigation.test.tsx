import { render, screen } from '@testing-library/react'
import { usePage } from '@inertiajs/react'
import { describe, expect, it, vi } from 'vitest'
import Sidebar from '@/components/layout/Sidebar'
import { ShellLayoutProvider } from '@/contexts/ShellLayoutContext'

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  usePage: vi.fn(),
}))

describe('shell navigation visibility', () => {
  it('shows only permitted nav items from the can map', () => {
    vi.mocked(usePage).mockReturnValue({
      props: {
        can: {
          'platform.tenant.view': true,
          'platform.user.view': false,
          'event.view': true,
        },
      },
      url: '/en/dashboard',
    } as unknown as ReturnType<typeof usePage>)

    render(
      <ShellLayoutProvider>
        <Sidebar />
      </ShellLayoutProvider>,
    )

    expect(screen.getByRole('link', { name: 'Overview' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Tenants' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Events' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Users' })).not.toBeInTheDocument()
  })
})
