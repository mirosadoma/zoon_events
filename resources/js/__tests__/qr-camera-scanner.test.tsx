import { render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import QrCameraScanner from '@/components/checkin/QrCameraScanner'

const scannerMocks = vi.hoisted(() => ({
  construct: vi.fn(),
  getCameras: vi.fn().mockResolvedValue([{ id: 'rear-camera' }]),
  start: vi.fn().mockResolvedValue(undefined),
  stop: vi.fn().mockResolvedValue(undefined),
  clear: vi.fn().mockResolvedValue(undefined),
}))

vi.mock('html5-qrcode', () => ({
  Html5Qrcode: class {
    static getCameras = scannerMocks.getCameras

    constructor(containerId: string) {
      scannerMocks.construct(containerId)
    }

    start = scannerMocks.start
    stop = scannerMocks.stop
    clear = scannerMocks.clear
  },
}))

describe('QR camera scanner', () => {
  beforeEach(() => {
    scannerMocks.construct.mockClear()
    scannerMocks.getCameras.mockClear()
    scannerMocks.start.mockClear()
    scannerMocks.stop.mockClear()
    scannerMocks.clear.mockClear()
    scannerMocks.getCameras.mockResolvedValue([{ id: 'rear-camera' }])
    scannerMocks.start.mockResolvedValue(undefined)
    scannerMocks.stop.mockResolvedValue(undefined)
    scannerMocks.clear.mockImplementation(() => undefined)
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
    let decodeHandler: ((value: string) => void) | null = null

    scannerMocks.start.mockImplementation(async (_cameraId, _config, onSuccess) => {
      decodeHandler = onSuccess
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
      expect(decodeHandler).not.toBeNull()
    })

    decodeHandler?.('scanned-token')

    expect(onScan).toHaveBeenCalledWith('scanned-token')
  })
})
