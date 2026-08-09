import { useCallback } from 'react'
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import {
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { Eye, EyeOff, Trash2, GripVertical } from 'lucide-react'

type SiteBlock = {
  id: string
  type: string
  visible: boolean
  page_id?: string
  content_en: Record<string, unknown>
  content_ar: Record<string, unknown>
}

type BlockTypeLabels = Record<string, { en: string; ar: string }>

type Props = {
  blocks: SiteBlock[]
  selectedId: string | null
  locale: 'en' | 'ar'
  blockTypeLabels: BlockTypeLabels
  onSelect: (id: string) => void
  onMove: (fromIndex: number, toIndex: number) => void
  onRemove: (id: string) => void
  onToggleVisibility: (id: string) => void
}

function SortableBlockItem({
  block,
  isSelected,
  locale,
  blockTypeLabels,
  onSelect,
  onRemove,
  onToggleVisibility,
}: {
  block: SiteBlock
  isSelected: boolean
  locale: 'en' | 'ar'
  blockTypeLabels: BlockTypeLabels
  onSelect: () => void
  onRemove: () => void
  onToggleVisibility: () => void
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: block.id,
  })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
    zIndex: isDragging ? 1000 : undefined,
  }

  const label = blockTypeLabels[block.type]?.[locale] ?? block.type
  const content = locale === 'ar' ? block.content_ar : block.content_en
  const title = typeof content.title === 'string' ? content.title : ''

  return (
    <div
      ref={setNodeRef}
      style={style}
      onClick={onSelect}
      className={`
        group flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors
        ${isSelected
          ? 'border-primary bg-primary/5'
          : 'border-border hover:border-primary/50 hover:bg-muted/50'
        }
        ${!block.visible ? 'opacity-50' : ''}
      `}
    >
      <button
        type="button"
        className="cursor-grab text-muted-foreground hover:text-foreground touch-none"
        {...attributes}
        {...listeners}
      >
        <GripVertical className="w-5 h-5" />
      </button>

      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium truncate">{label}</p>
        {title && (
          <p className="text-xs text-muted-foreground truncate">{title}</p>
        )}
        {block.page_id && block.page_id !== 'home' && (
          <p className="text-xs text-primary/70 truncate">📄 {block.page_id}</p>
        )}
      </div>

      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation()
            onToggleVisibility()
          }}
          className="p-1 rounded hover:bg-muted"
          title={block.visible ? 'Hide' : 'Show'}
        >
          {block.visible ? (
            <Eye className="w-4 h-4 text-muted-foreground" />
          ) : (
            <EyeOff className="w-4 h-4 text-muted-foreground" />
          )}
        </button>

        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation()
            onRemove()
          }}
          className="p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30"
          title="Remove"
        >
          <Trash2 className="w-4 h-4 text-red-500" />
        </button>
      </div>
    </div>
  )
}

export default function SiteBlockList({
  blocks,
  selectedId,
  locale,
  blockTypeLabels,
  onSelect,
  onMove,
  onRemove,
  onToggleVisibility,
}: Props) {
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 5,
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    }),
  )

  const handleDragEnd = useCallback(
    (event: DragEndEvent) => {
      const { active, over } = event
      if (over && active.id !== over.id) {
        const fromIndex = blocks.findIndex((b) => b.id === active.id)
        const toIndex = blocks.findIndex((b) => b.id === over.id)
        if (fromIndex !== -1 && toIndex !== -1) {
          onMove(fromIndex, toIndex)
        }
      }
    },
    [blocks, onMove],
  )

  return (
    <DndContext
      sensors={sensors}
      collisionDetection={closestCenter}
      onDragEnd={handleDragEnd}
    >
      <SortableContext
        items={blocks.map((b) => b.id)}
        strategy={verticalListSortingStrategy}
      >
        <div className="space-y-2">
          {blocks.map((block) => (
            <SortableBlockItem
              key={block.id}
              block={block}
              isSelected={block.id === selectedId}
              locale={locale}
              blockTypeLabels={blockTypeLabels}
              onSelect={() => onSelect(block.id)}
              onRemove={() => onRemove(block.id)}
              onToggleVisibility={() => onToggleVisibility(block.id)}
            />
          ))}
        </div>
      </SortableContext>
    </DndContext>
  )
}
