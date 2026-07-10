import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import CheckInScanner from '@/pages/tenant/checkin/Scanner'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

const event = { id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }

describe('scanner flow', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('accepts a valid scan and renders the result card', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: true,
      json: async () => ({
        data: {
          result: 'accepted',
          reason_code: 'allowed',
          attendee_display_name: 'Synthetic Attendee',
          ticket_type_label: 'General',
        },
      }),
    } as Response)

    render(<CheckInScanner event={event} tenantId="ten_1" />)

    fireEvent.change(screen.getByLabelText('QR payload'), { target: { value: 'valid-token' } })
    fireEvent.click(screen.getByRole('button', { name: 'Submit scan' }))

    await waitFor(() => {
      expect(screen.getByTestId('scan-result-card')).toHaveTextContent('accepted')
    })
  })

  it('surfaces rejected scans with reason codes', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: false,
      json: async () => ({ code: 'credential_revoked' }),
    } as Response)

    render(<CheckInScanner event={event} tenantId="ten_1" />)

    fireEvent.change(screen.getByLabelText('QR payload'), { target: { value: 'revoked-token' } })
    fireEvent.click(screen.getByRole('button', { name: 'Submit scan' }))

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('credential_revoked')
    })
  })

  it('guards duplicate submit while a scan is in flight', async () => {
    let resolveFetch: (value: Response) => void = () => undefined
    vi.mocked(fetch).mockImplementation(() => new Promise((resolve) => {
      resolveFetch = resolve
    }))

    render(<CheckInScanner event={event} tenantId="ten_1" />)

    fireEvent.change(screen.getByLabelText('QR payload'), { target: { value: 'valid-token' } })
    fireEvent.click(screen.getByRole('button', { name: 'Submit scan' }))

    expect(screen.getByRole('button', { name: 'Submit scan' })).toBeDisabled()
    expect(fetch).toHaveBeenCalledTimes(1)

    resolveFetch({
      ok: true,
      json: async () => ({ data: { result: 'accepted', reason_code: 'allowed' } }),
    } as Response)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Submit scan' })).not.toBeDisabled()
    })
  })
})
