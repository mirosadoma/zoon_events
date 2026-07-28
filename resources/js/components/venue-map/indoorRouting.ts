import {
  haversineMeters,
  isGeoPoint,
  isRelativePoint,
  metersToLatDegrees,
  metersToLngDegrees,
  nearestPointOnSegment,
  relativePointsToGeo,
  type GeoPoint,
  type OverlayBounds,
} from '@/components/venue-map/geoCoordinates'
import type { MapPoint, ZoneShapeType } from '@/components/venue-map/types'

export type RoutingZone = {
  id: string
  type: string
  shape_type: ZoneShapeType | null
  coordinate_space?: 'relative' | 'geo'
  polygon_coordinates: MapPoint[] | null
  shape_radius: number | null
}

export type RoutingPath = {
  id: string
  coordinate_space?: 'relative' | 'geo'
  polyline_coordinates: MapPoint[]
}

type Segment = {
  a: GeoPoint
  b: GeoPoint
  aNodeId: string
  bNodeId: string
}

type Graph = {
  nodes: Map<string, GeoPoint>
  edges: Map<string, Array<{ to: string; cost: number }>>
  segments: Segment[]
}

type Anchor = {
  point: GeoPoint
  zoneId?: string
}

export type IndoorRouteResult = {
  indoorRoute: GeoPoint[]
  approachRoute: GeoPoint[]
  distanceMeters: number
  usedGateZoneId: string | null
}

const NODE_MERGE_METERS = 6
const ANCHOR_JOIN_METERS = 25
const OUTSIDE_NETWORK_METERS = 45

function asGeoPoints(points: MapPoint[] | null | undefined, bounds: OverlayBounds | null): GeoPoint[] {
  if (!points?.length) return []
  if (points.every(isGeoPoint)) {
    return points.map((point) => ({ lat: Number(point.lat), lng: Number(point.lng) }))
  }
  if (bounds && points.every(isRelativePoint)) {
    return relativePointsToGeo(points, bounds)
  }
  return []
}

function zoneAnchor(zone: RoutingZone, bounds: OverlayBounds | null): Anchor | null {
  const points = asGeoPoints(zone.polygon_coordinates, bounds)
  if (points.length === 0) return null

  const first = points[0]
  if (
    zone.shape_type === 'circle'
    || zone.shape_type === 'ellipse'
    || zone.shape_type === 'pillar'
    || zone.shape_type === 'person'
  ) {
    return { point: first, zoneId: zone.id }
  }

  const avg = points.reduce(
    (acc, point) => ({ lat: acc.lat + point.lat, lng: acc.lng + point.lng }),
    { lat: 0, lng: 0 },
  )

  return {
    point: {
      lat: avg.lat / points.length,
      lng: avg.lng / points.length,
    },
    zoneId: zone.id,
  }
}

function findOrCreateNode(
  nodes: Map<string, GeoPoint>,
  point: GeoPoint,
  thresholdMeters: number,
): string {
  for (const [nodeId, node] of nodes.entries()) {
    if (haversineMeters(point.lat, point.lng, node.lat, node.lng) <= thresholdMeters) {
      return nodeId
    }
  }

  const nextId = `n${nodes.size + 1}`
  nodes.set(nextId, point)
  return nextId
}

function addEdge(
  edges: Map<string, Array<{ to: string; cost: number }>>,
  from: string,
  to: string,
  cost: number,
) {
  if (!edges.has(from)) edges.set(from, [])
  edges.get(from)!.push({ to, cost })
}

function buildGraph(paths: RoutingPath[], bounds: OverlayBounds | null): Graph {
  const nodes = new Map<string, GeoPoint>()
  const edges = new Map<string, Array<{ to: string; cost: number }>>()
  const segments: Segment[] = []

  for (const path of paths) {
    const points = asGeoPoints(path.polyline_coordinates, bounds)
    if (points.length < 2) continue

    const nodeIds = points.map((point) => findOrCreateNode(nodes, point, NODE_MERGE_METERS))
    for (let index = 0; index < nodeIds.length - 1; index += 1) {
      const aNodeId = nodeIds[index]
      const bNodeId = nodeIds[index + 1]
      if (aNodeId === bNodeId) continue
      const a = nodes.get(aNodeId)!
      const b = nodes.get(bNodeId)!
      const cost = haversineMeters(a.lat, a.lng, b.lat, b.lng)
      if (cost <= 0.01) continue
      addEdge(edges, aNodeId, bNodeId, cost)
      addEdge(edges, bNodeId, aNodeId, cost)
      segments.push({ a, b, aNodeId, bNodeId })
    }
  }

  return { nodes, edges, segments }
}

