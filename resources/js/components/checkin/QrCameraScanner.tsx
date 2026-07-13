import {
  Html5Qrcode,
  Html5QrcodeSupportedFormats,
  type Html5QrcodeCameraScanConfig,
} from 'html5-qrcode'
import { useEffect, useEffectEvent, useId, useRef, useState } from 'react'

type ScanStatus = 'starting' | 'ready' | 'unavailable'

type Props = {
  active: boolean
  onScan: (value: string) => void
  unavailableLabel: string
  startingLabel: string
  className?: string
}

async function disposeScanner(scanner: Html5Qrcode): Promise<void> {
  try {
    await scanner.stop()
  } catch {
    // Scanner may still be starting or already stopped.
  }

  try {
    scanner.clear()
  } catch {
    // Container may already be cleared or detached.
  }
}

function selectBackCamera(cameras: Array<{ id: string; label?: string }>): string | undefined {
  const backCamera = cameras.find((camera) => /back|rear|environment|الخلف/i.test(camera.label ?? ''))

  return backCamera?.id ?? cameras.at(-1)?.id ?? cameras[0]?.id
}

function responsiveQrbox(viewfinderWidth: number, viewfinderHeight: number) {
  const shortestSide = Math.min(viewfinderWidth, viewfinderHeight)
  const size = Math.max(180, Math.min(420, shortestSide - 32, Math.round(shortestSide * 0.72)))

  return { width: size, height: size }
}

const scanConfig: Html5QrcodeCameraScanConfig = {
  fps: 15,
  qrbox: responsiveQrbox,
  disableFlip: true,
  videoConstraints: {
    facingMode: { ideal: 'environment' },
    width: { ideal: 1920 },
    height: { ideal: 1080 },
  },
}

export default function QrCameraScanner({
  active,
  onScan,
  unavailableLabel,
  startingLabel,
  className = '',
}: Props) {
  const containerId = useId().replace(/:/g, '')
  const scannerRef = useRef<Html5Qrcode | null>(null)
  const handledRef = useRef(false)
  const mountedRef = useRef(true)
  const [status, setStatus] = useState<ScanStatus>('starting')
  const handleScan = useEffectEvent(onScan)

  useEffect(() => {
    mountedRef.current = true

    return () => {
      mountedRef.current = false
    }
  }, [])

  useEffect(() => {
    handledRef.current = false

    if (!active) {
      const scanner = scannerRef.current

      if (scanner !== null) {
        scannerRef.current = null
        void disposeScanner(scanner)
      }

      return
    }

    let cancelled = false
    const scanner = new Html5Qrcode(containerId, {
      formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
      useBarCodeDetectorIfSupported: true,
      verbose: false,
    })
    scannerRef.current = scanner

    if (mountedRef.current) {
      setStatus('starting')
    }

    void Html5Qrcode.getCameras()
      .then(async (cameras) => {
        if (cancelled) {
          return
        }

        const cameraId = selectBackCamera(cameras)

        if (cameraId === undefined) {
          if (mountedRef.current) {
            setStatus('unavailable')
          }

          return
        }

        await scanner.start(
          cameraId,
          scanConfig,
          (decodedText) => {
            if (handledRef.current || cancelled) {
              return
            }

            handledRef.current = true
            handleScan(decodedText)

            window.setTimeout(() => {
              handledRef.current = false
            }, 1500)
          },
          () => undefined,
        )

        try {
          await scanner.applyVideoConstraints({
            advanced: [{ focusMode: 'continuous' }],
          } as unknown as MediaTrackConstraints)
        } catch {
          // Some mobile browsers do not expose camera focus controls.
        }

        if (!cancelled && mountedRef.current) {
          setStatus('ready')
        }
      })
      .catch(() => {
        if (!cancelled && mountedRef.current) {
          setStatus('unavailable')
        }
      })

    return () => {
      cancelled = true
      scannerRef.current = null
      void disposeScanner(scanner)
    }
  }, [active, containerId])

  const overlayMessage = status === 'starting'
    ? startingLabel
    : status === 'unavailable'
      ? unavailableLabel
      : null

  return (
    <section
      className={`scanner-camera-panel ${className}`.trim()}
      aria-label="QR camera scanner"
    >
      <div className="scanner-camera-screen">
        <div
          id={containerId}
          className="scanner-camera-viewport"
          aria-live="polite"
        />
        {overlayMessage ? (
          <div className="scanner-camera-overlay" role="status">
            <p>{overlayMessage}</p>
          </div>
        ) : null}
      </div>
    </section>
  )
}
