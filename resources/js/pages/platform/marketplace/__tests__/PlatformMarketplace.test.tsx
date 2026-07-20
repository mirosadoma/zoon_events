import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import en from '@/locales/en'
import PlatformMarketplaceIndex from '@/pages/platform/marketplace/Index'
import PlatformDisputeShow from '@/pages/platform/marketplace/Disputes/Show'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { get: vi.fn() },
  usePage: () => ({ props: { locale: 'en', direction: 'ltr', can: { 'platform.marketplace.disputes.manage': true } } }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => (en as unknown as Record<string, string>)[key] ?? key,
    localizedPath: (path: string) => `/en${path}`,
  }),
}))

vi.mock('@/components/layout/PermissionGate', () => ({
  default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

describe('PlatformMarketplace', () => {
  it('renders platform marketplace empty state', () => {
    render(<PlatformMarketplaceIndex rows={[]} />)
    expect(screen.getByRole('heading', { name: en.platformMarketplace })).toBeInTheDocument()
  })

  it('renders platform dispute detail with review actions', () => {
    render(
      <PlatformDisputeShow
        dispute={{
          id: 'disp_1',
          status: 'open',
          reason: 'Amount mismatch',
          owner_display_name: 'Owner Co',
          organizer_display_name: 'Organizer Co',
          venue_name: { en: 'Hall A', ar: 'قاعة أ' },
          event_name: { en: 'Summit', ar: 'القمة' },
          timeline: [],
          platform_notes: [],
        }}
      />,
    )
    expect(screen.getByRole('heading', { level: 1, name: en.platformDisputeDetails })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: en.startReview })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: en.resolveDispute })).toBeInTheDocument()
  })
})
