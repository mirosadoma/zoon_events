export type PaletteBlockDrag = {
  kind: 'palette-block'
  blockType: string
  presetId?: string
}

export type PaletteElementDrag = {
  kind: 'palette-element'
  elementKind: string
}

export type CanvasBlockDrag = {
  kind: 'canvas-block'
  blockId: string
}

export type SectionElementDrag = {
  kind: 'section-element'
  blockId: string
  elementId: string
}

export type BuilderDragData = PaletteBlockDrag | PaletteElementDrag | CanvasBlockDrag | SectionElementDrag

export type BlockSlotDrop = {
  kind: 'block-slot'
  index: number
}

export type SectionDrop = {
  kind: 'section'
  blockId: string
}

export type SectionElementTargetDrop = {
  kind: 'section-element-target'
  blockId: string
  elementId: string
}

export type LayoutColumnDrop = {
  kind: 'layout-column'
  layoutPreset: string
  columnIndex: number
  insertIndex: number
}

export type BuilderDropData = BlockSlotDrop | SectionDrop | LayoutColumnDrop | SectionElementTargetDrop

export function isPaletteBlock(data: unknown): data is PaletteBlockDrag {
  return typeof data === 'object' && data !== null && (data as PaletteBlockDrag).kind === 'palette-block'
}

export function isPaletteElement(data: unknown): data is PaletteElementDrag {
  return typeof data === 'object' && data !== null && (data as PaletteElementDrag).kind === 'palette-element'
}

export function isCanvasBlock(data: unknown): data is CanvasBlockDrag {
  return typeof data === 'object' && data !== null && (data as CanvasBlockDrag).kind === 'canvas-block'
}

export function isSectionElement(data: unknown): data is SectionElementDrag {
  return typeof data === 'object' && data !== null && (data as SectionElementDrag).kind === 'section-element'
}

export function isBlockSlot(data: unknown): data is BlockSlotDrop {
  return typeof data === 'object' && data !== null && (data as BlockSlotDrop).kind === 'block-slot'
}

export function isSectionDrop(data: unknown): data is SectionDrop {
  return typeof data === 'object' && data !== null && (data as SectionDrop).kind === 'section'
}

export function isSectionElementTarget(data: unknown): data is SectionElementTargetDrop {
  return (
    typeof data === 'object' &&
    data !== null &&
    (data as SectionElementTargetDrop).kind === 'section-element-target'
  )
}

export function isLayoutColumn(data: unknown): data is LayoutColumnDrop {
  return typeof data === 'object' && data !== null && (data as LayoutColumnDrop).kind === 'layout-column'
}

/** Map palette aliases to real block types + default option overrides */
export function resolvePaletteBlock(blockType: string): { type: string; optionsPatch?: Record<string, unknown> } {
  switch (blockType) {
    case 'carousel':
      return {
        type: 'image_showcase',
        optionsPatch: { display: 'carousel', autoplay: true, columns: 3, show_arrows: true },
      }
    case 'grid_section':
      return { type: 'section' }
    default:
      return { type: blockType }
  }
}
