import { AlignCenter, AlignLeft, AlignRight, ChevronDown, ChevronUp, Copy, GripVertical, Move, Trash2 } from 'lucide-react'
import type { ReactNode } from 'react'

type Props = {
  label: string
  locale: 'en' | 'ar'
  selected: boolean
  canMoveUp: boolean
  canMoveDown: boolean
  align?: 'start' | 'center' | 'end'
  showAlign?: boolean
  onMoveUp?: () => void
  onMoveDown?: () => void
  onDuplicate?: () => void
  onRemove?: () => void
  onAlign?: (align: 'start' | 'center' | 'end') => void
  onPositionPointerDown?: (e: React.PointerEvent) => void
  positionTitle?: string
  dndListeners?: Record<string, unknown>
  dndAttributes?: Record<string, unknown>
  meta?: ReactNode
}

export default function BuilderElementToolbar({
  label,
  locale,
  selected,
  canMoveUp,
  canMoveDown,
  align = 'start',
  showAlign = false,
  onMoveUp,
  onMoveDown,
  onDuplicate,
  onRemove,
  onAlign,
  onPositionPointerDown,
  positionTitle,
  dndListeners,
  dndAttributes,
  meta,
}: Props) {
  if (!selected) return null

  return (
    <>
      <div className="pointer-events-auto absolute start-0 top-0 z-[60] flex -translate-y-[calc(100%+6px)] items-center gap-1">
        <span
          className={`inline-flex items-center gap-1 rounded bg-violet-600 px-2 py-0.5 text-[10px] font-semibold text-white shadow ${
            onPositionPointerDown ? 'cursor-grab touch-none active:cursor-grabbing' : ''
          }`}
          onPointerDown={onPositionPointerDown}
          title={
            onPositionPointerDown
              ? positionTitle ?? (locale === 'ar' ? 'اسحب لتحريك الموضع' : 'Drag to reposition')
              : undefined
          }
        >
          {onPositionPointerDown && <GripVertical className="h-3 w-3 shrink-0 opacity-90" />}
          {label}
          {meta && <span className="opacity-75">{meta}</span>}
        </span>
      </div>

      <div className="pointer-events-auto absolute end-0 top-0 z-[60] flex -translate-y-[calc(100%+6px)] items-center gap-0.5 rounded-md border border-white/20 bg-[#1a1a2e]/95 p-0.5 shadow-lg backdrop-blur-sm">
        {showAlign && onAlign && (
          <>
            <button
              type="button"
              title={locale === 'ar' ? 'يسار' : 'Align left'}
              onClick={(e) => {
                e.stopPropagation()
                onAlign('start')
              }}
              className={`rounded p-1.5 hover:bg-white/10 ${
                align === 'start' ? 'bg-white/15 text-white' : 'text-white/70 hover:text-white'
              }`}
            >
              <AlignLeft className="h-3.5 w-3.5" />
            </button>
            <button
              type="button"
              title={locale === 'ar' ? 'وسط' : 'Align center'}
              onClick={(e) => {
                e.stopPropagation()
                onAlign('center')
              }}
              className={`rounded p-1.5 hover:bg-white/10 ${
                align === 'center' ? 'bg-white/15 text-white' : 'text-white/70 hover:text-white'
              }`}
            >
              <AlignCenter className="h-3.5 w-3.5" />
            </button>
            <button
              type="button"
              title={locale === 'ar' ? 'يمين' : 'Align right'}
              onClick={(e) => {
                e.stopPropagation()
                onAlign('end')
              }}
              className={`rounded p-1.5 hover:bg-white/10 ${
                align === 'end' ? 'bg-white/15 text-white' : 'text-white/70 hover:text-white'
              }`}
            >
              <AlignRight className="h-3.5 w-3.5" />
            </button>
            <span className="mx-0.5 h-5 w-px bg-white/20" />
          </>
        )}
        {onMoveUp && (
          <button
            type="button"
            title={locale === 'ar' ? 'تحريك لأعلى' : 'Move up'}
            disabled={!canMoveUp}
            onClick={(e) => {
              e.stopPropagation()
              onMoveUp()
            }}
            className="rounded p-1.5 text-white/70 hover:bg-white/10 hover:text-white disabled:opacity-30"
          >
            <ChevronUp className="h-3.5 w-3.5" />
          </button>
        )}
        {onMoveDown && (
          <button
            type="button"
            title={locale === 'ar' ? 'تحريك لأسفل' : 'Move down'}
            disabled={!canMoveDown}
            onClick={(e) => {
              e.stopPropagation()
              onMoveDown()
            }}
            className="rounded p-1.5 text-white/70 hover:bg-white/10 hover:text-white disabled:opacity-30"
          >
            <ChevronDown className="h-3.5 w-3.5" />
          </button>
        )}
        {onDuplicate && (
          <button
            type="button"
            title={locale === 'ar' ? 'نسخ' : 'Duplicate'}
            onClick={(e) => {
              e.stopPropagation()
              onDuplicate()
            }}
            className="rounded p-1.5 text-white/70 hover:bg-white/10 hover:text-white"
          >
            <Copy className="h-3.5 w-3.5" />
          </button>
        )}
        {dndListeners && (
          <button
            type="button"
            className="cursor-grab touch-none rounded p-1.5 text-white/70 hover:bg-white/10 hover:text-white"
            title={
              locale === 'ar'
                ? 'اسحب لإعادة الترتيب أو نقل لسيكشن آخر'
                : 'Drag to reorder or move to another section'
            }
            onClick={(e) => e.stopPropagation()}
            {...dndListeners}
            {...dndAttributes}
          >
            <Move className="h-3.5 w-3.5" />
          </button>
        )}
        {onRemove && (
          <button
            type="button"
            title={locale === 'ar' ? 'حذف' : 'Delete'}
            onClick={(e) => {
              e.stopPropagation()
              onRemove()
            }}
            className="rounded p-1.5 text-red-400 hover:bg-red-500/20 hover:text-red-300"
          >
            <Trash2 className="h-3.5 w-3.5" />
          </button>
        )}
      </div>
    </>
  )
}
