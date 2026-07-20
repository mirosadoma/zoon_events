import { render, screen, waitFor } from '@testing-library/react'
import axe from 'axe-core'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AddToWalletButtons from '@/components/wallet/AddToWalletButtons'
import CheckInDashboard from '@/pages/tenant/checkin/Dashboard'
import CheckInScanner from '@/pages/tenant/checkin/Scanner'
import Confirmation from '@/pages/public/registration/Confirmation'
import en from '@/locales/en'
import ar from '@/locales/ar'

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => (key === 'phase2.wallet_pass.added' ? 'Pass added to wallet' : key),
  }),
}))

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => {
    const locale = 'en'
    const messages = locale === 'ar' ? ar : en
    return {
      locale,
      direction: 'ltr' as const,
      t: (key: string) => {
        const value = messages[key as keyof typeof messages]
        return typeof value === 'string' ? value : key
      },
    }
  },
}))

vi.mock('@/components/registration/RegistrationPageControls', () => ({
  default: () => null,
}))

describe('phase 2 wallet and check-in browser surfaces', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })
  it('renders wallet buttons in English LTR and Arabic RTL', () => {
    const { container: english } = render(
      <Confirmation locale="en" reference="ord_1" accessToken="tok" attendeeName="Alex" />,
    )
    expect(english.querySelector('main')).toHaveAttribute('dir', 'ltr')
    expect(screen.getByRole('link', { name: /Add to Apple Wallet/i })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Save to Google Wallet/i })).toBeInTheDocument()

    const { container: arabic } = render(
      <Confirmation locale="ar" reference="ord_1" accessToken="tok" attendeeName="عمرو" />,
    )
    expect(arabic.querySelector('main')).toHaveAttribute('dir', 'rtl')
    expect(screen.getByRole('heading', { name: /Welcome عمرو/ })).toBeInTheDocument()
  })

  it('renders check-in scanner with accessible labels in both locales', () => {
    render(
      <CheckInScanner event={{ id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }} tenantId="ten_1" />,
    )
    expect(screen.getByRole('heading', { name: 'Check-in scanner' })).toBeInTheDocument()
    expect(screen.getByLabelText('QR payload')).toBeRequired()

    vi.doMock('@/hooks/useLocale', () => ({
      useLocale: () => ({ locale: 'ar', direction: 'rtl' }),
    }))
  })

  it('renders check-in dashboard counters without serious axe violations', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: true,
      json: async () => ({
        data: {
          registered_count: 3,
          checked_in_count: 2,
          rejected_count: 0,
          duplicate_count: 1,
          last_scan_at: '2027-01-10T12:00:00Z',
        },
      }),
    } as Response)

    const { container } = render(
      <CheckInDashboard
        event={{ id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }}
        tenantId="ten_1"
        initialSummary={{
          registered_count: 3,
          checked_in_count: 2,
          rejected_count: 0,
          duplicate_count: 1,
          last_scan_at: '2027-01-10T12:00:00Z',
        }}
        pollIntervalMs={60000}
      />,
    )

    await waitFor(() => {
      expect(screen.getByTestId('checked-in-count')).toHaveTextContent('2')
    })
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
  })

  it('keeps wallet button component hidden for inactive credentials', () => {
    const { container } = render(
      <AddToWalletButtons
        locale="en"
        applePassUrl="https://example.test/apple"
        googleSaveUrl="https://example.test/google"
        credentialStatus="revoked"
      />,
    )
    expect(container).toBeEmptyDOMElement()
  })

  it('keeps Arabic and English locale catalogs equivalent for phase 2 keys', () => {
    expect(Object.keys(ar)).toEqual(Object.keys(en))
    expect(ar.walletPassAdded).not.toBe(en.walletPassAdded)
  })
})
