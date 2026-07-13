import { render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import QrCameraScanner from '@/components/checkin/QrCameraScanner'

const scannerMocks = vi.hoisted(() => ({
  construct: vi.fn(),
  getCameras: vi.fn().mockResolvedValue([{ id: 'rear-camera' }]),
  start: vi.fn().mockResolvedValue(undefined),
  stop: vi.fn().mockResolvedValue(undefined),
  clear: vi.fn().mockResolvedValue(undefined),
  applyVideoConstraints: vi.fn().mockResolvedValue(undefined),
}))

vi.mock('html5-qrcode', () => ({
  Html5QrcodeSupportedFormats: { QR_CODE: 0 },
  Html5Qrcode: class {
    static getCameras = scannerMocks.getCameras

    constructor(containerId: string) {
      scannerMocks.construct(containerId)
    }

    start = scannerMocks.start
    stop = scannerMocks.stop
    clear = scannerMocks.clear
    applyVideoConstraints = scannerMocks.applyVideoConstraints
  },
}))

describe('QR camera scanner', () => {
  beforeEach(() => {
    scannerMocks.construct.mockClear()
    scannerMocks.getCameras.mockClear()
    scannerMocks.start.mockClear()
    scannerMocks.stop.mockClear()
    scannerMocks.clear.mockClear()
    scannerMocks.applyVideoConstraints.mockClear()
    scannerMocks.getCameras.mockResolvedValue([{ id: 'rear-camera' }])
    scannerMocks.start.mockResolvedValue(undefined)
    scannerMocks.stop.mockResolvedValue(undefined)
    scannerMocks.clear.mockImplementation(() => undefined)
    scannerMocks.applyVideoConstraints.mockResolvedValue(undefined)
  })

  it('does not restart when callback props change', async () => {
    const { rerender } = render(
      <QrCameraScanner
        active
        onScan={vi.fn()}
        unavailableLabel="Camera access is unavailable on this device or browser."
        startingLabel="Starting camera…"
      />,
    )

    await waitFor(() => {
      expect(scannerMocks.start).toHaveBeenCalledTimes(1)
    })

    rerender(
      <QrCameraScanner
        active
        onScan={vi.fn()}
        unavailableLabel="Camera access is unavailable on this device or browser."
        startingLabel="Starting camera…"
      />,
    )

    await waitFor(() => {
      expect(scannerMocks.construct).toHaveBeenCalledTimes(1)
      expect(scannerMocks.start).toHaveBeenCalledTimes(1)
    })
  })

  it('shows an unavailable message on the black screen when no camera exists', async () => {
    scannerMocks.getCameras.mockResolvedValue([])

    render(
      <QrCameraScanner
        active
        onScan={vi.fn()}
        unavailableLabel="Camera access is unavailable on this device or browser."
        startingLabel="Starting camera…"
      />,
    )

    await waitFor(() => {
      expect(screen.getByText('Camera access is unavailable on this device or browser.')).toBeInTheDocument()
    })
  })

  it('fills scanned values through onScan when a QR code is read', async () => {
    const onScan = vi.fn()
    const decodeHandlers: Array<(value: string) => void> = []

    scannerMocks.start.mockImplementation(async (...args: unknown[]) => {
      decodeHandlers.push(args[2] as (value: string) => void)
    })

    render(
      <QrCameraScanner
        active
        onScan={onScan}
        unavailableLabel="Camera access is unavailable on this device or browser."
        startingLabel="Starting camera…"
      />,
    )

    await waitFor(() => {
      expect(decodeHandlers).toHaveLength(1)
    })

    decodeHandlers[0]('scanned-token')

    expect(onScan).toHaveBeenCalledWith('scanned-token')
  })
})
