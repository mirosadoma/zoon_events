import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import IdentityReviewQueuePage from '@/pages/tenant/identity/ReviewQueue'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

const { reloadMock, apiFetchMock } = vi.hoisted(() => ({
  reloadMock: vi.fn(),
  apiFetchMock: vi.fn(),
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { reload: reloadMock },
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr', t: (key: string) => key }),
}))

vi.mock('@/hooks/useToast', () => ({
  useToast: () => ({ toast: vi.fn() }),
}))

vi.mock('@/components/layout/PermissionGate', () => ({
  default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

vi.mock('@/lib/apiFetch', () => ({
  apiFetch: apiFetchMock,
  ApiFetchError: class ApiFetchError extends Error {
    constructor(message: string) {
      super(message)
      this.name = 'ApiFetchError'
    }
  },
}))

describe('identity review queue page', () => {
  beforeEach(() => {
    apiFetchMock.mockReset()
    reloadMock.mockReset()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  const items = [
    {
      id: 'ver_1',
      attendee_id: 'att_12345678',
      method: 'face_capture',
      status: 'pending',
      provider_reference: 'face-att_1',
      submitted_at: '2026-07-08T10:00:00Z',
    },
  ]

  it('renders pending review items with approve and reject controls', () => {
    render(
      <IdentityReviewQueuePage
        tenantId="ten_1"
        event={{ id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }}
        items={items}
        canReview
      />,
    )

    expect(screen.getByRole('heading', { name: 'identityReviewQueue' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'identityReviewApprove' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'identityReviewReject' })).toBeInTheDocument()
  })

  it('opens reason modal on reject and posts review decision', async () => {
    apiFetchMock.mockResolvedValue({ status: 'rejected' })

    render(
      <IdentityReviewQueuePage
        tenantId="ten_1"
        event={{ id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }}
        items={items}
        canReview
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'identityReviewReject' }))
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'blurry capture' } })
    fireEvent.click(screen.getAllByRole('button', { name: 'identityReviewReject' })[1])

    await waitFor(() => {
      expect(apiFetchMock).toHaveBeenCalledWith(
        '/api/v1/tenant/events/evt_1/identity/verifications/ver_1/review',
        expect.objectContaining({
          method: 'POST',
          tenantId: 'ten_1',
          idempotency: true,
          body: { decision: 'reject', reason: 'blurry capture' },
        }),
      )
    })
  })
})
