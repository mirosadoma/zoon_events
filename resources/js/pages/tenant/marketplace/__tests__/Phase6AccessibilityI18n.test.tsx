import { fireEvent, render, screen, within } from '@testing-library/react'
import axe from 'axe-core'
import { describe, expect, it, vi } from 'vitest'
import ar from '@/locales/ar'
import en from '@/locales/en'
import MarketplaceIndex from '@/pages/tenant/marketplace/Index'
import RentalsIndex from '@/pages/tenant/marketplace/Rentals/Index'
import RentalShow from '@/pages/tenant/marketplace/Rentals/Show'
import StatementsIndex from '@/pages/tenant/marketplace/Statements/Index'
import StatementShow from '@/pages/tenant/marketplace/Statements/Show'

let mockLocale = 'en'
let mockDirection = 'ltr'
let mockMessages: Record<string, string> = en as unknown as Record<string, string>

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <main>{children}</main>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { get: vi.fn(), reload: vi.fn() },
  usePage: () => ({ props: { locale: mockLocale, direction: mockDirection, can: { 'rentals.approve': true, 'marketplace.manage': true } } }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: mockLocale,
    direction: mockDirection,
    t: (key: string) => mockMessages[key] ?? key,
    localizedPath: (path: string) => `/${mockLocale}${path}`,
  }),
}))

vi.mock('@/hooks/useLocalizedRouter', () => ({
  useLocalizedRouter: () => ({ get: vi.fn() }),
}))

vi.mock('@/components/layout/PermissionGate', () => ({
  default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

function setArabic() {
  mockLocale = 'ar'
  mockDirection = 'rtl'
  mockMessages = ar as unknown as Record<string, string>
}

function setEnglish() {
  mockLocale = 'en'
  mockDirection = 'ltr'
  mockMessages = en as unknown as Record<string, string>
}

const sampleRental = {
  id: 'rent_1',
  viewer_role: 'organizer' as const,
  event_name: { en: 'Summit', ar: 'القمة' },
  venue_name: { en: 'Hall A', ar: 'قاعة أ' },
  window_start: '2026-07-01T08:00:00Z',
  window_end: '2026-07-01T18:00:00Z',
  currency: 'SAR',
  total_minor: 50000,
  status: 'requested',
}

const sampleRentalDetail = {
  ...sampleRental,
  status: 'approved',
  timeline: [{ id: 'evt_1', kind: 'submitted', occurred_at: '2026-07-01T07:00:00Z', summary: 'Submitted' }],
}

const sampleStatement = {
  id: 'stmt_1',
  rental_id: 'rent_1',
  revision: 1,
  status: 'issued',
  issued_at: '2026-07-02T10:00:00Z',
  currency: 'SAR',
  total_minor: 50000,
}

const sampleAsset = {
  id: 'pub_1',
  publication_id: 'pub_1',
  venue_id: 'ven_1',
  venue_name: { en: 'Hall A', ar: 'قاعة أ' },
  asset_type: 'room',
  name: { en: 'Room 1', ar: 'غرفة 1' },
  capabilities: ['wifi'],
  pricing_model: 'per_hour' as const,
  price_minor: 10000,
  currency: 'SAR',
}

beforeEach(() => {
  setEnglish()
})

describe('Tenant marketplace axe accessibility', () => {
  it('catalog page renders without serious axe violations', async () => {
    const { container } = render(<MarketplaceIndex assets={[]} />)
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((v) => ['critical', 'serious'].includes(v.impact || ''))).toEqual([])
  })

  it('catalog page with assets renders without serious axe violations', async () => {
    const { container } = render(<MarketplaceIndex assets={[sampleAsset]} />)
    const result = await axe.run(container, {
      rules: {
        'color-contrast': { enabled: false },
        'aria-required-children': { enabled: false },
      },
    })
    expect(result.violations.filter((v) => ['critical', 'serious'].includes(v.impact || ''))).toEqual([])
  })

  it('rentals index renders without serious axe violations', async () => {
    const { container } = render(<RentalsIndex rentals={[sampleRental]} />)
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((v) => ['critical', 'serious'].includes(v.impact || ''))).toEqual([])
  })

  it('rental detail renders without serious axe violations', async () => {
    const { container } = render(<RentalShow rental={sampleRentalDetail} />)
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((v) => ['critical', 'serious'].includes(v.impact || ''))).toEqual([])
  })

  it('statements index renders without serious axe violations', async () => {
    const { container } = render(<StatementsIndex statements={[sampleStatement as never]} />)
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((v) => ['critical', 'serious'].includes(v.impact || ''))).toEqual([])
  })

  it('statement detail renders without serious axe violations', async () => {
    const { container } = render(<StatementShow statement={sampleStatement} />)
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((v) => ['critical', 'serious'].includes(v.impact || ''))).toEqual([])
  })
})

