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
