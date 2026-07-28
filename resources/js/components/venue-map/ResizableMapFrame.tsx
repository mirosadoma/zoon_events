import { useEffect, useRef, useState, type PointerEvent as ReactPointerEvent, type ReactNode } from 'react'

type Corner = 'nw' | 'ne' | 'sw' | 'se'

type Props = {
  storageKey: string
  minWidth?: number
  minHeight?: number
  defaultHeight?: number
  /** Image width / height — used to pick a sensible default frame size. */
  aspectRatio?: number | null
  hint?: string
  children: ReactNode
}

type FrameSize = {
  width: number
  height: number
}

function clamp(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, value))
}

function readStoredSize(storageKey: string): FrameSize | null {
  if (typeof window === 'undefined') return null

  try {
    const raw = window.localStorage.getItem(storageKey)
    if (!raw) return null
    const parsed = JSON.parse(raw) as { width?: number; height?: number }
    if (typeof parsed.width !== 'number' || typeof parsed.height !== 'number') return null
    return {
      width: parsed.width,
      height: parsed.height,
    }
  } catch {
    return null
  }
}

function sizeFromAspect(
  parentWidth: number,
  aspectRatio: number | null | undefined,
  defaultHeight: number,
  minWidth: number,
  minHeight: number,
): FrameSize {
  const maxWidth = Math.max(minWidth, parentWidth)
  // Prefer filling the outer section width; height is user-controlled.
  const width = maxWidth
  const height = aspectRatio && aspectRatio > 0
    ? Math.max(minHeight, Math.round(width / aspectRatio))
    : Math.max(minHeight, defaultHeight)

  return {
    width: clamp(width, minWidth, maxWidth),
    height,
  }
}

export default function ResizableMapFrame({
  storageKey,
  minWidth = 320,
  minHeight = 280,
  defaultHeight = 520,
  aspectRatio = null,
  hint,
  children,
}: Props) {
  const wrapRef = useRef<HTMLDivElement | null>(null)
  const frameRef = useRef<HTMLDivElement | null>(null)
  const [size, setSize] = useState<FrameSize>(() => {
    const stored = readStoredSize(storageKey)
    if (stored) return stored
    return sizeFromAspect(960, aspectRatio, defaultHeight, minWidth, minHeight)
  })

  useEffect(() => {
    const wrap = wrapRef.current
    if (!wrap) return

    const stored = readStoredSize(storageKey)
    if (stored) {
      const maxWidth = Math.max(minWidth, wrap.clientWidth)
      setSize({
        width: clamp(stored.width, minWidth, maxWidth),
        height: Math.max(minHeight, stored.height),
      })
      return
    }

    setSize(sizeFromAspect(wrap.clientWidth, aspectRatio, defaultHeight, minWidth, minHeight))
  }, [storageKey, aspectRatio, defaultHeight, minWidth, minHeight])

  useEffect(() => {
    window.localStorage.setItem(storageKey, JSON.stringify(size))
  }, [size, storageKey])

  function startResize(corner: Corner, event: ReactPointerEvent<HTMLButtonElement>) {
    event.preventDefault()
    event.stopPropagation()

    const frame = frameRef.current
    const wrap = wrapRef.current
    if (!frame || !wrap) return

    const startX = event.clientX
    const startY = event.clientY
    const startWidth = frame.offsetWidth
    const startHeight = frame.offsetHeight
    const parentWidth = wrap.clientWidth

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
        width: clamp(Math.round(nextWidth), minWidth, parentWidth),
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
    <div ref={wrapRef} className="venue-map-resize-wrap">
      {hint ? <p className="venue-map-resize-hint">{hint}</p> : null}
      <div
        ref={frameRef}
        className="venue-map-resize-frame"
        style={{
          width: size.width,
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
