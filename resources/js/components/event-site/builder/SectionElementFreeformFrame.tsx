import { useCallback, useRef, type ReactNode } from 'react'
import { useDraggable, useDroppable } from '@dnd-kit/core'
import { clampPct, type FreeformPlacement } from '@/lib/sectionFreeformLayout'
import BuilderElementToolbar from './BuilderElementToolbar'

type Props = {
  elementId: string
  blockId: string
  kind: string
  placement: FreeformPlacement
  selected: boolean
  locale: 'en' | 'ar'
  canMoveUp: boolean
  canMoveDown: boolean
  canvasRef: React.RefObject<HTMLDivElement | null>
  onSelect: () => void
  onChange: (patch: Partial<FreeformPlacement>) => void
  onMoveUp?: () => void
  onMoveDown?: () => void
  onDuplicate?: () => void
  onRemove?: () => void
  children: ReactNode
}

const KIND_LABELS: Record<string, { en: string; ar: string }> = {
  heading: { en: 'Heading', ar: 'عنوان' },
  text: { en: 'Text', ar: 'نص' },
  image: { en: 'Image', ar: 'صورة' },
  button: { en: 'Button', ar: 'زر' },
  card: { en: 'Card', ar: 'بطاقة' },
  divider: { en: 'Divider', ar: 'فاصل' },
  quote: { en: 'Quote', ar: 'اقتباس' },
  video: { en: 'Video', ar: 'فيديو' },
  list: { en: 'List', ar: 'قائمة' },
  icon: { en: 'Icon', ar: 'أيقونة' },
  html: { en: 'HTML', ar: 'HTML' },
  hero: { en: 'Hero', ar: 'بطل' },
  box: { en: 'Box', ar: 'صندوق' },
  shape: { en: 'Shape', ar: 'شكل' },
  details: { en: 'Event details', ar: 'تفاصيل الحدث' },
}

function isInteractiveTarget(target: EventTarget | null): boolean {
  if (!(target instanceof Element)) return false
  return Boolean(target.closest('button, a, input, textarea, select, [data-no-drag]'))
}

