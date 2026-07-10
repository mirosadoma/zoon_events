import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import CredentialDetailPage from '@/pages/tenant/credentials/Detail'

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

const { toastMock, reloadMock } = vi.hoisted(() => ({
  toastMock: vi.fn(),
  reloadMock: vi.fn(),
}))

vi.mock('@/hooks/useToast', () => ({
  useToast: () => ({ toast: toastMock }),
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { reload: reloadMock },
}))

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@/components/layout/PermissionGate', () => ({
  default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

describe('credential actions', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
    vi.stubGlobal('crypto', { randomUUID: () => 'test-idempotency-key' })
    toastMock.mockReset()
    reloadMock.mockReset()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  function renderPage(status: string = 'active') {
    return render(
      <CredentialDetailPage
        tenantId="ten_1"
        event={{ id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }}
        credential={{
          id: 'cred_1',
          code: 'ABCD1234',
          attendee_id: 'attendee_1',
          status,
          issued_at: '2026-07-01T10:00:00Z',
        }}
      />,
    )
  }

  it('calls revoke endpoint with idempotency key and reason', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: true,
      json: async () => ({ data: { id: 'cred_1', status: 'revoked' } }),
    } as Response)

    renderPage('active')

    fireEvent.click(screen.getByRole('button', { name: 'Revoke credential' }))
    fireEvent.change(screen.getByLabelText('Reason'), { target: { value: 'Lost device' } })
    fireEvent.click(screen.getByRole('button', { name: 'Confirm revoke' }))

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        '/api/v1/tenant/events/evt_1/credentials/cred_1/revoke',
        expect.objectContaining({
          method: 'POST',
          credentials: 'include',
          body: JSON.stringify({ reason: 'Lost device' }),
          headers: expect.objectContaining({
            'X-Tenant-ID': 'ten_1',
            'Idempotency-Key': 'test-idempotency-key',
          }),
        }),
      )
    })
    expect(toastMock).toHaveBeenCalledWith('Credential revoked.', 'success')
    expect(reloadMock).toHaveBeenCalled()
  })

  it('calls reissue endpoint with idempotency key and reason', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: true,
      json: async () => ({ data: { id: 'cred_2', status: 'active' } }),
    } as Response)

    renderPage('revoked')

    fireEvent.click(screen.getByRole('button', { name: 'Reissue credential' }))
    fireEvent.change(screen.getByLabelText('Reason'), { target: { value: 'Badge damaged' } })
    fireEvent.click(screen.getByRole('button', { name: 'Reissue' }))

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        '/api/v1/tenant/events/evt_1/credentials/cred_1/reissue',
        expect.objectContaining({
          method: 'POST',
          credentials: 'include',
          body: JSON.stringify({ reason: 'Badge damaged' }),
          headers: expect.objectContaining({
            'X-Tenant-ID': 'ten_1',
            'Idempotency-Key': 'test-idempotency-key',
          }),
        }),
      )
    })
    expect(toastMock).toHaveBeenCalledWith('Credential reissued.', 'success')
    expect(reloadMock).toHaveBeenCalled()
  })

  it('shows error toast and avoids reload when revoke fails', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: false,
      json: async () => ({ detail: 'credential_not_active' }),
    } as Response)

    renderPage('active')

    fireEvent.click(screen.getByRole('button', { name: 'Revoke credential' }))
    fireEvent.change(screen.getByLabelText('Reason'), { target: { value: 'Lost device' } })
    fireEvent.click(screen.getByRole('button', { name: 'Confirm revoke' }))

    await waitFor(() => expect(toastMock).toHaveBeenCalledWith('credential_not_active', 'error'))
    expect(reloadMock).not.toHaveBeenCalled()
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })
})
