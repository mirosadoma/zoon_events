import {
  DndContext,
  DragOverlay,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from '@dnd-kit/core'
import { sortableKeyboardCoordinates } from '@dnd-kit/sortable'
import { useState, type ReactNode } from 'react'
import type { BuilderDragData } from '@/lib/siteBuilderDnd'
import {
  isBlockSlot,
  isCanvasBlock,
  isLayoutColumn,
  isPaletteBlock,
  isPaletteElement,
  isSectionDrop,
  isSectionElement,
  isSectionElementTarget,
} from '@/lib/siteBuilderDnd'

type Props = {
  children: ReactNode
  onDragStateChange?: (active: boolean, data: BuilderDragData | null) => void
  onPaletteBlockDrop: (blockType: string, index: number, presetId?: string) => void
  onPaletteElementDrop: (blockId: string, elementKind: string) => void
  onPaletteElementDropAfter?: (blockId: string, elementKind: string, afterElementId: string) => void
  onLayoutColumnDrop: (
    layoutPreset: string,
    columnIndex: number,
    insertIndex: number,
    activeData: BuilderDragData,
  ) => void
  onMoveSectionElement: (fromBlockId: string, elementId: string, toBlockId: string) => void
  onReorderSectionElement: (blockId: string, activeElementId: string, overElementId: string) => void
  onMoveSectionElementBefore: (
    fromBlockId: string,
    elementId: string,
    toBlockId: string,
    beforeElementId: string,
  ) => void
  onReorderBlocks: (activeId: string, overId: string) => void
}

export default function BuilderDragProvider({
  children,
  onDragStateChange,
  onPaletteBlockDrop,
  onPaletteElementDrop,
  onPaletteElementDropAfter,
  onLayoutColumnDrop,
  onMoveSectionElement,
  onReorderSectionElement,
  onMoveSectionElementBefore,
  onReorderBlocks,
}: Props) {
  const [activeLabel, setActiveLabel] = useState<string | null>(null)

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  function handleDragStart(event: DragStartEvent) {
    const data = event.active.data.current
    if (isPaletteBlock(data)) {
      setActiveLabel(data.blockType)
      onDragStateChange?.(true, data)
    } else if (isPaletteElement(data)) {
      setActiveLabel(data.elementKind)
      onDragStateChange?.(true, data)
    } else if (isSectionElement(data)) {
      setActiveLabel('element')
      onDragStateChange?.(true, data)
    } else if (isCanvasBlock(data)) {
      setActiveLabel('section')
      onDragStateChange?.(true, data)
    }
  }

  function handleDragEnd(event: DragEndEvent) {
    setActiveLabel(null)
    onDragStateChange?.(false, null)
    const { active, over } = event
    if (!over) return

    const activeData = active.data.current
    const overData = over.data.current

    if (isLayoutColumn(overData) && activeData) {
      if (isPaletteElement(activeData) || isPaletteBlock(activeData) || isSectionElement(activeData)) {
        onLayoutColumnDrop(
          overData.layoutPreset,
          overData.columnIndex,
          overData.insertIndex,
          activeData as BuilderDragData,
        )
        return
      }
    }

    if (isPaletteBlock(activeData)) {
      if (isBlockSlot(overData)) {
        onPaletteBlockDrop(activeData.blockType, overData.index, activeData.presetId)
        return
      }
    }

    if (isPaletteElement(activeData) && isSectionElementTarget(overData)) {
      if (onPaletteElementDropAfter) {
        onPaletteElementDropAfter(overData.blockId, activeData.elementKind, overData.elementId)
      } else {
        onPaletteElementDrop(overData.blockId, activeData.elementKind)
      }
      return
    }

    if (isPaletteElement(activeData) && isSectionDrop(overData)) {
      onPaletteElementDrop(overData.blockId, activeData.elementKind)
      return
    }

    if (isSectionElement(activeData) && isSectionElementTarget(overData)) {
      if (activeData.elementId === overData.elementId) return
      if (activeData.blockId === overData.blockId) {
        onReorderSectionElement(activeData.blockId, activeData.elementId, overData.elementId)
      } else {
        onMoveSectionElementBefore(
          activeData.blockId,
          activeData.elementId,
          overData.blockId,
          overData.elementId,
        )
      }
      return
    }

    if (isSectionElement(activeData) && isSectionDrop(overData)) {
      if (activeData.blockId !== overData.blockId) {
        onMoveSectionElement(activeData.blockId, activeData.elementId, overData.blockId)
      }
      return
    }

    if (isCanvasBlock(activeData) && isCanvasBlock(overData)) {
      if (activeData.blockId !== overData.blockId) {
        onReorderBlocks(activeData.blockId, overData.blockId)
      }
    }
  }

  return (
    <DndContext
      sensors={sensors}
      collisionDetection={closestCenter}
      onDragStart={handleDragStart}
      onDragEnd={handleDragEnd}
    >
      {children}
      <DragOverlay dropAnimation={{ duration: 180 }}>
        {activeLabel ? (
          <div className="rounded-lg border border-violet-400/60 bg-[#1a1a2e]/95 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-violet-200 shadow-xl">
            {activeLabel}
          </div>
        ) : null}
      </DragOverlay>
    </DndContext>
  )
}
