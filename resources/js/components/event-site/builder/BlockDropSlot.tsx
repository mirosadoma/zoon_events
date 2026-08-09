import { useDroppable, useDndContext } from '@dnd-kit/core'
import { useEffect } from 'react'

type Props = {
  id: string
  index: number
  active?: boolean
  onHover?: (index: number) => void
}

export default function BlockDropSlot({ id, index, active = true, onHover }: Props) {
  const { active: dndActive } = useDndContext()
  const dragging = Boolean(dndActive)
  const enabled = active && dragging

  const { setNodeRef, isOver } = useDroppable({
    id,
    data: { kind: 'block-slot', index },
    disabled: !enabled,
  })

  useEffect(() => {
    if (isOver && onHover) onHover(index)
  }, [isOver, index, onHover])

  // Keep drop targets out of the layout until a drag is in progress.
  if (!enabled) return null

  return (
    <div
      ref={setNodeRef}
      className={`relative z-30 transition-all ${isOver ? 'py-3' : 'py-1'}`}
    >
      <div
        className={`mx-4 rounded-full border-2 border-dashed transition-all ${
          isOver ? 'border-violet-400 bg-violet-500/20 py-2' : 'border-violet-400/40 py-0.5'
        }`}
      />
    </div>
  )
}