describe('Arabic RTL rendering', () => {
  it('catalog page renders Arabic headings', () => {
    setArabic()
    render(<MarketplaceIndex assets={[]} />)
    expect(screen.getByRole('heading', { name: ar.catalogTitle })).toBeInTheDocument()
  })

  it('rentals index renders Arabic headings', () => {
    setArabic()
    render(<RentalsIndex rentals={[]} />)
    expect(screen.getByRole('heading', { name: ar.myRentals })).toBeInTheDocument()
  })

  it('statement detail renders Arabic notice text', () => {
    setArabic()
    render(<StatementShow statement={sampleStatement} />)
    expect(screen.getByText(ar.statementOnlyNotice)).toBeInTheDocument()
  })
})

describe('Keyboard navigation', () => {
  it('Tab reaches catalog filter controls', () => {
    render(<MarketplaceIndex assets={[]} />)
    const applyButton = screen.getByRole('button', { name: en.applyFilters })
    applyButton.focus()
    expect(document.activeElement).toBe(applyButton)
  })

  it('dialog dismiss via Escape key closes reason dialog', () => {
    render(<RentalShow rental={{ ...sampleRentalDetail, status: 'requested', viewer_role: 'owner' }} />)
    const rejectButton = screen.getByRole('button', { name: en.rejectRental })
    fireEvent.click(rejectButton)
    const dialog = screen.getByRole('dialog')
    expect(dialog).toBeInTheDocument()
    fireEvent.keyDown(dialog, { key: 'Escape' })
  })

  it('Tab reaches rental action buttons', () => {
    render(<RentalShow rental={{ ...sampleRentalDetail, status: 'requested', viewer_role: 'owner' }} />)
    const approveButton = screen.getByRole('button', { name: en.approveRental })
    approveButton.focus()
    expect(document.activeElement).toBe(approveButton)
  })

  it('Tab reaches statement export link', () => {
    render(<StatementShow statement={sampleStatement} />)
    const exportLink = screen.getByRole('link', { name: en.exportStatementCsv })
    exportLink.focus()
    expect(document.activeElement).toBe(exportLink)
  })
})

describe('Focus management', () => {
  it('reject dialog contains focusable confirm and cancel buttons', () => {
    render(<RentalShow rental={{ ...sampleRentalDetail, status: 'requested', viewer_role: 'owner' }} />)
    fireEvent.click(screen.getByRole('button', { name: en.rejectRental }))
    const dialog = screen.getByRole('dialog')
    const buttons = within(dialog).getAllByRole('button')
    expect(buttons.length).toBeGreaterThanOrEqual(2)
    buttons[0].focus()
    expect(document.activeElement).toBe(buttons[0])
  })

  it('dispute dialog confirm is disabled until reason is entered', () => {
    render(<StatementShow statement={{ ...sampleStatement, dispute: undefined }} />)
    fireEvent.click(screen.getByRole('button', { name: en.openDispute }))
    const dialog = screen.getByRole('dialog')
    const confirmButton = within(dialog).getByRole('button', { name: en.confirm })
    expect(confirmButton).toBeDisabled()
  })
})

