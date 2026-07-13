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
  restartLabel: string
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

function nextPaint(): Promise<void> {
  return new Promise((resolve) => {
    requestAnimationFrame(() => {
      requestAnimationFrame(() => resolve())
    })
  })
}

async function waitForViewportLayout(element: HTMLElement, signal: AbortSignal): Promise<void> {
  if (element.clientWidth > 0 && element.clientHeight > 0) {
    return
  }

  if (typeof ResizeObserver === 'undefined') {
    await nextPaint()
    return
  }

  await new Promise<void>((resolve) => {
    const observer = new ResizeObserver(() => {
      if (element.clientWidth > 0 && element.clientHeight > 0) {
        observer.disconnect()
        resolve()
      }
    })

    observer.observe(element)

    const timeoutId = window.setTimeout(() => {
      observer.disconnect()
      resolve()
    }, 3000)

    signal.addEventListener('abort', () => {
      observer.disconnect()
      window.clearTimeout(timeoutId)
      resolve()
    }, { once: true })
  })
}

export default function QrCameraScanner({
  active,
  onScan,
  unavailableLabel,
  startingLabel,
  restartLabel,
  className = '',
}: Props) {
  const containerId = useId().replace(/:/g, '')
  const containerRef = useRef<HTMLDivElement | null>(null)
  const scannerRef = useRef<Html5Qrcode | null>(null)
  const handledRef = useRef(false)
  const mountedRef = useRef(true)
  const bootGenerationRef = useRef(0)
  const [status, setStatus] = useState<ScanStatus>('starting')
  const [restartToken, setRestartToken] = useState(0)
  const handleScan = useEffectEvent(onScan)

  useEffect(() => {
    mountedRef.current = true

    return () => {
      mountedRef.current = false
    }
  }, [])

  useEffect(() => {
    const onPageShow = (event: PageTransitionEvent) => {
      if (event.persisted) {
        setRestartToken((current) => current + 1)
      }
    }

    window.addEventListener('pageshow', onPageShow)

    return () => {
      window.removeEventListener('pageshow', onPageShow)
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

    const generation = bootGenerationRef.current + 1
    bootGenerationRef.current = generation
    const abortController = new AbortController()
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

    async function startCamera(): Promise<boolean> {
      const cameras = await Html5Qrcode.getCameras()

      if (cancelled || generation !== bootGenerationRef.current) {
        return false
      }

      const cameraId = selectBackCamera(cameras)

      if (cameraId === undefined) {
        return false
      }

      await scanner.start(
        cameraId,
        scanConfig,
        (decodedText) => {
          if (handledRef.current || cancelled || generation !== bootGenerationRef.current) {
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

      return true
    }

    async function boot(): Promise<void> {
      await nextPaint()

      if (cancelled || generation !== bootGenerationRef.current) {
        return
      }

      const container = containerRef.current ?? document.getElementById(containerId)

      if (!(container instanceof HTMLElement)) {
        if (mountedRef.current) {
          setStatus('unavailable')
        }

        return
      }

      await waitForViewportLayout(container, abortController.signal)

      if (cancelled || generation !== bootGenerationRef.current) {
        return
      }

      try {
        const started = await startCamera()

        if (!started) {
          if (mountedRef.current) {
            setStatus('unavailable')
          }

          return
        }

        if (!cancelled && generation === bootGenerationRef.current && mountedRef.current) {
          setStatus('ready')
        }
      } catch {
        if (cancelled || generation !== bootGenerationRef.current) {
          return
        }

        await new Promise((resolve) => {
          window.setTimeout(resolve, 400)
        })

        if (cancelled || generation !== bootGenerationRef.current) {
          return
        }

        try {
          const started = await startCamera()

          if (!started || cancelled || generation !== bootGenerationRef.current) {
            if (mountedRef.current) {
              setStatus('unavailable')
            }

            return
          }

          if (mountedRef.current) {
            setStatus('ready')
          }
        } catch {
          if (!cancelled && generation === bootGenerationRef.current && mountedRef.current) {
            setStatus('unavailable')
          }
        }
      }
    }

    void boot()

    return () => {
      cancelled = true
      abortController.abort()
      scannerRef.current = null
      void disposeScanner(scanner)
    }
  }, [active, containerId, restartToken])

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
          ref={containerRef}
          id={containerId}
          className="scanner-camera-viewport"
          aria-live="polite"
        />
        {overlayMessage ? (
          <div className="scanner-camera-overlay" role="status">
            <p>{overlayMessage}</p>
            {status === 'unavailable' ? (
              <button
                type="button"
                className="button-secondary mt-4"
                onClick={() => setRestartToken((current) => current + 1)}
              >
                {restartLabel}
              </button>
            ) : null}
          </div>
        ) : null}
      </div>
    </section>
  )
}
