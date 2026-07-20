import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import en from '@/locales/en'
import DelegationStatusPanel from '@/pages/tenant/marketplace/Components/DelegationStatusPanel'

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => (en as unknown as Record<string, string>)[key] ?? key,
    localizedPath: (path: string) => `/en${path}`,
  }),
}))

describe('DelegationStatus', () => {
  it('renders empty delegation panel', () => {
    render(<DelegationStatusPanel delegation={null} />)
    expect(screen.getByRole('heading', { name: en.delegationStatus })).toBeInTheDocument()
    expect(screen.getByText(en.noOperationalLinks)).toBeInTheDocument()
  })

  it('renders active delegation with countdown region', () => {
    render(
      <DelegationStatusPanel
        delegation={{
          status: 'active',
          expires_at: new Date(Date.now() + 3600000).toISOString(),
          server_timestamp: '2026-07-01T12:00:00Z',
        }}
      />,
    )
    expect(screen.getByText('Active')).toBeInTheDocument()
    expect(screen.getByText(/Local countdown/i)).toBeInTheDocument()
  })
})
