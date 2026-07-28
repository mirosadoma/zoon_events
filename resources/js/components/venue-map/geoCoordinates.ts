export type GeoPoint = {
  lat: number
  lng: number
}

export type RelativePoint = {
  x: number
  y: number
}

export type MapPoint = GeoPoint | RelativePoint

export function isGeoPoint(point: MapPoint | null | undefined): point is GeoPoint {
  return Boolean(point && 'lat' in point && 'lng' in point)
}

export function isRelativePoint(point: MapPoint | null | undefined): point is RelativePoint {
  return Boolean(point && 'x' in point && 'y' in point)
}

export type OverlayBounds = {
  north: number
  south: number
  east: number
  west: number
}

export function boundsFromCamera(
  lat: number,
  lng: number,
  zoom: number,
  aspect = 1.6,
): OverlayBounds {
  const safeZoom = Math.min(22, Math.max(1, zoom))
  const latSpan = 360 / (2 ** safeZoom) * 0.55
  const lngSpan = latSpan * Math.max(0.5, aspect)
  return {
    north: lat + latSpan / 2,
    south: lat - latSpan / 2,
    east: lng + lngSpan / 2,
    west: lng - lngSpan / 2,
  }
}

/** Shrink camera/viewport bounds around the center (for initial floor-plan placement). */
export function insetOverlayBounds(bounds: OverlayBounds, factor = 0.4): OverlayBounds {
  const safe = Math.min(0.95, Math.max(0.1, factor))
  const latMid = (bounds.north + bounds.south) / 2
  const lngMid = (bounds.east + bounds.west) / 2
  const latHalf = ((bounds.north - bounds.south) / 2) * safe
  const lngHalf = ((bounds.east - bounds.west) / 2) * safe
  return {
    north: latMid + latHalf,
    south: latMid - latHalf,
    east: lngMid + lngHalf,
    west: lngMid - lngHalf,
  }
}

export function relativeToGeo(
  point: RelativePoint,
  bounds: OverlayBounds,
): GeoPoint {
  const x = Math.min(1, Math.max(0, point.x))
  const y = Math.min(1, Math.max(0, point.y))
  return {
    lat: bounds.north + (bounds.south - bounds.north) * y,
    lng: bounds.west + (bounds.east - bounds.west) * x,
  }
}

export function relativePointsToGeo(
  points: RelativePoint[],
  bounds: OverlayBounds,
): GeoPoint[] {
  return points.map((point) => relativeToGeo(point, bounds))
}

export function relativeRadiusToMeters(
  relativeRadius: number,
  bounds: OverlayBounds,
): number {
  const centerLat = (bounds.north + bounds.south) / 2
  const widthMeters = haversineMeters(centerLat, bounds.west, centerLat, bounds.east)
  const heightMeters = haversineMeters(bounds.north, bounds.west, bounds.south, bounds.west)
  return Math.max(0.5, relativeRadius * Math.max(widthMeters, heightMeters, 1))
}

export function haversineMeters(lat1: number, lng1: number, lat2: number, lng2: number): number {
  const earth = 6371000
  const dLat = ((lat2 - lat1) * Math.PI) / 180
  const dLng = ((lng2 - lng1) * Math.PI) / 180
  const a = Math.sin(dLat / 2) ** 2
    + Math.cos((lat1 * Math.PI) / 180)
    * Math.cos((lat2 * Math.PI) / 180)
    * Math.sin(dLng / 2) ** 2
  return 2 * earth * Math.asin(Math.min(1, Math.sqrt(a)))
}

/** Closest point on segment AB to P, in lat/lng (planar approximation). */
export function nearestPointOnSegment(
  point: GeoPoint,
  a: GeoPoint,
  b: GeoPoint,
): { point: GeoPoint; distanceMeters: number } {
  const ax = a.lng
  const ay = a.lat
  const bx = b.lng
  const by = b.lat
  const px = point.lng
  const py = point.lat
  const dx = bx - ax
  const dy = by - ay
  const lengthSq = dx * dx + dy * dy
  const t = lengthSq === 0
    ? 0
    : Math.min(1, Math.max(0, ((px - ax) * dx + (py - ay) * dy) / lengthSq))
  const nearest = { lat: ay + t * dy, lng: ax + t * dx }
  return {
    point: nearest,
    distanceMeters: haversineMeters(point.lat, point.lng, nearest.lat, nearest.lng),
  }
}

/**
 * Snap a geo point to the nearest zone edge when within threshold meters.
 * Otherwise returns the original point (so paths can enter inside shapes).
 */
