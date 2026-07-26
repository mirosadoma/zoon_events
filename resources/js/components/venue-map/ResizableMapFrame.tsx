import { useEffect, useRef, useState, type PointerEvent as ReactPointerEvent, type ReactNode } from 'react'

type Corner = 'nw' | 'ne' | 'sw' | 'se'

type Props = {
  storageKey: string
  minWidth?: number
  minHeight?: number
  defaultHeight?: number
  hint?: string
  children: ReactNode
}

type FrameSize = {
  width: number | null
  height: number
}

function readStoredSize(storageKey: string, fallbackHeight: number): FrameSize {
  if (typeof window === 'undefined') {
    return { width: null, height: fallbackHeight }
  }

  try {
    const raw = window.localStorage.getItem(storageKey)
    if (!raw) return { width: null, height: fallbackHeight }
    const parsed = JSON.parse(raw) as { width?: number; height?: number }
    return {
      width: typeof parsed.width === 'number' ? parsed.width : null,
      height: typeof parsed.height === 'number' ? parsed.height : fallbackHeight,
    }
  } catch {
    return { width: null, height: fallbackHeight }
  }
}

export default function ResizableMapFrame({
  storageKey,
  minWidth = 320,
  minHeight = 280,
  defaultHeight = 520,
  hint,
  children,
}: Props) {
  const frameRef = useRef<HTMLDivElement | null>(null)
  const [size, setSize] = useState<FrameSize>(() => readStoredSize(storageKey, defaultHeight))

  useEffect(() => {
    window.localStorage.setItem(storageKey, JSON.stringify(size))
  }, [size, storageKey])

  function startResize(corner: Corner, event: ReactPointerEvent<HTMLButtonElement>) {
    event.preventDefault()
    event.stopPropagation()

    const frame = frameRef.current
    if (!frame) return

    const startX = event.clientX
    const startY = event.clientY
    const startWidth = frame.offsetWidth
    const startHeight = frame.offsetHeight
    const parentWidth = frame.parentElement?.clientWidth ?? startWidth

    const handle = event.currentTarget
    handle.setPointerCapture(event.pointerId)

    function onMove(moveEvent: PointerEvent) {
      const dx = moveEvent.clientX - startX
      const dy = moveEvent.clientY - startY

      let nextWidth = startWidth
      let nextHeight = startHeight

      if (corner === 'se' || corner === 'ne') {
        nextWidth = startWidth + dx
      }
      if (corner === 'sw' || corner === 'nw') {
        nextWidth = startWidth - dx
      }
      if (corner === 'se' || corner === 'sw') {
        nextHeight = startHeight + dy
      }
      if (corner === 'ne' || corner === 'nw') {
        nextHeight = startHeight - dy
      }

      setSize({
        width: Math.min(parentWidth, Math.max(minWidth, Math.round(nextWidth))),
        height: Math.max(minHeight, Math.round(nextHeight)),
      })
    }

    function onUp(upEvent: PointerEvent) {
      handle.releasePointerCapture(upEvent.pointerId)
      window.removeEventListener('pointermove', onMove)
      window.removeEventListener('pointerup', onUp)
    }

    window.addEventListener('pointermove', onMove)
    window.addEventListener('pointerup', onUp)
  }

  return (
    <div className="venue-map-resize-wrap">
      {hint ? <p className="venue-map-resize-hint">{hint}</p> : null}
      <div
        ref={frameRef}
        className="venue-map-resize-frame"
        style={{
          width: size.width ?? '100%',
          height: size.height,
        }}
      >
        <div className="venue-map-resize-frame__body">
          {children}
        </div>

        {(['nw', 'ne', 'sw', 'se'] as Corner[]).map((corner) => (
          <button
            key={corner}
            type="button"
            className={`venue-map-resize-handle venue-map-resize-handle--${corner}`}
            aria-label={`Resize ${corner}`}
            onPointerDown={(event) => startResize(corner, event)}
          />
        ))}
      </div>
    </div>
  )
}
