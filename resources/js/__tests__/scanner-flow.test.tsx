import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import CheckInScanner from '@/pages/tenant/checkin/Scanner'
import { ApiFetchError } from '@/lib/apiFetch'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  usePage: () => ({ props: {} }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => key,
  }),
}))

vi.mock('@/components/checkin/QrCameraScanner', () => ({
  default: ({
    onScan,
    unavailableLabel,
  }: {
    onScan: (value: string) => void
    unavailableLabel: string
  }) => (
    <section aria-label="QR camera scanner">
      <button type="button" onClick={() => onScan('camera-token')}>Mock camera scan</button>
      <p>{unavailableLabel}</p>
    </section>
  ),
}))

const apiFetchMock = vi.fn()

vi.mock('@/lib/apiFetch', async () => {
  const actual = await vi.importActual<typeof import('@/lib/apiFetch')>('@/lib/apiFetch')

  return {
    ...actual,
    apiFetch: (...args: unknown[]) => apiFetchMock(...args),
  }
})

const event = { id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }

describe('scanner flow', () => {
  beforeEach(() => {
    apiFetchMock.mockReset()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('accepts a valid scan and renders the attendee panel', async () => {
    apiFetchMock.mockResolvedValue({
      result: 'accepted',
      reason_code: 'allowed',
      attendee_display_name: 'Synthetic Attendee',
      ticket_type_label: 'General',
    })

    render(<CheckInScanner event={event} tenantId="ten_1" />)

    fireEvent.change(screen.getByRole('textbox', { name: /qrPayload/ }), { target: { value: 'valid-token' } })
    fireEvent.click(screen.getByRole('button', { name: 'submitScan' }))

    await waitFor(() => {
      expect(screen.getByTestId('scan-result-card')).toHaveTextContent('Synthetic Attendee')
      expect(screen.getByTestId('scan-result-card')).toHaveTextContent('General')
      expect(screen.getByTestId('scan-result-card')).toHaveTextContent('Accepted')
    })

    expect(apiFetchMock).toHaveBeenCalledWith('/api/v1/tenant/events/evt_1/scans', expect.objectContaining({
      method: 'POST',
      tenantId: 'ten_1',
      body: {
        qr_payload: 'valid-token',
        scanner_type: 'staff_phone',
      },
    }))
  })

  it('surfaces rejected scans with reason codes', async () => {
    apiFetchMock.mockRejectedValue(new ApiFetchError('Credential revoked', 422, 'credential_revoked'))

    render(<CheckInScanner event={event} tenantId="ten_1" />)

    fireEvent.change(screen.getByRole('textbox', { name: /qrPayload/ }), { target: { value: 'revoked-token' } })
    fireEvent.click(screen.getByRole('button', { name: 'submitScan' }))

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument()
    })
  })

  it('fills the payload input and submits when the camera reads a QR code', async () => {
    apiFetchMock.mockResolvedValue({
      result: 'accepted',
      reason_code: 'allowed',
      attendee_display_name: 'Camera Attendee',
      ticket_type_label: 'VIP',
    })

    render(<CheckInScanner event={event} tenantId="ten_1" />)

    expect(screen.getByRole('region', { name: 'QR camera scanner' })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Mock camera scan' }))

    await waitFor(() => {
      expect(apiFetchMock).toHaveBeenCalledWith('/api/v1/tenant/events/evt_1/scans', expect.objectContaining({
        body: {
          qr_payload: 'camera-token',
          scanner_type: 'staff_phone',
        },
      }))
      expect(screen.getByTestId('scan-result-card')).toHaveTextContent('Camera Attendee')
      expect(screen.getByTestId('scan-result-card')).toHaveTextContent('VIP')
      expect(screen.getByRole('textbox', { name: /qrPayload/ })).toHaveValue('camera-token')
    })
  })

  it('shows the manual entry form beside the camera screen', () => {
    render(<CheckInScanner event={event} tenantId="ten_1" />)

    expect(screen.getByRole('textbox', { name: /qrPayload/ })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'submitScan' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'scanUseCamera' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'scanManualEntry' })).not.toBeInTheDocument()
  })

  it('guards duplicate submit while a scan is in flight', async () => {
    let resolveFetch: (value: unknown) => void = () => undefined
    apiFetchMock.mockImplementation(() => new Promise((resolve) => {
      resolveFetch = resolve
    }))

    render(<CheckInScanner event={event} tenantId="ten_1" />)

    fireEvent.change(screen.getByRole('textbox', { name: /qrPayload/ }), { target: { value: 'valid-token' } })
    fireEvent.click(screen.getByRole('button', { name: 'submitScan' }))

    expect(screen.getByRole('button', { name: 'submitScan' })).toBeDisabled()
    expect(apiFetchMock).toHaveBeenCalledTimes(1)

    resolveFetch({
      result: 'accepted',
      reason_code: 'allowed',
    })

    await waitFor(() => {
      expect(screen.getByTestId('scan-result-card')).toHaveTextContent('Accepted')
    })

    expect(apiFetchMock).toHaveBeenCalledTimes(1)
    expect(screen.getByRole('textbox', { name: /qrPayload/ })).toHaveValue('valid-token')
  })
})
