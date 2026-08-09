export type SectionGridElement = {
  id: string
  col_span?: number
  col_start?: number
  order?: number
}

export function clampColSpan(span: number, colStart = 1): number {
  return Math.max(1, Math.min(12, span, 12 - colStart + 1))
}

export function clampColStart(start: number, span: number): number {
  return Math.max(1, Math.min(12 - span + 1, start))
}

export function gridColumnStyle(element: SectionGridElement, _editMode = false): { gridColumn?: string; ['--section-gc']?: string } {
  const span = clampColSpan(Number(element.col_span ?? 6), element.col_start ?? 1)
  const start = element.col_start

  const gc =
    start && start >= 1
      ? `${clampColStart(start, span)} / span ${span}`
      : `span ${span} / span ${span}`

  return { gridColumn: gc, ['--section-gc']: gc }
}

export function colFromPointer(gridWidth: number, clientX: number, gridLeft: number): number {
  if (gridWidth <= 0) return 1
  const x = clientX - gridLeft
  const col = Math.floor((x / gridWidth) * 12) + 1
  return Math.max(1, Math.min(12, col))
}

export function spanFromWidth(gridWidth: number, widthPx: number, colStart: number): number {
  const colWidth = gridWidth / 12
  if (colWidth <= 0) return 6
  const span = Math.round(widthPx / colWidth)
  return clampColSpan(span, colStart)
}