function nearestNodeId(graph: Graph, point: GeoPoint): string | null {
  let bestNodeId: string | null = null
  let bestDistance = Number.POSITIVE_INFINITY

  for (const [nodeId, nodePoint] of graph.nodes.entries()) {
    const distance = haversineMeters(point.lat, point.lng, nodePoint.lat, nodePoint.lng)
    if (distance < bestDistance) {
      bestDistance = distance
      bestNodeId = nodeId
    }
  }

  return bestNodeId
}

function nearestProjection(graph: Graph, point: GeoPoint): { point: GeoPoint; segment: Segment; distanceMeters: number } | null {
  let best: { point: GeoPoint; segment: Segment; distanceMeters: number } | null = null

  for (const segment of graph.segments) {
    const projection = nearestPointOnSegment(point, segment.a, segment.b)
    if (!best || projection.distanceMeters < best.distanceMeters) {
      best = { point: projection.point, segment, distanceMeters: projection.distanceMeters }
    }
  }

  return best
}

function pointOutsideBounds(point: GeoPoint, bounds: OverlayBounds): boolean {
  const paddingMeters = 20
  return point.lat > bounds.north + metersToLatDegrees(paddingMeters)
    || point.lat < bounds.south - metersToLatDegrees(paddingMeters)
    || point.lng > bounds.east + metersToLngDegrees(paddingMeters, point.lat)
    || point.lng < bounds.west - metersToLngDegrees(paddingMeters, point.lat)
}

function isOutsideVenue(point: GeoPoint, graph: Graph, bounds: OverlayBounds | null): boolean {
  if (bounds && pointOutsideBounds(point, bounds)) return true
  const projection = nearestProjection(graph, point)
  if (!projection) return true
  return projection.distanceMeters > OUTSIDE_NETWORK_METERS
}

function connectPointToGraph(graph: Graph, point: GeoPoint, prefix: string): { id: string; graph: Graph } | null {
  if (graph.nodes.size === 0) return null
  const nextNodes = new Map(graph.nodes)
  const nextEdges = new Map(graph.edges)
  const projection = nearestProjection(graph, point)

  if (projection && projection.distanceMeters <= ANCHOR_JOIN_METERS) {
    const id = `${prefix}-proj`
    nextNodes.set(id, projection.point)
    const costA = haversineMeters(
      projection.point.lat,
      projection.point.lng,
      projection.segment.a.lat,
      projection.segment.a.lng,
    )
    const costB = haversineMeters(
      projection.point.lat,
      projection.point.lng,
      projection.segment.b.lat,
      projection.segment.b.lng,
    )
    addEdge(nextEdges, id, projection.segment.aNodeId, costA)
    addEdge(nextEdges, projection.segment.aNodeId, id, costA)
    addEdge(nextEdges, id, projection.segment.bNodeId, costB)
    addEdge(nextEdges, projection.segment.bNodeId, id, costB)
    return { id, graph: { ...graph, nodes: nextNodes, edges: nextEdges } }
  }

  const nearestId = nearestNodeId(graph, point)
  if (!nearestId) return null
  const nearest = graph.nodes.get(nearestId)!
  const id = `${prefix}-node`
  nextNodes.set(id, point)
  const cost = haversineMeters(point.lat, point.lng, nearest.lat, nearest.lng)
  addEdge(nextEdges, id, nearestId, cost)
  addEdge(nextEdges, nearestId, id, cost)
  return { id, graph: { ...graph, nodes: nextNodes, edges: nextEdges } }
}

