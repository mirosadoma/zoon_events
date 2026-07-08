import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import IdentityRequirementsPage from '@/pages/tenant/identity/Requirements'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

const { reloadMock, usePageMock, toastMock } = vi.hoisted(() => ({
  reloadMock: vi.fn(),
  usePageMock: vi.fn(),
  toastMock: vi.fn(),
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { reload: reloadMock },
  usePage: usePageMock,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr', t: (key: string) => key }),
}))

vi.mock('@/hooks/useToast', () => ({
  useToast: () => ({ toast: toastMock }),
}))

describe('identity requirements page', () => {
  beforeEach(() => {
    usePageMock.mockReturnValue({ props: { can: { 'identity.configure': true } } })
    vi.stubGlobal('fetch', vi.fn())
    vi.stubGlobal('crypto', { randomUUID: () => 'identity-idempotency-key' })
    reloadMock.mockReset()
    toastMock.mockReset()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  function renderPage(canManage: boolean = true) {
    return render(
      <IdentityRequirementsPage
        tenantId="ten_1"
        event={{ id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }}
        ticketTypes={[{ id: 'tkt_1', code: 'VIP', name: { en: 'VIP', ar: 'كبار الشخصيات' } }]}
        requirements={[]}
        canManage={canManage}
      />,
    )
  }

  it('renders requirement forms and permission-gated save controls', () => {
    renderPage(true)

    expect(screen.getByText('identityEventDefaultRule')).toBeInTheDocument()
    expect(screen.getByText('identityTierOverrides')).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'save' }).length).toBeGreaterThan(0)
  })

  it('hides save controls when permission gate denies identity.configure', () => {
    usePageMock.mockReturnValue({ props: { can: { 'identity.configure': false } } })

    renderPage(true)

    expect(screen.queryByRole('button', { name: 'save' })).not.toBeInTheDocument()
  })

  it('sends requirement update request with tenant and idempotency metadata', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: true,
      json: async () => ({
        data: {
          id: 'req_1',
          event_id: 'evt_1',
          ticket_type_id: null,
          level: 'required_before_gate',
          face_fallback_enabled: true,
        },
      }),
    } as Response)

    renderPage(true)

    const levelInput = screen.getAllByLabelText('identityRequirementLevel')[0]
    const fallbackInput = screen.getAllByLabelText('identityFaceFallbackEnabled')[0]
    fireEvent.change(levelInput, { target: { value: 'required_before_gate' } })
    fireEvent.click(fallbackInput)
    fireEvent.submit(levelInput.closest('form')!)

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        '/api/v1/tenant/events/evt_1/identity/requirements',
        expect.objectContaining({
          method: 'PUT',
          credentials: 'include',
          headers: expect.objectContaining({
            'X-Tenant-ID': 'ten_1',
            'Idempotency-Key': 'identity-idempotency-key',
          }),
        }),
      )
    })
    expect(toastMock).toHaveBeenCalledWith('identityRequirementsSaved', 'success')
    expect(reloadMock).toHaveBeenCalled()
  })
})
