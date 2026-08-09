import { useDraggable } from '@dnd-kit/core'
import type { LucideIcon } from 'lucide-react'
import type { BuilderDragData } from '@/lib/siteBuilderDnd'

type Props = {
  id: string
  dragData: BuilderDragData
  label: string
  hint?: string
  icon?: LucideIcon
  glyph?: string
  onClick?: () => void
  variant?: 'dark' | 'light'
}

export default function PaletteDraggable({
  id,
  dragData,
  label,
  hint,
  icon: Icon,
  glyph,
  onClick,
  variant = 'dark',
}: Props) {
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
    id,
    data: dragData,
  })

  const isLight = variant === 'light'

  return (
    <button
      ref={setNodeRef}
      type="button"
      onClick={onClick}
      className={`group relative flex min-h-[4.5rem] cursor-grab flex-col items-start justify-between gap-2 overflow-hidden rounded-xl border p-2.5 text-start transition touch-none active:cursor-grabbing ${
        isLight
          ? 'border-slate-200 bg-white text-slate-700 hover:-translate-y-0.5 hover:border-indigo-500 hover:shadow-md'
          : 'border-white/10 bg-gradient-to-br from-white/[0.06] to-white/[0.02] text-white/85 hover:-translate-y-0.5 hover:border-violet-400/50 hover:from-violet-500/20 hover:to-violet-500/5 hover:shadow-[0_8px_20px_-12px_rgba(139,92,246,0.55)]'
      } ${isDragging ? 'opacity-40 ring-2 ring-violet-400/50' : ''}`}
      {...listeners}
      {...attributes}
    >
      <span
        className={`flex h-8 w-8 items-center justify-center rounded-lg ${
          isLight
            ? 'bg-slate-100 text-indigo-600'
            : 'bg-violet-500/15 text-violet-300 ring-1 ring-violet-400/20 group-hover:bg-violet-500/25 group-hover:text-violet-200'
        }`}
      >
        {Icon ? <Icon className="h-4 w-4" strokeWidth={1.75} /> : <span className="text-sm font-bold">{glyph}</span>}
      </span>
      <span className="w-full">
        <span className="block text-[11px] font-bold leading-tight tracking-wide">{label}</span>
        {hint && (
          <span className={`mt-0.5 block text-[9px] leading-tight ${isLight ? 'text-slate-400' : 'text-white/35'}`}>
            {hint}
          </span>
        )}
      </span>
    </button>
  )
}
