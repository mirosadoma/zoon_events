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
      <Confirmation locale="en" reference="ord_1" accessToken="tok" />,
    )
    expect(english.querySelector('main')).toHaveAttribute('dir', 'ltr')
    expect(screen.getAllByRole('link')).toHaveLength(2)

    const { container: arabic } = render(
      <Confirmation locale="ar" reference="ord_1" accessToken="tok" />,
    )
    expect(arabic.querySelector('main')).toHaveAttribute('dir', 'rtl')
    expect(screen.getByRole('heading', { name: 'اكتمل التسجيل' })).toBeInTheDocument()
  })

  it('renders check-in scanner with accessible labels in both locales', () => {
    const { container: english } = render(
      <CheckInScanner eventId="evt_1" tenantId="ten_1" locale="en" />,
    )
    expect(english.querySelector('main')).toHaveAttribute('dir', 'ltr')
    expect(screen.getByRole('heading', { name: 'Check-in scanner' })).toBeInTheDocument()
    expect(screen.getByLabelText('QR payload')).toBeRequired()

    const { container: arabic } = render(
      <CheckInScanner eventId="evt_1" tenantId="ten_1" locale="ar" />,
    )
    expect(arabic.querySelector('main')).toHaveAttribute('dir', 'rtl')
    expect(screen.getByRole('heading', { name: 'ماسح تسجيل الحضور' })).toBeInTheDocument()
    expect(screen.getByLabelText('حمولة رمز الاستجابة السريعة')).toBeRequired()
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
        eventId="evt_1"
        tenantId="ten_1"
        locale="en"
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
