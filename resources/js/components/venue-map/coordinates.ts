import type { RelativePoint } from '@/components/venue-map/types'

export function clamp01(value: number): number {
  if (Number.isNaN(value)) return 0
  return Math.min(1, Math.max(0, value))
}

export function toRelative(px: number, py: number, width: number, height: number): RelativePoint {
  if (width <= 0 || height <= 0) {
    return { x: 0, y: 0 }
  }

  return {
    x: Number(clamp01(px / width).toFixed(6)),
    y: Number(clamp01(py / height).toFixed(6)),
  }
}

export function toPixel(point: RelativePoint, width: number, height: number): { x: number; y: number } {
  return {
    x: point.x * width,
    y: point.y * height,
  }
}

export function pointsToFlat(points: RelativePoint[], width: number, height: number): number[] {
  return points.flatMap((point) => {
    const pixel = toPixel(point, width, height)
    return [pixel.x, pixel.y]
  })
}

export function rotateRelativePoints(
  points: RelativePoint[],
  degrees: number,
  pivot?: RelativePoint,
): RelativePoint[] {
  if (points.length === 0) return points

  const center = pivot ?? {
    x: points.reduce((sum, point) => sum + point.x, 0) / points.length,
    y: points.reduce((sum, point) => sum + point.y, 0) / points.length,
  }
  const radians = (degrees * Math.PI) / 180
  const cos = Math.cos(radians)
  const sin = Math.sin(radians)

  // Rotate without per-point clamping so corners keep the true shape orientation.
  const rotated = points.map((point) => {
    const dx = point.x - center.x
    const dy = point.y - center.y
    return {
      x: center.x + dx * cos - dy * sin,
      y: center.y + dx * sin + dy * cos,
    }
  })

  return fitRelativePoints(rotated)
}

/** Translate (and scale if needed) so every point stays inside 0–1 without skewing. */
export function fitRelativePoints(points: RelativePoint[]): RelativePoint[] {
  if (points.length === 0) return points

  let minX = Infinity
  let maxX = -Infinity
  let minY = Infinity
  let maxY = -Infinity

  for (const point of points) {
    minX = Math.min(minX, point.x)
    maxX = Math.max(maxX, point.x)
    minY = Math.min(minY, point.y)
    maxY = Math.max(maxY, point.y)
  }

  let shiftX = 0
  let shiftY = 0
  if (minX < 0) shiftX = -minX
  if (maxX + shiftX > 1) shiftX = 1 - maxX
  if (minY < 0) shiftY = -minY
  if (maxY + shiftY > 1) shiftY = 1 - maxY

  let next = points.map((point) => ({
    x: point.x + shiftX,
    y: point.y + shiftY,
  }))

  minX += shiftX
  maxX += shiftX
  minY += shiftY
  maxY += shiftY

  const width = maxX - minX
  const height = maxY - minY
  const needsScale = width > 1 || height > 1 || minX < 0 || maxX > 1 || minY < 0 || maxY > 1

  if (needsScale) {
    const scale = Math.min(
      1 / Math.max(width, 0.000001),
      1 / Math.max(height, 0.000001),
      1,
    )
    const midX = (minX + maxX) / 2
    const midY = (minY + maxY) / 2
    next = next.map((point) => ({
      x: 0.5 + (point.x - midX) * scale,
      y: 0.5 + (point.y - midY) * scale,
    }))
  }

  return next.map((point) => ({
    x: Number(clamp01(point.x).toFixed(6)),
    y: Number(clamp01(point.y).toFixed(6)),
  }))
}

export function snapRelative(
  point: RelativePoint,
  anchors: RelativePoint[],
  threshold = 0.012,
): RelativePoint {
  let best = point
  let bestDistance = threshold

  for (const anchor of anchors) {
    const dx = point.x - anchor.x
    const dy = point.y - anchor.y
    const distance = Math.hypot(dx, dy)
    if (distance <= bestDistance) {
      best = anchor
      bestDistance = distance
    }
  }

  return best
}

export function rectangleFromCorners(a: RelativePoint, b: RelativePoint): RelativePoint[] {
  const minX = Math.min(a.x, b.x)
  const maxX = Math.max(a.x, b.x)
  const minY = Math.min(a.y, b.y)
  const maxY = Math.max(a.y, b.y)

  return [
    { x: minX, y: minY },
    { x: maxX, y: minY },
    { x: maxX, y: maxY },
    { x: minX, y: maxY },
  ]
}

export function relativeRadius(center: RelativePoint, edge: RelativePoint, imageAspect: number): number {
  const dx = (edge.x - center.x) * imageAspect
  const dy = edge.y - center.y
  return Number(clamp01(Math.hypot(dx, dy)).toFixed(6))
}

export function centroid(points: RelativePoint[]): RelativePoint {
  if (points.length === 0) {
    return { x: 0.5, y: 0.5 }
  }

  const sum = points.reduce(
    (acc, point) => ({ x: acc.x + point.x, y: acc.y + point.y }),
    { x: 0, y: 0 },
  )

  return {
    x: sum.x / points.length,
    y: sum.y / points.length,
  }
}

/** Flat local pixel points centered on the shape centroid, plus the centroid in pixels. */
export function centeredLocalFlat(
  points: RelativePoint[],
  width: number,
  height: number,
): { centerX: number; centerY: number; local: number[] } {
  const center = centroid(points)
  const centerPx = toPixel(center, width, height)
  const local = points.flatMap((point) => {
    const px = toPixel(point, width, height)
    return [px.x - centerPx.x, px.y - centerPx.y]
  })

  return {
    centerX: centerPx.x,
    centerY: centerPx.y,
    local,
  }
}

export function absoluteFromCenteredLocal(
  centerX: number,
  centerY: number,
  local: number[],
  width: number,
  height: number,
): RelativePoint[] {
  const points: RelativePoint[] = []
  for (let index = 0; index < local.length; index += 2) {
    points.push(toRelative(centerX + local[index], centerY + local[index + 1], width, height))
  }
  return points
}

export function regularPolygonPoints(
  center: RelativePoint,
  edge: RelativePoint,
  sides: number,
): RelativePoint[] {
  const dx = edge.x - center.x
  const dy = edge.y - center.y
  const radius = Math.hypot(dx, dy)
  const startAngle = Math.atan2(dy, dx)

  return Array.from({ length: sides }, (_, index) => {
    const angle = startAngle + (index * 2 * Math.PI) / sides
    return {
      x: Number(clamp01(center.x + Math.cos(angle) * radius).toFixed(6)),
      y: Number(clamp01(center.y + Math.sin(angle) * radius).toFixed(6)),
    }
  })
}

export function normalizeDegrees(degrees: number): number {
  if (!Number.isFinite(degrees)) return 0
  const wrapped = ((degrees % 360) + 360) % 360
  return Number((wrapped > 180 ? wrapped - 360 : wrapped).toFixed(3))
}
