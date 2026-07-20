import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import en from '@/locales/en'
import StatementsIndex from '@/pages/tenant/marketplace/Statements/Index'
import StatementShow from '@/pages/tenant/marketplace/Statements/Show'
import DisputePanel from '@/pages/tenant/marketplace/Components/DisputePanel'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { get: vi.fn() },
  usePage: () => ({ props: { locale: 'en', direction: 'ltr', can: {} } }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => (en as unknown as Record<string, string>)[key] ?? key,
    localizedPath: (path: string) => `/en${path}`,
  }),
}))

describe('StatementsDisputes', () => {
  it('renders statements empty state with factual notice in description', () => {
    render(<StatementsIndex statements={[]} />)
    expect(screen.getByRole('heading', { name: en.statementsTitle })).toBeInTheDocument()
    expect(screen.getByText(en.statementsDescription)).toBeInTheDocument()
    expect(screen.getByText(en.noStatements)).toBeInTheDocument()
  })

  it('renders statement detail with payment disclaimer', () => {
    render(
      <StatementShow
        statement={{
          id: 'stmt_1',
          rental_id: 'rent_1',
          revision: 1,
          status: 'issued',
          issued_at: '2026-07-02T10:00:00Z',
          currency: 'SAR',
          total_minor: 50000,
        }}
      />,
    )
    expect(screen.getByText(en.statementOnlyNotice)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: en.exportStatementCsv })).toBeInTheDocument()
  })

  it('renders participant dispute panel with open action', () => {
    render(<DisputePanel canOpen onOpenDispute={vi.fn()} />)
    expect(screen.getByRole('button', { name: en.openDispute })).toBeInTheDocument()
  })
})
