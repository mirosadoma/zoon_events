import { Html5Qrcode } from 'html5-qrcode'
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
    const scanner = new Html5Qrcode(containerId)
    scannerRef.current = scanner

    if (mountedRef.current) {
      setStatus('starting')
    }

    void Html5Qrcode.getCameras()
      .then(async (cameras) => {
        if (cancelled) {
          return
        }

        const cameraId = cameras.at(-1)?.id ?? cameras[0]?.id

        if (cameraId === undefined) {
          if (mountedRef.current) {
            setStatus('unavailable')
          }

          return
        }

        await scanner.start(
          cameraId,
          {
            fps: 10,
            qrbox: { width: 220, height: 220 },
            aspectRatio: 1,
          },
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