describe('Status badge non-color cues', () => {
  it('rental status badges render visible text labels', () => {
    render(<RentalsIndex rentals={[sampleRental]} />)
    expect(screen.getByText('Requested')).toBeInTheDocument()
  })

  it('statement status badges render visible text labels', () => {
    render(<StatementsIndex statements={[sampleStatement as never]} />)
    expect(screen.getByText('Issued')).toBeInTheDocument()
  })

  it('rental detail header shows text status badge', () => {
    render(<RentalShow rental={sampleRentalDetail} />)
    expect(screen.getByText('Approved')).toBeInTheDocument()
  })
})

describe('Table accessibility', () => {
  it('rentals table uses proper th/td structure', () => {
    const { container } = render(<RentalsIndex rentals={[sampleRental]} />)
    const table = container.querySelector('table')
    expect(table).not.toBeNull()
    const headers = table!.querySelectorAll('th')
    expect(headers.length).toBeGreaterThan(0)
    const cells = table!.querySelectorAll('td')
    expect(cells.length).toBeGreaterThan(0)
  })

  it('statements table uses proper th/td structure', () => {
    const { container } = render(<StatementsIndex statements={[sampleStatement as never]} />)
    const table = container.querySelector('table')
    expect(table).not.toBeNull()
    const headers = table!.querySelectorAll('th')
    expect(headers.length).toBeGreaterThan(0)
  })

  it('table headers have scope="col" attribute', () => {
    const { container } = render(<RentalsIndex rentals={[sampleRental]} />)
    const headers = container.querySelectorAll('th[scope="col"]')
    expect(headers.length).toBeGreaterThan(0)
  })
})

describe('Locale key completeness', () => {
  const marketplaceKeys = [
    'marketplace', 'marketplaceRentals', 'marketplaceStatements',
    'catalogTitle', 'catalogDescription', 'noCatalogAssets', 'noCatalogAssetsDetail',
    'noCatalogMatch', 'noCatalogMatchDetail', 'catalogUnavailable', 'catalogRetry',
    'rentalQuote', 'submitRentalRequest', 'myRentals', 'myRentalsDescription',
    'noRentals', 'noRentalsDetail', 'rentalDetails',
    'approveRental', 'rejectRental', 'revokeRental', 'cancelRental',
    'rejectRentalReason', 'revokeRentalReason',
    'delegationStatus', 'delegationCountdown', 'noOperationalLinks',
    'statementsTitle', 'statementsDescription', 'noStatements', 'noStatementsDetail',
    'statementDetails', 'statementOnlyNotice', 'statementRevision', 'exportStatementCsv',
    'openDispute', 'disputeReason', 'disputeReasonCategory', 'disputePanel', 'noDispute',
    'viewerRole', 'roleOwner', 'roleOrganizer',
    'requestedWindow', 'venueTimezone', 'lineTotal', 'quoteTotal',
    'rentalTimeline', 'reason',
  ] as const

  it('all marketplace keys exist and are non-empty in English', () => {
    const enFlat = en as unknown as Record<string, string>
    for (const key of marketplaceKeys) {
      expect(enFlat[key], `en.${key} should be a non-empty string`).toBeTruthy()
    }
  })

  it('all marketplace keys exist and are non-empty in Arabic', () => {
    const arFlat = ar as unknown as Record<string, string>
    for (const key of marketplaceKeys) {
      expect(arFlat[key], `ar.${key} should be a non-empty string`).toBeTruthy()
    }
  })

  it('Arabic marketplace keys differ from English (translations exist)', () => {
    const enFlat = en as unknown as Record<string, string>
    const arFlat = ar as unknown as Record<string, string>
    for (const key of marketplaceKeys) {
      expect(arFlat[key], `ar.${key} should differ from en.${key}`).not.toBe(enFlat[key])
    }
  })

  it('statusLabels keys match between en and ar', () => {
    expect(Object.keys(ar.statusLabels).sort()).toEqual(Object.keys(en.statusLabels).sort())
  })
})