function shortestPath(graph: Graph, startId: string, targetId: string): string[] {
  const distances = new Map<string, number>()
  const previous = new Map<string, string | null>()
  const unvisited = new Set<string>(graph.nodes.keys())

  for (const nodeId of unvisited) {
    distances.set(nodeId, Number.POSITIVE_INFINITY)
    previous.set(nodeId, null)
  }
  distances.set(startId, 0)

  while (unvisited.size > 0) {
    let current: string | null = null
    let currentDist = Number.POSITIVE_INFINITY

    for (const nodeId of unvisited) {
      const distance = distances.get(nodeId) ?? Number.POSITIVE_INFINITY
      if (distance < currentDist) {
        current = nodeId
        currentDist = distance
      }
    }

    if (!current || currentDist === Number.POSITIVE_INFINITY) break
    if (current === targetId) break

    unvisited.delete(current)
    const neighbors = graph.edges.get(current) ?? []
    for (const neighbor of neighbors) {
      if (!unvisited.has(neighbor.to)) continue
      const candidate = currentDist + neighbor.cost
      if (candidate < (distances.get(neighbor.to) ?? Number.POSITIVE_INFINITY)) {
        distances.set(neighbor.to, candidate)
        previous.set(neighbor.to, current)
      }
    }
  }

  const route: string[] = []
  let step: string | null = targetId
  while (step) {
    route.push(step)
    step = previous.get(step) ?? null
  }
  route.reverse()
  return route[0] === startId ? route : []
}

function routeDistance(points: GeoPoint[]): number {
  let total = 0
  for (let i = 0; i < points.length - 1; i += 1) {
    total += haversineMeters(points[i].lat, points[i].lng, points[i + 1].lat, points[i + 1].lng)
  }
  return total
}

function nearestGateAnchor(origin: GeoPoint, zones: RoutingZone[], bounds: OverlayBounds | null): Anchor | null {
  const gates = zones.filter((zone) => zone.type === 'gate')
  let best: { anchor: Anchor; distance: number } | null = null
  for (const gate of gates) {
    const anchor = zoneAnchor(gate, bounds)
    if (!anchor) continue
    const distance = haversineMeters(origin.lat, origin.lng, anchor.point.lat, anchor.point.lng)
    if (!best || distance < best.distance) {
      best = { anchor, distance }
    }
  }
  return best?.anchor ?? null
}

export function resolveZoneAnchorById(
  zoneId: string | null | undefined,
  zones: RoutingZone[],
  bounds: OverlayBounds | null,
): Anchor | null {
  if (!zoneId) return null
  const zone = zones.find((candidate) => candidate.id === zoneId)
  if (!zone) return null
  return zoneAnchor(zone, bounds)
}

export function routeToDestination({
  origin,
  destinationZoneId,
  startZoneId = null,
  zones,
  paths,
  overlayBounds = null,
}: {
  origin: GeoPoint | null
  destinationZoneId: string
  startZoneId?: string | null
  zones: RoutingZone[]
  paths: RoutingPath[]
  overlayBounds?: OverlayBounds | null
}): IndoorRouteResult | null {
  const destinationAnchor = resolveZoneAnchorById(destinationZoneId, zones, overlayBounds)
  if (!destinationAnchor) return null

  const graph = buildGraph(paths, overlayBounds)
  if (graph.nodes.size < 2) return null

  let startAnchor: Anchor | null = null
  let approachRoute: GeoPoint[] = []
  let usedGateZoneId: string | null = null

  if (startZoneId) {
    startAnchor = resolveZoneAnchorById(startZoneId, zones, overlayBounds)
  } else if (origin) {
    if (isOutsideVenue(origin, graph, overlayBounds)) {
      const gateAnchor = nearestGateAnchor(origin, zones, overlayBounds)
      if (gateAnchor) {
        startAnchor = gateAnchor
        approachRoute = [origin, gateAnchor.point]
        usedGateZoneId = gateAnchor.zoneId ?? null
      } else {
        startAnchor = { point: origin }
      }
    } else {
      startAnchor = { point: origin }
    }
  }

  if (!startAnchor) return null

  const connectedStart = connectPointToGraph(graph, startAnchor.point, 'start')
  if (!connectedStart) return null
  const connectedEnd = connectPointToGraph(connectedStart.graph, destinationAnchor.point, 'end')
  if (!connectedEnd) return null

  const nodePath = shortestPath(connectedEnd.graph, connectedStart.id, connectedEnd.id)
  if (nodePath.length < 2) return null

  const indoorRoute = nodePath
    .map((nodeId) => connectedEnd.graph.nodes.get(nodeId))
    .filter((point): point is GeoPoint => Boolean(point))

  if (indoorRoute.length < 2) return null

  const distanceMeters = routeDistance(approachRoute) + routeDistance(indoorRoute)

  return {
    indoorRoute,
    approachRoute,
    distanceMeters,
    usedGateZoneId,
  }
}
