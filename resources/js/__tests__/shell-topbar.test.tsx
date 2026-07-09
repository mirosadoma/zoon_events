import { render, screen } from '@testing-library/react'
import { usePage } from '@inertiajs/react'
import type { ReactElement } from 'react'
import { describe, expect, it, vi } from 'vitest'
import NotificationDropdown from '@/components/layout/NotificationDropdown'
import SearchCommand from '@/components/layout/SearchCommand'
import Sidebar from '@/components/layout/Sidebar'
import { ShellLayoutProvider } from '@/contexts/ShellLayoutContext'

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  usePage: vi.fn(),
  router: { visit: vi.fn() },
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

function renderWithShell(ui: ReactElement) {
  return render(<ShellLayoutProvider>{ui}</ShellLayoutProvider>)
}

describe('shell topbar and nav', () => {
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

    renderWithShell(<Sidebar />)

    expect(screen.getByRole('link', { name: 'Overview' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Tenants' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Users' })).not.toBeInTheDocument()
  })

  it('renders notification empty state', () => {
    render(<NotificationDropdown />)
    expect(screen.getByRole('button', { name: 'Notifications' })).toBeInTheDocument()
  })

  it('renders search command placeholder', () => {
    render(<SearchCommand />)
    expect(screen.getByRole('searchbox', { name: /search or type command/i })).toBeInTheDocument()
  })
})
