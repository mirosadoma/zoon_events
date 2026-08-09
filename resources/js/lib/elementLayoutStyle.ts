import type { CSSProperties } from 'react'

export type ElementLayoutInput = {
  col_span?: number
  col_start?: number
  align?: 'start' | 'center' | 'end'
}

/** Vertical stack layout (legacy). Prefer CSS grid + gridColumn for section elements. */
export function elementStackLayoutStyle(element: ElementLayoutInput): CSSProperties {
  const span = Math.max(1, Math.min(12, Number(element.col_span ?? 6)))
  const start = element.col_start !== undefined && element.col_start >= 1 ? element.col_start : 1
  const widthPct = (span / 12) * 100
  const offsetPct = ((start - 1) / 12) * 100
  const align = element.align ?? 'start'

  const style: CSSProperties = {
    width: `${widthPct}%`,
    maxWidth: '100%',
    flexShrink: 0,
    boxSizing: 'border-box',
  }

  if (align === 'center') {
    style.marginInlineStart = 'auto'
    style.marginInlineEnd = 'auto'
  } else if (align === 'end') {
    style.marginInlineStart = 'auto'
    style.marginInlineEnd = '0'
  } else if (offsetPct > 0) {
    style.marginInlineStart = `${offsetPct}%`
  }

  return style
}

export function alignElementPatch(align: 'start' | 'center' | 'end'): Record<string, unknown> {
  if (align === 'center') {
    return { align: 'center', col_start: null }
  }
  if (align === 'end') {
    return { align: 'end', col_start: null }
  }
  return { align: 'start', col_start: 1 }
}

export function mergeElementLayoutPatch(
  element: Record<string, unknown>,
  patch: Record<string, unknown>,
): Record<string, unknown> {
  const next = { ...element, ...patch }
  if (patch.col_start === null) {
    delete next.col_start
  }
  if (patch.align === 'center' || patch.align === 'end') {
    delete next.col_start
  }
  return next
}

export function widthElementPatch(colSpan: number): Partial<ElementLayoutInput> {
  return { col_span: Math.max(1, Math.min(12, colSpan)) }
}

export const WIDTH_PRESETS = [
  { span: 3, labelEn: '25%', labelAr: '25%' },
  { span: 4, labelEn: '33%', labelAr: '33%' },
  { span: 6, labelEn: '50%', labelAr: '50%' },
  { span: 8, labelEn: '66%', labelAr: '66%' },
  { span: 12, labelEn: '100%', labelAr: '100%' },
] as const

export function inheritElementPlacement(
  source: ElementLayoutInput,
): Pick<ElementLayoutInput, 'col_span' | 'col_start' | 'align'> {
  return {
    col_span: source.col_span ?? 6,
    col_start: source.col_start,
    align: source.align,
  }
}

export function insertElementAfter<T extends { id: string }>(items: T[], activeId: string, afterId: string): T[] {
  const activeIdx = items.findIndex((e) => e.id === activeId)
  const afterIdx = items.findIndex((e) => e.id === afterId)
  if (activeIdx === -1 || afterIdx === -1) return items

  const active = items[activeIdx]
  const next = items.filter((e) => e.id !== activeId)
  const insertAt = next.findIndex((e) => e.id === afterId) + 1
  return [...next.slice(0, insertAt), active, ...next.slice(insertAt)]
}

export function insertNewAfter<T extends { id: string }>(
  items: T[],
  newItem: T,
  afterId: string,
): T[] {
  const afterIdx = items.findIndex((e) => e.id === afterId)
  if (afterIdx === -1) return [...items, newItem]
  return [...items.slice(0, afterIdx + 1), newItem, ...items.slice(afterIdx + 1)]
}
