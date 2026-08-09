import type { CSSProperties } from 'react'
import { formatCssLength, parseCssLength } from '@/lib/cssLength'

export type FreeformPlacement = {
  x_pct: number
  y_pct: number
  width_pct: number
  height_pct?: number
  z_index?: number
}

export type FreeformElementInput = {
  col_span?: number
  col_start?: number
  order?: number
  x_pct?: number
  y_pct?: number
  width_pct?: number
  height_pct?: number
  z_index?: number
}

export function isFreeformSection(options: Record<string, unknown>): boolean {
  return options.layout_mode === 'freeform'
}

/** Numeric px fallback used by editor math when unit is px; otherwise a reasonable canvas default. */
export function resolveFreeformHeight(options: Record<string, unknown>): number {
  const length = parseCssLength(options.freeform_height, options.freeform_height_unit, {
    value: 480,
    unit: 'px',
  })
  if (length.unit === 'px' && length.value >= 200 && length.value <= 2400) return length.value
  // Non-px units still need a canvas working height for absolute positioning math.
  if (length.unit === 'vh') return Math.round(Math.max(200, Math.min(2400, (length.value / 100) * 900)))
  if (length.unit === '%') return Math.round(Math.max(200, Math.min(2400, (length.value / 100) * 800)))
  if (length.unit === 'rem' || length.unit === 'em') {
    return Math.round(Math.max(200, Math.min(2400, length.value * 16)))
  }
  if (length.unit === 'vw') return Math.round(Math.max(200, Math.min(2400, (length.value / 100) * 1200)))
  return 480
}

export function resolveFreeformHeightCss(options: Record<string, unknown>): string {
  return formatCssLength(
    parseCssLength(options.freeform_height, options.freeform_height_unit, {
      value: 480,
      unit: 'px',
    }),
  )
}

export function clampPct(n: number, min = 0, max = 100): number {
  return Math.max(min, Math.min(max, n))
}

export function gridToFreeformPlacement(element: FreeformElementInput, index = 0): FreeformPlacement {
  const span = Math.max(1, Math.min(12, Number(element.col_span ?? 6)))
  const start = element.col_start ?? 1
  const x_pct = clampPct(((start - 1) / 12) * 100, 0, 95)
  const width_pct = clampPct((span / 12) * 100, 5, 100)
  const perRow = Math.max(1, Math.floor(12 / span))
  const y_pct = clampPct(Math.floor(index / perRow) * 22 + 5, 0, 85)
  return { x_pct, y_pct, width_pct, z_index: index + 1 }
}

export function defaultFreeformPlacement(order = 0): FreeformPlacement {
  const row = Math.floor(order / 3)
  const col = order % 3
  return {
    x_pct: clampPct(col * 32 + 2, 0, 90),
    y_pct: clampPct(row * 22 + 5, 0, 85),
    width_pct: 30,
    z_index: order + 1,
  }
}

export function resolveFreeformPlacement(element: FreeformElementInput, index = 0): FreeformPlacement {
  if (element.x_pct !== undefined && element.width_pct !== undefined) {
    return {
      x_pct: clampPct(element.x_pct, 0, 95),
      y_pct: clampPct(element.y_pct ?? 0, 0, 95),
      width_pct: clampPct(element.width_pct, 5, 100),
      height_pct:
        element.height_pct !== undefined ? clampPct(element.height_pct, 5, 100) : undefined,
      z_index: element.z_index ?? index + 1,
    }
  }
  return gridToFreeformPlacement(element, index)
}

export function freeformStyle(placement: FreeformPlacement): CSSProperties {
  const style: CSSProperties = {
    position: 'absolute',
    left: `${placement.x_pct}%`,
    top: `${placement.y_pct}%`,
    width: `${placement.width_pct}%`,
    zIndex: placement.z_index ?? 1,
  }
  if (placement.height_pct !== undefined) {
    style.height = `${placement.height_pct}%`
    style.overflow = 'auto'
  }
  return style
}

/** Pack grid elements onto freeform canvas, keeping same-row items aligned. */
export function convertElementsToFreeform<T extends FreeformElementInput>(elements: T[]): T[] {
  let cursor = 1
  let row = 0

  return elements.map((el, i) => {
    const span = Math.max(1, Math.min(12, Number(el.col_span ?? 6)))
    let start =
      el.col_start !== undefined && el.col_start >= 1
        ? Math.max(1, Math.min(12 - span + 1, el.col_start))
        : cursor

    if (el.col_start !== undefined && el.col_start >= 1) {
      // Explicit start before the cursor means a wrapped new row.
      if (start < cursor && cursor > 1) {
        row += 1
      }
    } else if (cursor + span - 1 > 12) {
      row += 1
      start = 1
      cursor = 1
    }

    const placement: FreeformPlacement = {
      x_pct: clampPct(((start - 1) / 12) * 100, 0, 95),
      y_pct: clampPct(row * 22 + 5, 0, 85),
      width_pct: clampPct((span / 12) * 100, 5, 100),
      z_index: i + 1,
    }

    cursor = start + span
    if (cursor > 12) {
      cursor = 1
      row += 1
    }

    return {
      ...el,
      ...placement,
    }
  })
}

export function pointerToFreeformPct(
  canvasRect: DOMRect,
  clientX: number,
  clientY: number,
): { x_pct: number; y_pct: number } {
  const x = clientX - canvasRect.left
  const y = clientY - canvasRect.top
  const x_pct = canvasRect.width > 0 ? clampPct((x / canvasRect.width) * 100, 0, 95) : 0
  const y_pct = canvasRect.height > 0 ? clampPct((y / canvasRect.height) * 100, 0, 95) : 0
  return { x_pct, y_pct }
}
