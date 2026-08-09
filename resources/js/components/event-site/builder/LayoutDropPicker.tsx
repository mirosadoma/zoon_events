import { useDroppable } from '@dnd-kit/core'
import { X } from 'lucide-react'
import { LAYOUT_DROP_PRESETS } from '@/lib/layoutDropPresets'

type Props = {
  visible: boolean
  locale: 'en' | 'ar'
  mode?: 'insert' | 'relayout'
  insertIndex: number
  onClose: () => void
  onColumnPick: (layoutPreset: string, columnIndex: number, insertIndex: number) => void
  onRelayoutPick?: (layoutPreset: string) => void
}

function LayoutColumnCell({
  id,
  layoutPreset,
  columnIndex,
  insertIndex,
  spanClass,
  relayout,
  onColumnPick,
  onRelayoutPick,
}: {
  id: string
  layoutPreset: string
  columnIndex: number
  insertIndex: number
  spanClass: string
  relayout: boolean
  onColumnPick: (layoutPreset: string, columnIndex: number, insertIndex: number) => void
  onRelayoutPick?: (layoutPreset: string) => void
}) {
  const { setNodeRef, isOver } = useDroppable({
    id,
    data: {
      kind: 'layout-column',
      layoutPreset,
      columnIndex,
      insertIndex,
    },
    disabled: relayout,
  })

  if (relayout) {
    return (
      <button
        type="button"
        onClick={() => onRelayoutPick?.(layoutPreset)}
        className={`${spanClass} min-h-10 min-w-0 rounded border border-slate-500 bg-slate-700 transition hover:border-indigo-400 hover:bg-indigo-600/80`}
        title="Apply layout"
      />
    )
  }

  return (
    <button
      ref={setNodeRef}
      type="button"
      onClick={() => onColumnPick(layoutPreset, columnIndex, insertIndex)}
      className={`${spanClass} min-h-10 min-w-0 rounded border transition ${
        isOver
          ? 'border-indigo-300 bg-indigo-600 ring-2 ring-indigo-400/50'
          : 'border-slate-500 bg-slate-700 hover:border-indigo-400 hover:bg-indigo-600/80'
      }`}
      title="Drop here"
    />
  )
}

export default function LayoutDropPicker({
  visible,
  locale,
  mode = 'insert',
  insertIndex,
  onClose,
  onColumnPick,
  onRelayoutPick,
}: Props) {
  const isAr = locale === 'ar'
  const relayout = mode === 'relayout'

  if (!visible) return null

  return (
    <div className="sticky top-2 z-50 mb-3 rounded-2xl border border-slate-700 bg-slate-900 p-3 shadow-2xl">
      <div className="mb-2 flex items-center justify-between gap-3 text-white">
        <div>
          <b className="text-sm">{isAr ? 'اختر تخطيط السيكشن' : 'Choose a section layout'}</b>
          <p className="m-0 text-xs text-slate-400">
            {relayout
              ? isAr
                ? 'انقر على التخطيط المطلوب لتطبيقه على هذا السيكشن'
                : 'Click a layout to apply it to this section'
              : isAr
                ? 'اسقط في العمود المطلوب أو انقر لإضافة سيكشن'
                : 'Drop into the highlighted column or click to add a section'}
          </p>
        </div>
        <button
          type="button"
          onClick={onClose}
          className="rounded-lg px-2 py-1 text-slate-300 hover:bg-slate-700 hover:text-white"
          aria-label={isAr ? 'إغلاق' : 'Close'}
        >
          <X className="h-4 w-4" />
        </button>
      </div>
      <div className="flex gap-2 overflow-x-auto pb-1">
        {LAYOUT_DROP_PRESETS.map((preset) => (
          <button
            key={preset.id}
            type="button"
            onClick={() => relayout && onRelayoutPick?.(preset.layoutPreset)}
            className={`grid h-14 w-24 shrink-0 gap-1 rounded-lg border border-slate-600 bg-slate-800 p-1 ${preset.gridClass} ${
              relayout ? 'cursor-pointer hover:border-indigo-400 hover:bg-slate-700' : ''
            }`}
            title={isAr ? preset.labelAr : preset.labelEn}
          >
            {preset.spans.map((span, columnIndex) => (
              <LayoutColumnCell
                key={`${preset.id}-${columnIndex}`}
                id={`layout-${preset.layoutPreset}-${columnIndex}-${insertIndex}-${relayout ? 'r' : 'i'}`}
                layoutPreset={preset.layoutPreset}
                columnIndex={columnIndex}
                insertIndex={insertIndex}
                spanClass={span}
                relayout={relayout}
                onColumnPick={onColumnPick}
                onRelayoutPick={onRelayoutPick}
              />
            ))}
          </button>
        ))}
      </div>
    </div>
  )
}
