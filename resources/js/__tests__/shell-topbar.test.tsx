import { fireEvent, render, screen, waitFor } from '@testing-library/react'
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
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => key,
    localizedPath: (path: string) => `/en${path}`,
  }),
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

    expect(screen.getByRole('link', { name: 'Main Page' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Tenants' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Users' })).not.toBeInTheDocument()
  })

  it('renders notification empty state', () => {
    render(<NotificationDropdown />)
    expect(screen.getByRole('button', { name: 'Notifications' })).toBeInTheDocument()
  })

  it('renders search command placeholder', () => {
    vi.mocked(usePage).mockReturnValue({
      props: {
        auth: {
          user: { id: '1', name: 'Demo User' },
        },
        can: {
          'event.view': true,
        },
        session: {
          tenant: { id: '1' },
        },
      },
      url: '/en/dashboard',
    } as unknown as ReturnType<typeof usePage>)

    render(<SearchCommand />)
    expect(screen.getByRole('searchbox', { name: 'searchPlaceholder' })).toBeInTheDocument()
  })

  it('requests search results with the query string intact', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        results: [{
          type: 'event',
          id: '1',
          label: 'Zonetec Summit 2026',
          href: '/tenant/events/1',
        }],
      }),
    })

    vi.stubGlobal('fetch', fetchMock)

    vi.mocked(usePage).mockReturnValue({
      props: {
        auth: {
          user: { id: '1', name: 'Demo User' },
        },
        session: {
          tenant: { id: '1' },
        },
      },
      url: '/en/dashboard',
    } as unknown as ReturnType<typeof usePage>)

    render(<SearchCommand />)

    fireEvent.change(screen.getByRole('searchbox', { name: 'searchPlaceholder' }), {
      target: { value: 'Zon' },
    })

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledWith(
        '/en/dashboard/search?q=Zon',
        expect.objectContaining({
          credentials: 'include',
        }),
      )
    })

    await waitFor(() => {
      expect(screen.getByText('Zonetec Summit 2026')).toBeInTheDocument()
    })

    vi.unstubAllGlobals()
  })
})
