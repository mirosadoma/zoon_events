import { Copy, ChevronUp, ChevronDown, Eye, EyeOff, Trash2, GripVertical, LayoutGrid, Settings } from 'lucide-react'
import type { ReactNode } from 'react'

type Props = {
  blockId: string
  blockType: string
  label: string
  selected: boolean
  visible: boolean
  canMoveUp: boolean
  canMoveDown: boolean
  onSelect: () => void
  onDuplicate?: () => void
  onMoveUp?: () => void
  onMoveDown?: () => void
  onToggleVisibility?: () => void
  onChangeLayout?: () => void
  onOpenStyle?: () => void
  sortableHandleProps?: {
    attributes: Record<string, unknown>
    listeners: Record<string, unknown> | undefined
  }
  onRemove?: () => void
  children: ReactNode
}

export default function BuilderSectionFrame({
  blockId,
  blockType,
  label,
  selected,
  visible,
  canMoveUp,
  canMoveDown,
  onSelect,
  onDuplicate,
  onMoveUp,
  onMoveDown,
  onToggleVisibility,
  onChangeLayout,
  onOpenStyle,
  onRemove,
  sortableHandleProps,
  children,
}: Props) {
  const showToolbar = selected || !visible

  return (
    <div
      data-block-id={blockId}
      data-block-type={blockType}
      data-builder-section="true"
      className="group/builder-section relative w-full transition-all"
      onClick={(e) => {
        e.stopPropagation()
        onSelect()
      }}
    >
      <div
        className={`pointer-events-none absolute inset-0 z-20 border-2 transition-colors ${
          selected
            ? 'border-violet-500 shadow-[inset_0_0_0_1px_rgba(139,92,246,0.4)]'
            : 'border-transparent group-hover/builder-section:border-violet-400/50'
        } ${!visible ? 'border-dashed border-violet-400/60' : ''}`}
      />

      <div
        className={`pointer-events-auto absolute start-0 top-0 z-[60] flex items-center gap-1 transition-opacity ${
          selected || !visible ? 'opacity-100' : 'opacity-0 group-hover/builder-section:opacity-100'
        }`}
      >
        <span className="inline-flex items-center gap-1 bg-violet-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white shadow-sm">
          <button
            type="button"
            className="cursor-grab active:cursor-grabbing touch-none rounded p-0.5 hover:bg-white/20"
            {...(sortableHandleProps?.attributes ?? {})}
            {...(sortableHandleProps?.listeners ?? {})}
          >
            <GripVertical className="h-3 w-3 opacity-70" />
          </button>
          {label}
          {!visible && (
            <span className="rounded bg-white/20 px-1 py-px text-[9px] normal-case tracking-normal">
              Hidden
            </span>
          )}
        </span>
      </div>

      <div
        className={`pointer-events-auto absolute end-2 top-0 z-[60] flex -translate-y-[calc(100%+8px)] items-center gap-0.5 rounded-md border border-white/20 bg-[#1a1a2e]/95 p-0.5 shadow-lg backdrop-blur-sm transition-opacity ${
          showToolbar ? 'opacity-100' : 'opacity-0 group-hover/builder-section:opacity-100'
        }`}
      >
        {onMoveUp && (
          <button
            type="button"
            title="Move up"
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
            title="Move down"
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
        {onChangeLayout && (
          <button
            type="button"
            title="Change layout"
            onClick={(e) => {
              e.stopPropagation()
              onChangeLayout()
            }}
            className="rounded p-1.5 text-white/70 hover:bg-white/10 hover:text-white"
          >
            <LayoutGrid className="h-3.5 w-3.5" />
          </button>
        )}
        {onOpenStyle && (
          <button
            type="button"
            title="Section settings · Grid / Freeform"
            onClick={(e) => {
              e.stopPropagation()
              onOpenStyle()
            }}
            className="rounded p-1.5 text-white/70 hover:bg-white/10 hover:text-white"
          >
            <Settings className="h-3.5 w-3.5" />
          </button>
        )}
        {onDuplicate && (
          <button
            type="button"
            title="Duplicate"
            onClick={(e) => {
              e.stopPropagation()
              onDuplicate()
            }}
            className="rounded p-1.5 text-white/70 hover:bg-white/10 hover:text-white"
          >
            <Copy className="h-3.5 w-3.5" />
          </button>
        )}
        {onToggleVisibility && (
          <button
            type="button"
            title={visible ? 'Hide' : 'Show'}
            onClick={(e) => {
              e.stopPropagation()
              onToggleVisibility()
            }}
            className="rounded p-1.5 text-white/70 hover:bg-white/10 hover:text-white"
          >
            {visible ? <Eye className="h-3.5 w-3.5" /> : <EyeOff className="h-3.5 w-3.5" />}
          </button>
        )}
        {onRemove && (
          <button
            type="button"
            title="Delete"
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

      <div
        className={`relative z-10 transition-opacity ${!visible ? 'pointer-events-none opacity-45' : ''}`}
      >
        {children}
      </div>
    </div>
  )
}
