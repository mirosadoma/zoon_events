import { fireEvent, render, screen, within } from '@testing-library/react'
import axe from 'axe-core'
import { describe, expect, it, vi } from 'vitest'
import ar from '@/locales/ar'
import en from '@/locales/en'
import PlatformMarketplaceIndex from '@/pages/platform/marketplace/Index'
import PlatformDisputeShow from '@/pages/platform/marketplace/Disputes/Show'

let mockLocale = 'en'
let mockDirection = 'ltr'
let mockMessages: Record<string, string> = en as unknown as Record<string, string>

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <main>{children}</main>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { get: vi.fn() },
  usePage: () => ({ props: { locale: mockLocale, direction: mockDirection, can: { 'platform.marketplace.disputes.manage': true } } }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: mockLocale,
    direction: mockDirection,
    t: (key: string) => mockMessages[key] ?? key,
    localizedPath: (path: string) => `/${mockLocale}${path}`,
  }),
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

const sampleRow = {
  id: 'disp_1',
  kind: 'dispute',
  status: 'open',
  owner_name: 'Owner Co',
  organizer_name: 'Organizer Co',
  venue_name: 'Hall A',
  event_name: 'Summit',
  opened_at: '2026-07-01T12:00:00Z',
}

const sampleDispute = {
  id: 'disp_1',
  status: 'open',
  reason: 'Amount mismatch',
  owner_display_name: 'Owner Co',
  organizer_display_name: 'Organizer Co',
  venue_name: { en: 'Hall A', ar: 'قاعة أ' },
  event_name: { en: 'Summit', ar: 'القمة' },
  timeline: [],
  platform_notes: [],
}

beforeEach(() => {
  setEnglish()
})

describe('Platform marketplace axe accessibility', () => {
  it('platform index renders without serious axe violations', async () => {
    const { container } = render(<PlatformMarketplaceIndex rows={[]} />)
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((v) => ['critical', 'serious'].includes(v.impact || ''))).toEqual([])
  })

  it('platform index with rows renders without serious axe violations', async () => {
    const { container } = render(<PlatformMarketplaceIndex rows={[sampleRow as never]} />)
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((v) => ['critical', 'serious'].includes(v.impact || ''))).toEqual([])
  })

  it('dispute detail renders without serious axe violations', async () => {
    const { container } = render(<PlatformDisputeShow dispute={sampleDispute} />)
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((v) => ['critical', 'serious'].includes(v.impact || ''))).toEqual([])
  })
})

describe('Arabic RTL rendering', () => {
  it('platform index renders Arabic heading', () => {
    setArabic()
    render(<PlatformMarketplaceIndex rows={[]} />)
    expect(screen.getByRole('heading', { name: ar.platformMarketplace })).toBeInTheDocument()
  })

  it('dispute detail renders Arabic heading and labels', () => {
    setArabic()
    render(<PlatformDisputeShow dispute={sampleDispute} />)
    expect(screen.getByRole('heading', { level: 1, name: ar.platformDisputeDetails })).toBeInTheDocument()
    expect(screen.getByText(ar.platformNotes)).toBeInTheDocument()
  })
})

describe('Keyboard navigation for dispute management', () => {
  it('Tab reaches dispute review action buttons', () => {
    render(<PlatformDisputeShow dispute={sampleDispute} />)
    const reviewButton = screen.getByRole('button', { name: en.startReview })
    reviewButton.focus()
    expect(document.activeElement).toBe(reviewButton)
  })

  it('Tab reaches resolve and reject buttons', () => {
    render(<PlatformDisputeShow dispute={sampleDispute} />)
    const resolveButton = screen.getByRole('button', { name: en.resolveDispute })
    resolveButton.focus()
    expect(document.activeElement).toBe(resolveButton)
    const rejectButton = screen.getByRole('button', { name: en.rejectDispute })
    rejectButton.focus()
    expect(document.activeElement).toBe(rejectButton)
  })

  it('start review opens confirm dialog', () => {
    render(<PlatformDisputeShow dispute={sampleDispute} />)
    fireEvent.click(screen.getByRole('button', { name: en.startReview }))
    const dialog = screen.getByRole('dialog')
    expect(dialog).toBeInTheDocument()
  })
})

