import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import IdentityVerifyPage from '@/pages/public/identity/Verify'

const disclosures = {
  what: { en: 'Government identity attributes.', ar: 'سمات الهوية الحكومية.' },
  why: { en: 'To confirm attendee identity.', ar: 'لتأكيد هوية الحاضر.' },
  retention: { en: 'Retained per policy.', ar: 'محفوظ وفق السياسة.' },
  who: { en: 'Authorized organizers.', ar: 'المنظمون المرخصون.' },
  processing_mode: { en: 'On-premise processing.', ar: 'معالجة محلية.' },
  deletion: { en: 'Request deletion where permitted.', ar: 'اطلب الحذف حيث يسمح.' },
}

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr', t: (key: string) => key }),
}))

describe('identity verify page', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
    vi.stubGlobal('crypto', { randomUUID: () => 'identity-idempotency-key' })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  function renderPage() {
    return render(
      <IdentityVerifyPage
        locale="en"
        event={{ id: 'evt_1', slug: 'summit', name: { en: 'Summit', ar: 'القمة' } }}
        attendeeId="att_1"
        accessToken="tok_1"
        noticeVersion="identity-v1"
        residencyMode="on_premise"
        disclosures={disclosures}
      />,
    )
  }

  it('renders consent notice disclosures before capture', () => {
    renderPage()

    expect(screen.getByText('identityConsentTitle')).toBeInTheDocument()
    expect(screen.getByText('identityConsentWhat')).toBeInTheDocument()
    expect(screen.getByText('Government identity attributes.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'identityConsentAccept' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'identityConsentDecline' })).toBeInTheDocument()
  })

  it('starts verification after consent and shows verified result', async () => {
    vi.mocked(fetch)
      .mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: async () => ({ data: { consented: true, status: 'pending' } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        status: 202,
        json: async () => ({
          data: {
            provider_reference: 'gov-att_1',
            verification: { status: 'gov_verified' },
          },
        }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            status: 'gov_verified',
            verified_name: 'Mock Verified Attendee',
            verified_nationality: 'SA',
          },
        }),
      } as Response)

    renderPage()
    fireEvent.click(screen.getByRole('button', { name: 'identityConsentAccept' }))

    await waitFor(() => {
      expect(screen.getByText(/gov_verified/)).toBeInTheDocument()
    })

    expect(fetch).toHaveBeenCalledWith(
      '/api/v1/tenant/events/evt_1/attendees/att_1/identity/consent',
      expect.objectContaining({ method: 'POST' }),
    )
    expect(fetch).toHaveBeenCalledWith(
      '/api/v1/tenant/events/evt_1/attendees/att_1/identity/verification',
      expect.objectContaining({ method: 'POST' }),
    )
  })
})
