import { Copy, ArrowUp, ArrowDown, Eye, EyeOff, Trash2 } from 'lucide-react'

type Align = 'start' | 'center' | 'end'
type Width = 'full' | 'boxed' | 'narrow'

type Props = {
  blockId: string
  locale: 'en' | 'ar'
  canMoveUp: boolean
  canMoveDown: boolean
  onDuplicate: () => void
  onMoveUp: () => void
  onMoveDown: () => void
  onToggleVisibility: () => void
  onRemove: () => void
  visible: boolean
  onAlignChange: (align: Align) => void
  align: Align
  onWidthChange: (width: Width) => void
  width: Width
}

export default function BlockActionsToolbar({
  locale,
  canMoveUp,
  canMoveDown,
  onDuplicate,
  onMoveUp,
  onMoveDown,
  onToggleVisibility,
  onRemove,
  visible,
  onAlignChange,
  align,
  onWidthChange,
  width,
}: Props) {
  const alignOptions = [
    { value: 'start', label: locale === 'ar' ? 'بداية' : 'Start' },
    { value: 'center', label: locale === 'ar' ? 'وسط' : 'Center' },
    { value: 'end', label: locale === 'ar' ? 'نهاية' : 'End' },
  ]

  const widthOptions = [
    { value: 'full', label: locale === 'ar' ? 'كامل' : 'Full' },
    { value: 'boxed', label: locale === 'ar' ? 'محاط' : 'Boxed' },
    { value: 'narrow', label: locale === 'ar' ? 'ضيق' : 'Narrow' },
  ]

  return (
    <div className="mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-white/10 bg-white/[0.03] p-2">
      <div className="flex items-center gap-1">
        <button
          type="button"
          onClick={onDuplicate}
          className="inline-flex items-center gap-1 rounded px-2 py-1.5 text-xs text-white/80 transition-colors hover:bg-white/10"
          title={locale === 'ar' ? 'تكرار' : 'Duplicate'}
        >
          <Copy className="h-3.5 w-3.5" />
          <span className="hidden sm:inline">{locale === 'ar' ? 'تكرار' : 'Copy'}</span>
        </button>

        <button
          type="button"
          onClick={onMoveUp}
          disabled={!canMoveUp}
          className="inline-flex items-center gap-1 rounded px-2 py-1.5 text-xs text-white/80 transition-colors hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
          title={locale === 'ar' ? 'نقل لأعلى' : 'Move up'}
        >
          <ArrowUp className="h-3.5 w-3.5" />
        </button>

        <button
          type="button"
          onClick={onMoveDown}
          disabled={!canMoveDown}
          className="inline-flex items-center gap-1 rounded px-2 py-1.5 text-xs text-white/80 transition-colors hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
          title={locale === 'ar' ? 'نقل لأسفل' : 'Move down'}
        >
          <ArrowDown className="h-3.5 w-3.5" />
        </button>

        <button
          type="button"
          onClick={onToggleVisibility}
          className="inline-flex items-center gap-1 rounded px-2 py-1.5 text-xs text-white/80 transition-colors hover:bg-white/10"
          title={visible ? (locale === 'ar' ? 'إخفاء' : 'Hide') : (locale === 'ar' ? 'إظهار' : 'Show')}
        >
          {visible ? <Eye className="h-3.5 w-3.5" /> : <EyeOff className="h-3.5 w-3.5" />}
        </button>

        <button
          type="button"
          onClick={onRemove}
          className="inline-flex items-center gap-1 rounded px-2 py-1.5 text-xs text-red-400 transition-colors hover:bg-red-500/10"
          title={locale === 'ar' ? 'حذف' : 'Remove'}
        >
          <Trash2 className="h-3.5 w-3.5" />
        </button>
      </div>

      <div className="mx-1 hidden h-4 w-px bg-white/10 sm:block" />

      <div className="flex items-center gap-2">
        <div className="flex items-center gap-1.5">
          <span className="text-xs text-white/50">{locale === 'ar' ? 'المحاذاة' : 'Align'}:</span>
          <select
            value={align}
            onChange={(e) => onAlignChange(e.target.value as Align)}
            className="rounded border border-white/10 bg-white/5 px-2 py-1 text-xs text-white"
          >
            {alignOptions.map((opt) => (
              <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
          </select>
        </div>

        <div className="flex items-center gap-1.5">
          <span className="text-xs text-white/50">{locale === 'ar' ? 'العرض' : 'Width'}:</span>
          <select
            value={width}
            onChange={(e) => onWidthChange(e.target.value as Width)}
            className="rounded border border-white/10 bg-white/5 px-2 py-1 text-xs text-white"
          >
            {widthOptions.map((opt) => (
              <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
          </select>
        </div>
      </div>
    </div>
  )
}