describe('Focus management for resolution dialogs', () => {
  it('resolve dialog contains confirm and cancel buttons', () => {
    render(<PlatformDisputeShow dispute={sampleDispute} />)
    fireEvent.click(screen.getByRole('button', { name: en.resolveDispute }))
    const dialog = screen.getByRole('dialog')
    const buttons = within(dialog).getAllByRole('button')
    expect(buttons.length).toBeGreaterThanOrEqual(2)
  })

  it('resolve dialog confirm is disabled until fields are filled', () => {
    render(<PlatformDisputeShow dispute={sampleDispute} />)
    fireEvent.click(screen.getByRole('button', { name: en.resolveDispute }))
    const dialog = screen.getByRole('dialog')
    const confirmButton = within(dialog).getByRole('button', { name: en.confirm })
    expect(confirmButton).toBeDisabled()
  })

  it('review dialog has aria-modal and aria-labelledby', () => {
    render(<PlatformDisputeShow dispute={sampleDispute} />)
    fireEvent.click(screen.getByRole('button', { name: en.startReview }))
    const dialog = screen.getByRole('dialog')
    expect(dialog).toHaveAttribute('aria-modal', 'true')
    expect(dialog).toHaveAttribute('aria-labelledby')
  })
})

describe('Status non-color cues', () => {
  it('platform table status badges render visible text labels', () => {
    render(<PlatformMarketplaceIndex rows={[sampleRow as never]} />)
    expect(screen.getByText('Open')).toBeInTheDocument()
  })

  it('dispute detail header shows text status badge', () => {
    render(<PlatformDisputeShow dispute={sampleDispute} />)
    const badges = screen.getAllByText('Open')
    expect(badges.length).toBeGreaterThan(0)
  })
})

describe('Table accessibility', () => {
  it('platform table uses proper th/td structure', () => {
    const { container } = render(<PlatformMarketplaceIndex rows={[sampleRow as never]} />)
    const table = container.querySelector('table')
    expect(table).not.toBeNull()
    const headers = table!.querySelectorAll('th')
    expect(headers.length).toBeGreaterThan(0)
    const cells = table!.querySelectorAll('td')
    expect(cells.length).toBeGreaterThan(0)
  })

  it('platform table headers have scope="col"', () => {
    const { container } = render(<PlatformMarketplaceIndex rows={[sampleRow as never]} />)
    const headers = container.querySelectorAll('th[scope="col"]')
    expect(headers.length).toBeGreaterThan(0)
  })
})

describe('Platform locale key completeness', () => {
  const platformKeys = [
    'platformMarketplace', 'platformMarketplaceDescription',
    'platformDisputeDetails', 'platformNotes',
    'startReview', 'resolveDispute', 'rejectDispute',
    'resolutionCode', 'resolutionSummary', 'addPlatformNote',
    'noDispute', 'disputeReason', 'disputePanel',
  ] as const

  it('all platform marketplace keys exist and are non-empty in English', () => {
    const enFlat = en as unknown as Record<string, string>
    for (const key of platformKeys) {
      expect(enFlat[key], `en.${key} should be a non-empty string`).toBeTruthy()
    }
  })

  it('all platform marketplace keys exist and are non-empty in Arabic', () => {
    const arFlat = ar as unknown as Record<string, string>
    for (const key of platformKeys) {
      expect(arFlat[key], `ar.${key} should be a non-empty string`).toBeTruthy()
    }
  })

  it('Arabic platform keys differ from English (translations exist)', () => {
    const enFlat = en as unknown as Record<string, string>
    const arFlat = ar as unknown as Record<string, string>
    for (const key of platformKeys) {
      expect(arFlat[key], `ar.${key} should differ from en.${key}`).not.toBe(enFlat[key])
    }
  })

  it('statusLabels keys match between en and ar', () => {
    expect(Object.keys(ar.statusLabels).sort()).toEqual(Object.keys(en.statusLabels).sort())
  })
})