export default function SectionElementFreeformFrame({
  elementId,
  blockId,
  kind,
  placement,
  selected,
  locale,
  canMoveUp,
  canMoveDown,
  canvasRef,
  onSelect,
  onChange,
  onMoveUp,
  onMoveDown,
  onDuplicate,
  onRemove,
  children,
}: Props) {
  const moveRef = useRef<{ startX: number; startY: number; startPlacement: FreeformPlacement } | null>(null)
  const resizeWidthRef = useRef<{ startX: number; startWidth: number; x_pct: number } | null>(null)
  const resizeHeightRef = useRef<{ startY: number; startHeight: number } | null>(null)
  const placementRef = useRef(placement)
  placementRef.current = placement

  const label = KIND_LABELS[kind]?.[locale] ?? kind

  const beginMove = useCallback(
    (e: React.PointerEvent, target: HTMLElement) => {
      e.stopPropagation()
      e.preventDefault()
      onSelect()
      const canvas = canvasRef.current
      if (!canvas) return

      const startPlacement = { ...placementRef.current }
      moveRef.current = { startX: e.clientX, startY: e.clientY, startPlacement }
      target.setPointerCapture?.(e.pointerId)

      const onMove = (ev: PointerEvent) => {
        if (!moveRef.current) return
        const rect = canvas.getBoundingClientRect()
        if (rect.width <= 0 || rect.height <= 0) return
        const deltaX = ev.clientX - moveRef.current.startX
        const deltaY = ev.clientY - moveRef.current.startY
        const dxPct = (deltaX / rect.width) * 100
        const dyPct = (deltaY / rect.height) * 100
        const start = moveRef.current.startPlacement
        const maxX = Math.max(0, 100 - start.width_pct)
        onChange({
          x_pct: clampPct(start.x_pct + dxPct, 0, maxX),
          y_pct: clampPct(start.y_pct + dyPct, 0, 95),
        })
      }

      const onUp = (ev: PointerEvent) => {
        moveRef.current = null
        try {
          target.releasePointerCapture?.(ev.pointerId)
        } catch {
          /* already released */
        }
        window.removeEventListener('pointermove', onMove)
        window.removeEventListener('pointerup', onUp)
        window.removeEventListener('pointercancel', onUp)
      }

      window.addEventListener('pointermove', onMove)
      window.addEventListener('pointerup', onUp)
      window.addEventListener('pointercancel', onUp)
    },
    [canvasRef, onChange, onSelect],
  )

  const onMovePointerDown = useCallback(
    (e: React.PointerEvent) => {
      beginMove(e, e.currentTarget as HTMLElement)
    },
    [beginMove],
  )

  const onBodyPointerDown = useCallback(
    (e: React.PointerEvent) => {
      if (isInteractiveTarget(e.target)) return
      // Allow drag from the element body (Figma-like). First click selects; drag moves.
      beginMove(e, e.currentTarget as HTMLElement)
    },
    [beginMove],
  )

  const onResizeWidthPointerDown = useCallback(
    (e: React.PointerEvent) => {
      e.stopPropagation()
      e.preventDefault()
      onSelect()
      const canvas = canvasRef.current
      if (!canvas) return
      const target = e.currentTarget as HTMLElement
      resizeWidthRef.current = {
        startX: e.clientX,
        startWidth: placementRef.current.width_pct,
        x_pct: placementRef.current.x_pct,
      }
      target.setPointerCapture?.(e.pointerId)
      const onMove = (ev: PointerEvent) => {
        if (!resizeWidthRef.current) return
        const rect = canvas.getBoundingClientRect()
        const delta = ev.clientX - resizeWidthRef.current.startX
        const deltaPct = rect.width > 0 ? (delta / rect.width) * 100 : 0
        const maxWidth = Math.max(5, 100 - resizeWidthRef.current.x_pct)
        onChange({
          width_pct: clampPct(resizeWidthRef.current.startWidth + deltaPct, 5, maxWidth),
        })
      }
      const onUp = (ev: PointerEvent) => {
        resizeWidthRef.current = null
        try {
          target.releasePointerCapture?.(ev.pointerId)
        } catch {
          /* already released */
        }
        window.removeEventListener('pointermove', onMove)
        window.removeEventListener('pointerup', onUp)
        window.removeEventListener('pointercancel', onUp)
      }
      window.addEventListener('pointermove', onMove)
      window.addEventListener('pointerup', onUp)
      window.addEventListener('pointercancel', onUp)
    },
    [canvasRef, onChange, onSelect],
  )

  const onResizeHeightPointerDown = useCallback(
    (e: React.PointerEvent) => {
      e.stopPropagation()
      e.preventDefault()
      onSelect()
      const canvas = canvasRef.current
      if (!canvas) return
      const target = e.currentTarget as HTMLElement
      const startHeight = placementRef.current.height_pct ?? 20
      resizeHeightRef.current = { startY: e.clientY, startHeight }
      target.setPointerCapture?.(e.pointerId)
      const onMove = (ev: PointerEvent) => {
        if (!resizeHeightRef.current) return
        const rect = canvas.getBoundingClientRect()
        const delta = ev.clientY - resizeHeightRef.current.startY
        const deltaPct = rect.height > 0 ? (delta / rect.height) * 100 : 0
        onChange({ height_pct: clampPct(resizeHeightRef.current.startHeight + deltaPct, 5, 100) })
      }
      const onUp = (ev: PointerEvent) => {
        resizeHeightRef.current = null
        try {
          target.releasePointerCapture?.(ev.pointerId)
        } catch {
          /* already released */
        }
        window.removeEventListener('pointermove', onMove)
        window.removeEventListener('pointerup', onUp)
        window.removeEventListener('pointercancel', onUp)
      }
      window.addEventListener('pointermove', onMove)
      window.addEventListener('pointerup', onUp)
      window.addEventListener('pointercancel', onUp)
    },
    [canvasRef, onChange, onSelect],
  )

  const { attributes: dndAttributes, listeners: dndListeners, setNodeRef: setDragRef, isDragging } = useDraggable({
    id: `element-${blockId}-${elementId}`,
    data: { kind: 'section-element', blockId, elementId },
    // Freeform uses custom pointer move; dnd-kit would steal the gesture and look "broken".
    disabled: true,
  })

  const { setNodeRef: setDropRef, isOver } = useDroppable({
    id: `element-target-${blockId}-${elementId}`,
    data: { kind: 'section-element-target', blockId, elementId },
  })

  const setCombinedRef = (node: HTMLDivElement | null) => {
    setDragRef(node)
    setDropRef(node)
  }

  return (
    <div
      ref={setCombinedRef}
      data-element-id={elementId}
      className={`relative min-h-[2rem] touch-none rounded-md transition-shadow ${
        isDragging ? 'opacity-50' : ''
      } ${
        isOver ? 'ring-2 ring-indigo-400 ring-offset-2 ring-offset-transparent' : ''
      } ${
        selected
          ? 'z-20 cursor-move ring-2 ring-violet-500 ring-offset-2 ring-offset-transparent shadow-lg'
          : 'cursor-grab hover:ring-2 hover:ring-violet-400/50'
      }`}
      onClick={(e) => {
        e.stopPropagation()
        onSelect()
      }}
      onPointerDown={onBodyPointerDown}
    >
      <BuilderElementToolbar
        label={label}
        locale={locale}
        selected={selected}
        canMoveUp={canMoveUp}
        canMoveDown={canMoveDown}
        onMoveUp={onMoveUp}
        onMoveDown={onMoveDown}
        onDuplicate={onDuplicate}
        onRemove={onRemove}
        onPositionPointerDown={onMovePointerDown}
        positionTitle={locale === 'ar' ? 'اسحب للتحريك' : 'Drag to move'}
        dndListeners={dndListeners}
        dndAttributes={dndAttributes}
        meta={
          <>
            {Math.round(placement.x_pct)}%, {Math.round(placement.y_pct)}%
            · {Math.round(placement.width_pct)}%w
          </>
        }
      />

      <div className="relative pointer-events-none select-none">{children}</div>

      {selected && (
        <>
          <button
            type="button"
            data-no-drag
            className="pointer-events-auto absolute -end-1 top-1/2 z-30 h-8 w-3 -translate-y-1/2 cursor-ew-resize rounded-full bg-violet-500 shadow-md hover:bg-violet-400 touch-none"
            onPointerDown={onResizeWidthPointerDown}
            title={locale === 'ar' ? 'تغيير العرض' : 'Resize width'}
          />
          <button
            type="button"
            data-no-drag
            className="pointer-events-auto absolute -bottom-1 left-1/2 z-30 h-3 w-8 -translate-x-1/2 cursor-ns-resize rounded-full bg-violet-500 shadow-md hover:bg-violet-400 touch-none"
            onPointerDown={onResizeHeightPointerDown}
            title={locale === 'ar' ? 'تغيير الارتفاع' : 'Resize height'}
          />
        </>
      )}
    </div>
  )
}
