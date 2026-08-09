export type CssLengthUnit = 'px' | '%' | 'vh' | 'vw' | 'rem' | 'em'

export type CssLength = {
  value: number
  unit: CssLengthUnit
}

export const CSS_LENGTH_UNITS: CssLengthUnit[] = ['px', '%', 'vh', 'vw', 'rem', 'em']

const UNIT_SET = new Set<string>(CSS_LENGTH_UNITS)

export function isCssLengthUnit(value: unknown): value is CssLengthUnit {
  return typeof value === 'string' && UNIT_SET.has(value)
}

/** Parse number / "400px" / options pair into a CssLength. */
export function parseCssLength(
  raw: unknown,
  unitRaw?: unknown,
  fallback: CssLength = { value: 400, unit: 'px' },
): CssLength {
  if (typeof raw === 'string') {
    const trimmed = raw.trim()
    const match = trimmed.match(/^(-?\d+(?:\.\d+)?)\s*(px|%|vh|vw|rem|em)?$/i)
    if (match) {
      const value = Number(match[1])
      const unit = (match[2]?.toLowerCase() as CssLengthUnit | undefined) ?? (isCssLengthUnit(unitRaw) ? unitRaw : 'px')
      if (Number.isFinite(value)) return { value, unit }
    }
  }

  if (typeof raw === 'number' && Number.isFinite(raw)) {
    return {
      value: raw,
      unit: isCssLengthUnit(unitRaw) ? unitRaw : fallback.unit,
    }
  }

  return { ...fallback }
}

export function formatCssLength(length: CssLength): string {
  const value = Number.isFinite(length.value) ? length.value : 0
  const unit = isCssLengthUnit(length.unit) ? length.unit : 'px'
  return `${value}${unit}`
}

/** Resolve options-style `{ valueKey, unitKey }` into a CSS length string. */
export function resolveCssLengthFromOptions(
  options: Record<string, unknown>,
  valueKey: string,
  unitKey: string,
  fallback: CssLength,
): string {
  return formatCssLength(parseCssLength(options[valueKey], options[unitKey], fallback))
}

export function cssLengthUnitOptions(locale: 'en' | 'ar' = 'en'): Array<{ value: string; label: string }> {
  return CSS_LENGTH_UNITS.map((unit) => ({
    value: unit,
    label: unit,
  }))
}

/** Soft min hints per unit for UI clamping (not hard CSS limits). */
export function defaultMinForUnit(unit: CssLengthUnit): number {
  switch (unit) {
    case '%':
    case 'vh':
    case 'vw':
      return 10
    case 'rem':
    case 'em':
      return 1
    default:
      return 40
  }
}

export function defaultValueForUnit(unit: CssLengthUnit, preferredPx = 400): number {
  switch (unit) {
    case '%':
      return 50
    case 'vh':
      return 50
    case 'vw':
      return 50
    case 'rem':
      return Math.max(1, Math.round(preferredPx / 16))
    case 'em':
      return Math.max(1, Math.round(preferredPx / 16))
    default:
      return preferredPx
  }
}
