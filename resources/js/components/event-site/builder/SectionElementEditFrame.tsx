import { useCallback, useRef, type ReactNode } from 'react'
import { useDraggable, useDroppable } from '@dnd-kit/core'
import { clampColSpan, clampColStart, colFromPointer } from '@/lib/sectionElementGrid'
import { alignElementPatch } from '@/lib/elementLayoutStyle'
import BuilderElementToolbar from './BuilderElementToolbar'

type Props = {
  elementId: string
  blockId: string
  kind: string
  colSpan: number
  colStart?: number
  align?: 'start' | 'center' | 'end'
  selected: boolean
  locale: 'en' | 'ar'
  canMoveUp: boolean
  canMoveDown: boolean
  gridRef: React.RefObject<HTMLDivElement | null>
  onSelect: () => void
  onChange: (patch: Record<string, unknown>) => void
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

export default function SectionElementEditFrame({
  elementId,
  blockId,
  kind,
  colSpan,
  colStart,
  align = 'start',
  selected,
  locale,
  canMoveUp,
  canMoveDown,
  gridRef,
  onSelect,
  onChange,
  onMoveUp,
  onMoveDown,
  onDuplicate,
  onRemove,
  children,
}: Props) {
  const movePointerRef = useRef<{ startX: number; startCol: number; span: number } | null>(null)
  const resizeRef = useRef<{ startX: number; startSpan: number; colStart: number } | null>(null)

  const label = KIND_LABELS[kind]?.[locale] ?? kind
  const effectiveStart = colStart ?? 1

  const onMovePointerDown = useCallback(
    (e: React.PointerEvent) => {
      e.stopPropagation()
      e.preventDefault()
      onSelect()
      const grid = gridRef.current
      if (!grid) return
      movePointerRef.current = {
        startX: e.clientX,
        startCol: effectiveStart,
        span: colSpan,
      }
      const onMove = (ev: PointerEvent) => {
        if (!movePointerRef.current || !grid) return
        const rect = grid.getBoundingClientRect()
        const col = colFromPointer(rect.width, ev.clientX, rect.left)
        const span = movePointerRef.current.span
        const newStart = clampColStart(col, span)
        onChange({ col_start: newStart, col_span: span, align: undefined })
      }
      const onUp = () => {
        movePointerRef.current = null
        window.removeEventListener('pointermove', onMove)
        window.removeEventListener('pointerup', onUp)
      }
      window.addEventListener('pointermove', onMove)
      window.addEventListener('pointerup', onUp)
    },
    [colSpan, effectiveStart, gridRef, onChange, onSelect],
  )

  const onResizePointerDown = useCallback(
    (e: React.PointerEvent) => {
      e.stopPropagation()
      e.preventDefault()
      onSelect()
      const grid = gridRef.current
      if (!grid) return
      resizeRef.current = { startX: e.clientX, startSpan: colSpan, colStart: effectiveStart }
      const onMove = (ev: PointerEvent) => {
        if (!resizeRef.current || !grid) return
        const rect = grid.getBoundingClientRect()
        const colWidth = rect.width / 12
        if (colWidth <= 0) return
        const delta = ev.clientX - resizeRef.current.startX
        const deltaCols = Math.round(delta / colWidth)
        const newSpan = clampColSpan(resizeRef.current.startSpan + deltaCols, resizeRef.current.colStart)
        onChange({ col_span: newSpan, col_start: resizeRef.current.colStart })
      }
      const onUp = () => {
        resizeRef.current = null
        window.removeEventListener('pointermove', onMove)
        window.removeEventListener('pointerup', onUp)
      }
      window.addEventListener('pointermove', onMove)
      window.addEventListener('pointerup', onUp)
    },
    [colSpan, effectiveStart, gridRef, onChange, onSelect],
  )

  const { attributes: dndAttributes, listeners: dndListeners, setNodeRef: setDragRef, isDragging } = useDraggable({
    id: `element-${blockId}-${elementId}`,
    data: { kind: 'section-element', blockId, elementId },
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
      className={`relative min-h-[2.5rem] rounded-md transition-shadow ${
        isDragging ? 'opacity-50' : ''
      } ${
        isOver ? 'ring-2 ring-indigo-400 ring-offset-2 ring-offset-transparent' : ''
      } ${
        selected
          ? 'z-20 ring-2 ring-violet-500 ring-offset-2 ring-offset-transparent shadow-lg'
          : 'hover:ring-2 hover:ring-violet-400/50'
      }`}
      onClick={(e) => {
        e.stopPropagation()
        onSelect()
      }}
    >
      <BuilderElementToolbar
        label={label}
        locale={locale}
        selected={selected}
        canMoveUp={canMoveUp}
        canMoveDown={canMoveDown}
        align={align}
        showAlign
        onAlign={(next) => onChange(alignElementPatch(next))}
        onMoveUp={onMoveUp}
        onMoveDown={onMoveDown}
        onDuplicate={onDuplicate}
        onRemove={onRemove}
        onPositionPointerDown={onMovePointerDown}
        positionTitle={locale === 'ar' ? 'اسحب لتحريك العمود' : 'Drag to move column'}
        dndListeners={dndListeners}
        dndAttributes={dndAttributes}
        meta={
          <>
            {colSpan}/12
            {colStart ? ` · col ${colStart}` : ''}
          </>
        }
      />

      <div className="relative">{children}</div>

      {selected && (
        <button
          type="button"
          className="pointer-events-auto absolute -end-1 top-1/2 z-30 h-8 w-3 -translate-y-1/2 cursor-ew-resize rounded-full bg-violet-500 shadow-md hover:bg-violet-400 touch-none"
          onPointerDown={onResizePointerDown}
          title={locale === 'ar' ? 'اسحب لتغيير العرض' : 'Drag to resize width'}
        />
      )}
    </div>
  )
}