export function snapPathPointToZoneEdges(
  point: GeoPoint,
  zonePolygons: GeoPoint[][],
  thresholdMeters = 8,
): { point: GeoPoint; snapped: boolean } {
  let best: { point: GeoPoint; distanceMeters: number } | null = null

  for (const polygon of zonePolygons) {
    if (polygon.length < 2) continue
    const closed = polygon.length >= 3
      ? [...polygon, polygon[0]]
      : polygon
    for (let i = 0; i < closed.length - 1; i += 1) {
      const candidate = nearestPointOnSegment(point, closed[i], closed[i + 1])
      if (!best || candidate.distanceMeters < best.distanceMeters) {
        best = candidate
      }
    }
  }

  if (best && best.distanceMeters <= thresholdMeters) {
    return { point: best.point, snapped: true }
  }

  return { point, snapped: false }
}

export function snapPathPointToPathSegments(
  point: GeoPoint,
  pathPolylines: GeoPoint[][],
  thresholdMeters = 8,
): { point: GeoPoint; snapped: boolean } {
  let best: { point: GeoPoint; distanceMeters: number } | null = null

  for (const polyline of pathPolylines) {
    if (polyline.length < 2) continue
    for (let i = 0; i < polyline.length - 1; i += 1) {
      const candidate = nearestPointOnSegment(point, polyline[i], polyline[i + 1])
      if (!best || candidate.distanceMeters < best.distanceMeters) {
        best = candidate
      }
    }
  }

  if (best && best.distanceMeters <= thresholdMeters) {
    return { point: best.point, snapped: true }
  }

  return { point, snapped: false }
}

/** Approximate meters → degrees latitude. */
export function metersToLatDegrees(meters: number): number {
  return meters / 111320
}

export function metersToLngDegrees(meters: number, atLat: number): number {
  return meters / (111320 * Math.max(0.2, Math.cos((atLat * Math.PI) / 180)))
}

export function rectangleGeoFromCorners(a: GeoPoint, b: GeoPoint): GeoPoint[] {
  const north = Math.max(a.lat, b.lat)
  const south = Math.min(a.lat, b.lat)
  const east = Math.max(a.lng, b.lng)
  const west = Math.min(a.lng, b.lng)
  return [
    { lat: north, lng: west },
    { lat: north, lng: east },
    { lat: south, lng: east },
    { lat: south, lng: west },
  ]
}

export function regularPolygonGeo(
  center: GeoPoint,
  edge: GeoPoint,
  sides: number,
): GeoPoint[] {
  const radiusMeters = haversineMeters(center.lat, center.lng, edge.lat, edge.lng)
  const startAngle = Math.atan2(edge.lng - center.lng, edge.lat - center.lat)
  const points: GeoPoint[] = []
  for (let i = 0; i < sides; i += 1) {
    const angle = startAngle + (i * 2 * Math.PI) / sides
    const dLat = metersToLatDegrees(radiusMeters * Math.cos(angle))
    const dLng = metersToLngDegrees(radiusMeters * Math.sin(angle), center.lat)
    points.push({
      lat: center.lat + dLat,
      lng: center.lng + dLng,
    })
  }
  return points
}

export function centroidGeo(points: GeoPoint[]): GeoPoint {
  if (points.length === 0) return { lat: 0, lng: 0 }
  const sum = points.reduce(
    (acc, point) => ({ lat: acc.lat + point.lat, lng: acc.lng + point.lng }),
    { lat: 0, lng: 0 },
  )
  return {
    lat: sum.lat / points.length,
    lng: sum.lng / points.length,
  }
}

export function boundsFromGeoPoints(points: GeoPoint[]): OverlayBounds | null {
  if (points.length === 0) return null
  let north = points[0].lat
  let south = points[0].lat
  let east = points[0].lng
  let west = points[0].lng
  for (const point of points) {
    north = Math.max(north, point.lat)
    south = Math.min(south, point.lat)
    east = Math.max(east, point.lng)
    west = Math.min(west, point.lng)
  }
  return { north, south, east, west }
}

/** Rotate geo points around a pivot using a local equirectangular approximation. */
export function rotateGeoPointsAround(
  points: GeoPoint[],
  center: GeoPoint,
  degrees: number,
): GeoPoint[] {
  if (points.length === 0 || degrees === 0) return points
  const rad = (degrees * Math.PI) / 180
  const cos = Math.cos(rad)
  const sin = Math.sin(rad)
  const cosLat = Math.max(0.2, Math.cos((center.lat * Math.PI) / 180))

  return points.map((point) => {
    const dLat = point.lat - center.lat
    const dLng = (point.lng - center.lng) * cosLat
    const rLat = dLat * cos - dLng * sin
    const rLng = dLat * sin + dLng * cos
    return {
      lat: center.lat + rLat,
      lng: center.lng + rLng / cosLat,
    }
  })
}

export function circleBoundsFromCenterRadius(center: GeoPoint, radiusMeters: number): OverlayBounds {
  const dLat = metersToLatDegrees(radiusMeters)
  const dLng = metersToLngDegrees(radiusMeters, center.lat)
  return {
    north: center.lat + dLat,
    south: center.lat - dLat,
    east: center.lng + dLng,
    west: center.lng - dLng,
  }
}
