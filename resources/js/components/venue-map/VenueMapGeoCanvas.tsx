import { Circle, GoogleMap, Polygon, Polyline, useJsApiLoader } from '@react-google-maps/api'
import type { Libraries } from '@react-google-maps/api'
import { memo, useCallback, useEffect, useMemo, useRef, useState, type MutableRefObject } from 'react'
import RotatableFloorOverlay from '@/components/venue-map/RotatableFloorOverlay'
import ShapeRotateOverlay from '@/components/venue-map/ShapeRotateOverlay'
import ShapeMoveHandle from '@/components/venue-map/ShapeMoveHandle'
import MapHoverTooltip from '@/components/venue-map/MapHoverTooltip'
import GeoMarkerOverlay, { isGeoMarkerShape } from '@/components/venue-map/GeoMarkerOverlay'
import {
  boundsFromCamera,
  haversineMeters,
  nearestPointOnSegment,
  rectangleGeoFromCorners,
  regularPolygonGeo,
  snapPathPointToPathSegments,
  snapPathPointToZoneEdges,
  type GeoPoint,
  type OverlayBounds,
} from '@/components/venue-map/geoCoordinates'
import {
  DEFAULT_PATH_COLOR,
  defaultFillForType,
  type EditorTool,
  type MapPath,
  type MapZone,
  type ZoneShapeType,
} from '@/components/venue-map/types'
import { useLocale } from '@/hooks/useLocale'

type Props = {
  latitude: number
  longitude: number
  zoom: number
  heading: number
  mapTypeId: 'roadmap' | 'satellite' | 'hybrid'
  imageUrl: string | null
  overlayBounds: OverlayBounds | null
  overlayOpacity: number
  overlayRotation?: number
  removeBackground?: boolean
  zones: MapZone[]
  paths: MapPath[]
  selectedKey: string | null
  selectedPathKey: string | null
  tool: EditorTool
  onSelect: (key: string | null) => void
  onSelectPath: (key: string | null) => void
  onZonesChange: (zones: MapZone[]) => void
  onPathsChange: (paths: MapPath[]) => void
  onOverlayRotationChange?: (degrees: number) => void
  onOverlayBoundsChange?: (bounds: OverlayBounds) => void
  onCameraChange: (camera: {
    center: GeoPoint
    zoom: number
    heading: number
    mapTypeId: 'roadmap' | 'satellite' | 'hybrid'
    bounds: OverlayBounds
  }) => void
  onDraftPoint?: (point: GeoPoint | null) => void
}

const MAP_LIBRARIES: Libraries = ['places']

function withDefaults(zone: MapZone): MapZone {
  return {
    ...zone,
    coordinate_space: 'geo',
    shape_rotation: zone.shape_rotation ?? 0,
    fill_color: zone.fill_color ?? defaultFillForType(zone.type),
    stroke_color: zone.stroke_color ?? '#111827',
    opacity: zone.opacity ?? 45,
    stroke_width: zone.stroke_width ?? 2,
  }
}

function asGeoPoints(points: MapZone['polygon_coordinates'] | MapPath['polyline_coordinates'] | undefined): GeoPoint[] {
  if (!points?.length) return []
  return points
    .filter((point): point is GeoPoint => 'lat' in point && 'lng' in point)
    .map((point) => ({ lat: Number(point.lat), lng: Number(point.lng) }))
}

function pathToGeoPoints(path: google.maps.MVCArray<google.maps.LatLng> | null | undefined): GeoPoint[] {
  if (!path) return []
  const points: GeoPoint[] = []
  for (let i = 0; i < path.getLength(); i += 1) {
    const latLng = path.getAt(i)
    points.push({ lat: latLng.lat(), lng: latLng.lng() })
  }
  return stripClosedRingDuplicate(points)
}

/** Drop trailing point that only closes the ring (Google Maps editable polygons). */
function stripClosedRingDuplicate(points: GeoPoint[]): GeoPoint[] {
  if (points.length < 2) return points
  const first = points[0]
  const last = points[points.length - 1]
  if (
    Math.abs(first.lat - last.lat) < 1e-9
    && Math.abs(first.lng - last.lng) < 1e-9
  ) {
    return points.slice(0, -1)
  }
  return points
}

function coerceShapeTypeForPointCount(
  shapeType: MapZone['shape_type'],
  pointCount: number,
): MapZone['shape_type'] {
  if (!shapeType) return shapeType
  const expected: Partial<Record<NonNullable<MapZone['shape_type']>, number>> = {
    rectangle: 4,
    triangle: 3,
    hexagon: 6,
    circle: 1,
    ellipse: 1,
    pillar: 1,
    person: 1,
  }
  const need = expected[shapeType]
  if (need != null && pointCount !== need && pointCount >= 3) {
    return 'polygon'
  }
  return shapeType
}

function pointsEqual(a: GeoPoint[], b: GeoPoint[]): boolean {
  if (a.length !== b.length) return false
  return a.every((point, index) => (
    Math.abs(point.lat - b[index].lat) < 1e-8
    && Math.abs(point.lng - b[index].lng) < 1e-8
  ))
}

function pointInOverlayBounds(point: GeoPoint, bounds: OverlayBounds): boolean {
  return point.lat <= bounds.north
    && point.lat >= bounds.south
    && point.lng >= bounds.west
    && point.lng <= bounds.east
}

function lockMapGestures(map: google.maps.Map | null) {
  map?.setOptions({ gestureHandling: 'none', draggable: false })
}

function unlockMapGestures(map: google.maps.Map | null, selectTool: boolean) {
  map?.setOptions({
    gestureHandling: selectTool ? 'greedy' : 'cooperative',
    draggable: true,
  })
}

/** Keep path/center object identity stable so Polygon/Circle don't reset mid-drag. */
function useStableGeoPoints(
  coordinates: MapZone['polygon_coordinates'] | MapPath['polyline_coordinates'] | undefined,
): GeoPoint[] {
  const points = asGeoPoints(coordinates)
  const ref = useRef(points)
  if (!pointsEqual(ref.current, points)) {
    ref.current = points
  }
  return ref.current
}

type HoverTip = {
  name: string
  position: GeoPoint
}

type EditableZoneShapeProps = {
  zone: MapZone
  selected: boolean
  canEdit: boolean
  drawingPassThrough: boolean
  tool: EditorTool
  map: google.maps.Map | null
  draggingRef: MutableRefObject<string | null>
  locale: string
  onSelectZone: (key: string) => void
  onDeleteZoneShape: (key: string) => void
  onCommitPolygon: (key: string, polygon: google.maps.Polygon) => void
  onCommitCircle: (key: string, circle: google.maps.Circle) => void
  onDraftPathPoint: (point: GeoPoint) => void
  resolvePathPoint: (raw: GeoPoint) => GeoPoint
  onHover: (tip: HoverTip | null) => void
}

function zoneHoverName(zone: MapZone, locale: string): string {
  if (locale === 'ar') {
    return (zone.zone_name_ar || zone.zone_name_en || zone.label || '').trim()
  }
  return (zone.zone_name_en || zone.zone_name_ar || zone.label || '').trim()
}

function pathHoverName(path: MapPath, locale: string): string {
  if (locale === 'ar') {
    return (path.name_ar || path.name_en || '').trim()
  }
  return (path.name_en || path.name_ar || '').trim()
}

const EditableZoneShape = memo(function EditableZoneShape({
  zone,
  selected,
  canEdit,
  drawingPassThrough,
  tool,
  map,
  draggingRef,
  locale,
  onSelectZone,
  onDeleteZoneShape,
  onCommitPolygon,
  onCommitCircle,
  onDraftPathPoint,
  resolvePathPoint,
  onHover,
}: EditableZoneShapeProps) {
  const points = useStableGeoPoints(zone.polygon_coordinates)
  const editable = canEdit && selected
  const fill = zone.fill_color ?? defaultFillForType(zone.type)
  const stroke = zone.stroke_color ?? '#111827'
  const opacity = (zone.opacity ?? 45) / 100
  const isCircleLike = zone.shape_type === 'circle'
    || zone.shape_type === 'ellipse'
  const isMarker = isGeoMarkerShape(zone.shape_type)
  const isPointRadius = isCircleLike || isMarker
  const circleInstanceRef = useRef<google.maps.Circle | null>(null)
  const polygonInstanceRef = useRef<google.maps.Polygon | null>(null)
  const displayName = zoneHoverName(zone, locale)

  const options = useMemo(() => ({
    fillColor: fill,
    fillOpacity: opacity,
    strokeColor: stroke,
    strokeWeight: selected ? 3 : (zone.stroke_width ?? 2),
    clickable: true,
    zIndex: selected ? 5 : 3,
  }), [fill, opacity, stroke, selected, zone.stroke_width])

  const handleClick = useCallback((event: google.maps.MapMouseEvent) => {
    if (drawingPassThrough) {
      if (tool === 'path' && event.latLng) {
        const raw = { lat: event.latLng.lat(), lng: event.latLng.lng() }
        onDraftPathPoint(resolvePathPoint(raw))
      }
      return
    }
    event.stop()
    if (tool === 'delete') {
      onDeleteZoneShape(zone.key)
      return
    }
    onSelectZone(zone.key)
  }, [
    drawingPassThrough,
    tool,
    zone.key,
    onDraftPathPoint,
    resolvePathPoint,
    onDeleteZoneShape,
    onSelectZone,
  ])

  const handleHoverMove = useCallback((event: google.maps.MapMouseEvent) => {
    if (drawingPassThrough || draggingRef.current || !displayName || !event.latLng) {
      onHover(null)
      return
    }
    onHover({
      name: displayName,
      position: { lat: event.latLng.lat(), lng: event.latLng.lng() },
    })
  }, [drawingPassThrough, draggingRef, displayName, onHover])

  const handleHoverOut = useCallback(() => {
    onHover(null)
  }, [onHover])

  const handleDragStart = useCallback(() => {
    draggingRef.current = zone.key
    onHover(null)
    lockMapGestures(map)
  }, [draggingRef, map, onHover, zone.key])

  const handleCircleDragEnd = useCallback(() => {
    const circle = circleInstanceRef.current
    if (circle) onCommitCircle(zone.key, circle)
    draggingRef.current = null
    unlockMapGestures(map, tool === 'select')
  }, [draggingRef, map, onCommitCircle, tool, zone.key])

  const handlePolygonDragEnd = useCallback(() => {
    const polygon = polygonInstanceRef.current
    if (polygon) onCommitPolygon(zone.key, polygon)
    draggingRef.current = null
    unlockMapGestures(map, tool === 'select')
  }, [draggingRef, map, onCommitPolygon, tool, zone.key])

  const handleCircleMouseUp = useCallback(() => {
    if (!editable || draggingRef.current === zone.key) return
    const circle = circleInstanceRef.current
    if (circle) onCommitCircle(zone.key, circle)
  }, [draggingRef, editable, onCommitCircle, zone.key])

  const handlePolygonMouseUp = useCallback(() => {
    if (!editable || draggingRef.current === zone.key) return
    const polygon = polygonInstanceRef.current
    if (polygon) onCommitPolygon(zone.key, polygon)
  }, [draggingRef, editable, onCommitPolygon, zone.key])

  if (!zone.shape_type || points.length === 0) return null

  if (isPointRadius) {
    const radius = zone.shape_radius ?? 8
    const markerType = isGeoMarkerShape(zone.shape_type) ? zone.shape_type : null

    return (
      <>
        <Circle
          center={points[0]}
          radius={radius}
          options={{
            ...options,
            // Markers: keep a light hit/edit ring; icon paints on top.
            fillOpacity: markerType ? (selected ? Math.min(opacity, 0.18) : 0.02) : opacity,
            strokeOpacity: markerType ? (selected ? 0.75 : 0.15) : 1,
            strokeWeight: markerType
              ? (selected ? 2 : 1)
              : (selected ? 3 : (zone.stroke_width ?? 2)),
          }}
          draggable={editable}
          editable={editable}
          onLoad={(circle) => {
            circleInstanceRef.current = circle
          }}
          onUnmount={() => {
            circleInstanceRef.current = null
          }}
          onClick={handleClick}
          onMouseOver={handleHoverMove}
          onMouseMove={handleHoverMove}
          onMouseOut={handleHoverOut}
          onDragStart={handleDragStart}
          onDragEnd={handleCircleDragEnd}
          onMouseUp={handleCircleMouseUp}
        />
        {map && markerType ? (
          <GeoMarkerOverlay
            map={map}
            center={points[0]}
            radiusMeters={radius}
            shapeType={markerType}
            rotation={zone.shape_rotation ?? 0}
            fill={fill}
            stroke={stroke}
            opacity={opacity}
            strokeWidth={zone.stroke_width ?? 2}
            selected={selected}
          />
        ) : null}
      </>
    )
  }

  return (
    <Polygon
      paths={points}
      options={options}
      draggable={editable}
      editable={editable}
      onLoad={(polygon) => {
        polygonInstanceRef.current = polygon
      }}
      onUnmount={() => {
        polygonInstanceRef.current = null
      }}
      onClick={handleClick}
      onMouseOver={handleHoverMove}
      onMouseMove={handleHoverMove}
      onMouseOut={handleHoverOut}
      onDragStart={handleDragStart}
      onDragEnd={handlePolygonDragEnd}
      onMouseUp={handlePolygonMouseUp}
    />
  )
})

type EditablePathShapeProps = {
  path: MapPath
  selected: boolean
  canEdit: boolean
  drawingPassThrough: boolean
  tool: EditorTool
  map: google.maps.Map | null
  draggingRef: MutableRefObject<string | null>
  locale: string
  onSelectPath: (key: string) => void
  onDeletePath: (key: string) => void
  onCommitPath: (key: string, polyline: google.maps.Polyline) => void
  onHover: (tip: HoverTip | null) => void
}

const EditablePathShape = memo(function EditablePathShape({
  path,
  selected,
  canEdit,
  drawingPassThrough,
  tool,
  map,
  draggingRef,
  locale,
  onSelectPath,
  onDeletePath,
  onCommitPath,
  onHover,
}: EditablePathShapeProps) {
  const points = useStableGeoPoints(path.polyline_coordinates)
  const editable = canEdit && selected
  const displayName = pathHoverName(path, locale)
  const options = useMemo(() => ({
    strokeColor: path.stroke_color ?? DEFAULT_PATH_COLOR,
    strokeOpacity: (path.opacity ?? 85) / 100,
    strokeWeight: selected ? (path.stroke_width ?? 3) + 1 : (path.stroke_width ?? 3),
    clickable: !drawingPassThrough,
    zIndex: selected ? 6 : 4,
  }), [
    path.stroke_color,
    path.opacity,
    path.stroke_width,
    selected,
    drawingPassThrough,
  ])

  const polylineRef = useRef<google.maps.Polyline | null>(null)

  const handleClick = useCallback((event: google.maps.MapMouseEvent) => {
    if (drawingPassThrough) return
    event.stop()
    if (tool === 'delete') {
      onDeletePath(path.key)
      return
    }
    onSelectPath(path.key)
  }, [drawingPassThrough, tool, path.key, onDeletePath, onSelectPath])

  const handleHoverMove = useCallback((event: google.maps.MapMouseEvent) => {
    if (drawingPassThrough || draggingRef.current || !displayName || !event.latLng) {
      onHover(null)
      return
    }
    onHover({
      name: displayName,
      position: { lat: event.latLng.lat(), lng: event.latLng.lng() },
    })
  }, [drawingPassThrough, draggingRef, displayName, onHover])

  const handleHoverOut = useCallback(() => {
    onHover(null)
  }, [onHover])

  const handleDragStart = useCallback(() => {
    draggingRef.current = path.key
    onHover(null)
    lockMapGestures(map)
  }, [draggingRef, map, onHover, path.key])

  const handleDragEnd = useCallback(() => {
    const polyline = polylineRef.current
    if (polyline) onCommitPath(path.key, polyline)
    draggingRef.current = null
    unlockMapGestures(map, tool === 'select')
  }, [draggingRef, map, onCommitPath, path.key, tool])

  const handleMouseUp = useCallback(() => {
    if (!editable || draggingRef.current === path.key) return
    const polyline = polylineRef.current
    if (polyline) onCommitPath(path.key, polyline)
  }, [draggingRef, editable, onCommitPath, path.key])

  if (points.length < 2) return null

  return (
    <Polyline
      path={points}
      options={options}
      draggable={editable}
      editable={editable}
      onLoad={(polyline) => {
        polylineRef.current = polyline
      }}
      onUnmount={() => {
        polylineRef.current = null
      }}
      onClick={handleClick}
      onMouseOver={handleHoverMove}
      onMouseMove={handleHoverMove}
      onMouseOut={handleHoverOut}
      onDragStart={handleDragStart}
      onDragEnd={handleDragEnd}
      onMouseUp={handleMouseUp}
    />
  )
})

function readPolygonPoints(polygon: google.maps.Polygon): GeoPoint[] {
  return pathToGeoPoints(polygon.getPath())
}

function readPolylinePoints(polyline: google.maps.Polyline): GeoPoint[] {
  return pathToGeoPoints(polyline.getPath())
}

export default function VenueMapGeoCanvas({
  latitude,
  longitude,
  zoom,
  heading,
  mapTypeId,
  imageUrl,
  overlayBounds,
  overlayOpacity,
  overlayRotation = 0,
  removeBackground = false,
  zones,
  paths,
  selectedKey,
  selectedPathKey,
  tool,
  onSelect,
  onSelectPath,
  onZonesChange,
  onPathsChange,
  onOverlayRotationChange,
  onOverlayBoundsChange,
  onCameraChange,
  onDraftPoint,
}: Props) {
  const { locale, t } = useLocale()
  const mapRef = useRef<google.maps.Map | null>(null)
  const [mapInstance, setMapInstance] = useState<google.maps.Map | null>(null)
  const [draftPoints, setDraftPoints] = useState<GeoPoint[]>([])
  const [hoverPoint, setHoverPoint] = useState<GeoPoint | null>(null)
  const [snappedHover, setSnappedHover] = useState(false)
  const [overlaySelected, setOverlaySelected] = useState(false)
  const [hoverTip, setHoverTip] = useState<HoverTip | null>(null)
  const zonesRef = useRef(zones)
  const pathsRef = useRef(paths)
  zonesRef.current = zones
  pathsRef.current = paths

  useEffect(() => {
    if (tool !== 'select') setOverlaySelected(false)
  }, [tool])

  useEffect(() => {
    if (selectedKey || selectedPathKey) setOverlaySelected(false)
  }, [selectedKey, selectedPathKey])
  const shapeDraggingRef = useRef<string | null>(null)
  const mapBootRef = useRef({ lat: latitude, lng: longitude, zoom, heading })
  const mapReadyRef = useRef(false)
  const lastEmittedCameraRef = useRef<{
    lat: number
    lng: number
    zoom: number
    heading: number
  } | null>(null)
  const apiKey = ((import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined) ?? '')
    .trim()
    .replace(/^["']|["']$/g, '')

  const { isLoaded, loadError } = useJsApiLoader({
    id: 'zoon-google-maps',
    googleMapsApiKey: apiKey,
    libraries: MAP_LIBRARIES,
    language: locale,
  })

  const zonePolygons = useMemo(
    () => zones
      .map((zone) => asGeoPoints(zone.polygon_coordinates))
      .filter((points) => points.length >= 2),
    [zones],
  )
  const pathPolylines = useMemo(
    () => paths
      .filter((path) => path.key !== selectedPathKey)
      .map((path) => asGeoPoints(path.polyline_coordinates))
      .filter((points) => points.length >= 2),
    [paths, selectedPathKey],
  )

  function nearestZoneKey(point: GeoPoint, thresholdMeters = 12): string | null {
    let best: { key: string; distance: number } | null = null
    for (const zone of zones) {
      if (!zone.shape_type) continue
      const points = asGeoPoints(zone.polygon_coordinates)
      if (points.length === 0) continue

      if (
        zone.shape_type === 'circle'
        || zone.shape_type === 'ellipse'
        || zone.shape_type === 'pillar'
        || zone.shape_type === 'person'
      ) {
        const center = points[0]
        const radius = zone.shape_radius ?? 0
        if (radius <= 0) continue
        const edgeDistance = Math.abs(haversineMeters(point.lat, point.lng, center.lat, center.lng) - radius)
        if (!best || edgeDistance < best.distance) {
          best = { key: zone.key, distance: edgeDistance }
        }
        continue
      }

      const closed = points.length >= 3 ? [...points, points[0]] : points
      for (let i = 0; i < closed.length - 1; i += 1) {
        const candidate = nearestPointOnSegment(point, closed[i], closed[i + 1])
        if (!best || candidate.distanceMeters < best.distance) {
          best = { key: zone.key, distance: candidate.distanceMeters }
        }
      }
    }

    return best && best.distance <= thresholdMeters ? best.key : null
  }

  function resolvePathPoint(raw: GeoPoint): GeoPoint {
    if (tool !== 'path') return raw

    const pathSnap = snapPathPointToPathSegments(raw, pathPolylines, 10)
    const edgeSnap = snapPathPointToZoneEdges(raw, zonePolygons, 10)
    let best = edgeSnap.snapped
      ? { point: edgeSnap.point, distanceMeters: 0 }
      : null
    if (pathSnap.snapped) {
      best = { point: pathSnap.point, distanceMeters: 0 }
    }

    // Also snap to circle / marker radii.
    for (const zone of zones) {
      if (
        zone.shape_type !== 'circle'
        && zone.shape_type !== 'ellipse'
        && zone.shape_type !== 'pillar'
        && zone.shape_type !== 'person'
      ) {
        continue
      }
      const centerPoint = asGeoPoints(zone.polygon_coordinates)[0]
      const radius = zone.shape_radius ?? 0
      if (!centerPoint || radius <= 0) continue
      const distance = haversineMeters(raw.lat, raw.lng, centerPoint.lat, centerPoint.lng)
      const edgeDistance = Math.abs(distance - radius)
      if (edgeDistance > 10) continue
      // Project onto circle rim.
      if (distance < 0.01) continue
      const ratio = radius / distance
      const rim = {
        lat: centerPoint.lat + (raw.lat - centerPoint.lat) * ratio,
        lng: centerPoint.lng + (raw.lng - centerPoint.lng) * ratio,
      }
      if (!best || edgeDistance < (best.distanceMeters || 10)) {
        best = { point: rim, distanceMeters: edgeDistance }
      }
    }

    return best?.point ?? raw
  }

  const emitCamera = useCallback((map: google.maps.Map) => {
    const nextCenter = map.getCenter()
    const nextZoom = map.getZoom()
    if (!nextCenter || nextZoom == null) return
    const bounds = map.getBounds()
    const overlay = bounds
      ? {
          north: bounds.getNorthEast().lat(),
          south: bounds.getSouthWest().lat(),
          east: bounds.getNorthEast().lng(),
          west: bounds.getSouthWest().lng(),
        }
      : boundsFromCamera(nextCenter.lat(), nextCenter.lng(), nextZoom)

    const nextType = map.getMapTypeId()
    const typed = nextType === 'satellite' || nextType === 'hybrid' || nextType === 'roadmap'
      ? nextType
      : mapTypeId

    const camera = {
      center: { lat: nextCenter.lat(), lng: nextCenter.lng() },
      zoom: nextZoom,
      heading: map.getHeading() || 0,
      mapTypeId: typed,
      bounds: overlay,
    }
    lastEmittedCameraRef.current = {
      lat: camera.center.lat,
      lng: camera.center.lng,
      zoom: camera.zoom,
      heading: camera.heading,
    }
    onCameraChange(camera)
  }, [mapTypeId, onCameraChange])

  const handleLoad = useCallback((map: google.maps.Map) => {
    mapRef.current = map
    mapReadyRef.current = true
    setMapInstance(map)
    try {
      map.setHeading(heading)
    } catch {
      // ignore
    }
    emitCamera(map)
  }, [emitCamera, heading])

  // Apply camera only for external changes (place search, initial props) — never fight user pans.
  useEffect(() => {
    const map = mapRef.current
    if (!map) return
    const last = lastEmittedCameraRef.current
    if (
      last
      && Math.abs(last.lat - latitude) < 1e-9
      && Math.abs(last.lng - longitude) < 1e-9
      && Math.abs(last.zoom - zoom) < 1e-6
      && Math.abs(last.heading - heading) < 0.05
    ) {
      return
    }
    map.panTo({ lat: latitude, lng: longitude })
    map.setZoom(zoom)
    try {
      map.setHeading(heading)
    } catch {
      // ignore
    }
    lastEmittedCameraRef.current = {
      lat: latitude,
      lng: longitude,
      zoom,
      heading,
    }
  }, [latitude, longitude, zoom, heading])

  function createShapeZone(
    shapeType: ZoneShapeType,
    points: GeoPoint[],
    radiusMeters: number | null = null,
    radiusYMeters: number | null = null,
  ) {
    // Only attach to an explicitly selected zone; otherwise always create a new one.
    const selected = selectedKey
      ? zones.find((zone) => zone.key === selectedKey) ?? null
      : null

    if (selected) {
      onZonesChange(zones.map((zone) => (
        zone.key === selected.key
          ? withDefaults({
            ...zone,
            coordinate_space: 'geo',
            shape_type: shapeType,
            polygon_coordinates: points,
            shape_radius: radiusMeters,
            shape_radius_y: radiusYMeters,
            shape_rotation: 0,
            label: zone.label ?? zone.zone_name_en,
          })
          : zone
      )))
      onSelect(selected.key)
      return
    }

    const key = crypto.randomUUID()
    onZonesChange([
      ...zones,
      withDefaults({
        key,
        zone_name_en: `Zone ${zones.length + 1}`,
        zone_name_ar: `منطقة ${zones.length + 1}`,
        description_en: null,
        description_ar: null,
        type: 'hall',
        capacity: null,
        coordinate_space: 'geo',
        shape_type: shapeType,
        polygon_coordinates: points,
        shape_radius: radiusMeters,
        shape_rotation: 0,
        shape_radius_y: radiusYMeters,
        label: `Zone ${zones.length + 1}`,
        google_maps_url: null,
        lat: points[0]?.lat ?? latitude,
        lng: points[0]?.lng ?? longitude,
        fill_color: null,
        stroke_color: null,
        opacity: null,
        stroke_width: null,
      }),
    ])
    onSelect(key)
  }

  function finishPath() {
    if (draftPoints.length < 2) return
    const startZoneKey = nearestZoneKey(draftPoints[0], 14)
    const endZoneKey = nearestZoneKey(draftPoints[draftPoints.length - 1], 14)
    const key = crypto.randomUUID()
    onPathsChange([
      ...paths,
      {
        key,
        name_en: `Path ${paths.length + 1}`,
        name_ar: `مسار ${paths.length + 1}`,
        coordinate_space: 'geo',
        polyline_coordinates: draftPoints,
        from_zone_key: startZoneKey,
        to_zone_key: endZoneKey,
        stroke_color: DEFAULT_PATH_COLOR,
        stroke_width: 3,
        opacity: 85,
      },
    ])
    onSelect(null)
    onSelectPath(key)
    setDraftPoints([])
    setHoverPoint(null)
    onDraftPoint?.(null)
  }

  function clearShapeSelection() {
    onSelect(null)
    onSelectPath(null)
  }

  function handleMapClick(event: google.maps.MapMouseEvent) {
    const latLng = event.latLng
    if (!latLng) return
    const raw = { lat: latLng.lat(), lng: latLng.lng() }
    const point = resolvePathPoint(raw)

    if (tool === 'select' || tool === 'delete') {
      const insideOverlay = Boolean(
        imageUrl
        && overlayBounds
        && pointInOverlayBounds(raw, overlayBounds),
      )
      const hadShapeOrPath = Boolean(selectedKey || selectedPathKey)

      clearShapeSelection()

      // Select floor image only when clicking it while nothing else is selected.
      // Any outside click (or click that clears a shape/path) drops overlay selection.
      if (tool === 'select' && insideOverlay && !hadShapeOrPath && !overlaySelected) {
        setOverlaySelected(true)
      } else {
        setOverlaySelected(false)
      }
      return
    }

    if (tool === 'path') {
      setDraftPoints((current) => [...current, point])
      onDraftPoint?.(point)
      return
    }

    if (tool === 'polygon') {
      setDraftPoints((current) => [...current, point])
      onDraftPoint?.(point)
      return
    }

    if (tool === 'rectangle' || tool === 'triangle' || tool === 'hexagon') {
      if (draftPoints.length === 0) {
        setDraftPoints([point])
        onDraftPoint?.(point)
        return
      }
      if (tool === 'rectangle') {
        createShapeZone('rectangle', rectangleGeoFromCorners(draftPoints[0], point))
      } else if (tool === 'triangle') {
        createShapeZone('triangle', regularPolygonGeo(draftPoints[0], point, 3))
      } else {
        createShapeZone('hexagon', regularPolygonGeo(draftPoints[0], point, 6))
      }
      setDraftPoints([])
      setHoverPoint(null)
      onDraftPoint?.(null)
      return
    }

    if (tool === 'circle' || tool === 'ellipse' || tool === 'pillar' || tool === 'person') {
      if (draftPoints.length === 0) {
        setDraftPoints([point])
        onDraftPoint?.(point)
        return
      }
      const radius = Math.max(1, haversineMeters(draftPoints[0].lat, draftPoints[0].lng, point.lat, point.lng))
      createShapeZone(
        tool,
        [draftPoints[0]],
        radius,
        tool === 'ellipse' ? radius * 0.65 : null,
      )
      setDraftPoints([])
      setHoverPoint(null)
      onDraftPoint?.(null)
    }
  }

  const updateZoneGeometry = useCallback((zoneKey: string, patch: Partial<MapZone>) => {
    onZonesChange(zonesRef.current.map((row) => (
      row.key === zoneKey ? { ...row, ...patch, coordinate_space: 'geo' } : row
    )))
  }, [onZonesChange])

  const commitPolygonEdit = useCallback((zoneKey: string, polygon: google.maps.Polygon) => {
    const nextPoints = readPolygonPoints(polygon)
    if (nextPoints.length < 3) return
    const current = zonesRef.current.find((row) => row.key === zoneKey)
    const previous = asGeoPoints(current?.polygon_coordinates)
    const nextShapeType = coerceShapeTypeForPointCount(current?.shape_type ?? null, nextPoints.length)
    if (
      pointsEqual(previous, nextPoints)
      && nextShapeType === (current?.shape_type ?? null)
    ) {
      return
    }
    updateZoneGeometry(zoneKey, {
      polygon_coordinates: nextPoints,
      shape_type: nextShapeType,
      shape_radius: null,
      shape_radius_y: null,
    })
  }, [updateZoneGeometry])

  const commitCircleEdit = useCallback((zoneKey: string, circle: google.maps.Circle) => {
    const centerPoint = circle.getCenter()
    if (!centerPoint) return
    const nextCenter = { lat: centerPoint.lat(), lng: centerPoint.lng() }
    const nextRadius = Math.max(1, circle.getRadius())
    const current = zonesRef.current.find((row) => row.key === zoneKey)
    const previous = asGeoPoints(current?.polygon_coordinates)
    const sameCenter = previous.length === 1
      && Math.abs(previous[0].lat - nextCenter.lat) < 1e-8
      && Math.abs(previous[0].lng - nextCenter.lng) < 1e-8
    const sameRadius = Math.abs((current?.shape_radius ?? 0) - nextRadius) < 0.05
    if (sameCenter && sameRadius) return
    updateZoneGeometry(zoneKey, {
      polygon_coordinates: [nextCenter],
      shape_radius: nextRadius,
    })
  }, [updateZoneGeometry])

  const commitPathEdit = useCallback((pathKey: string, polyline: google.maps.Polyline) => {
    const nextPoints = readPolylinePoints(polyline)
    if (nextPoints.length < 2) return
    const current = pathsRef.current.find((row) => row.key === pathKey)
    const previous = asGeoPoints(current?.polyline_coordinates)
    if (pointsEqual(previous, nextPoints)) return
    onPathsChange(pathsRef.current.map((row) => (
      row.key === pathKey
        ? { ...row, polyline_coordinates: nextPoints, coordinate_space: 'geo' }
        : row
    )))
  }, [onPathsChange])

  const selectZoneShape = useCallback((key: string) => {
    setOverlaySelected(false)
    onSelect(key)
    onSelectPath(null)
  }, [onSelect, onSelectPath])

  const deleteZoneShape = useCallback((key: string) => {
    setOverlaySelected(false)
    onZonesChange(zonesRef.current.map((row) => (
      row.key === key
        ? {
          ...row,
          shape_type: null,
          polygon_coordinates: null,
          shape_radius: null,
          shape_radius_y: null,
          shape_rotation: 0,
        }
        : row
    )))
    onSelect(null)
  }, [onZonesChange, onSelect])

  const selectPathShape = useCallback((key: string) => {
    setOverlaySelected(false)
    onSelectPath(key)
    onSelect(null)
  }, [onSelect, onSelectPath])

  const deletePathShape = useCallback((key: string) => {
    setOverlaySelected(false)
    onPathsChange(pathsRef.current.filter((row) => row.key !== key))
    onSelectPath(null)
  }, [onPathsChange, onSelectPath])

  const draftPathPoint = useCallback((point: GeoPoint) => {
    setDraftPoints((current) => [...current, point])
    onDraftPoint?.(point)
  }, [onDraftPoint])

  const resolvePathPointRef = useRef(resolvePathPoint)
  resolvePathPointRef.current = resolvePathPoint
  const resolvePathPointStable = useCallback((raw: GeoPoint) => resolvePathPointRef.current(raw), [])
  const handleShapeHover = useCallback((tip: HoverTip | null) => {
    setHoverTip(tip)
  }, [])

  const canEditShapes = tool === 'select'
  const drawingPathOrShape = tool === 'path'
    || tool === 'polygon'
    || tool === 'rectangle'
    || tool === 'triangle'
    || tool === 'hexagon'
    || tool === 'circle'
    || tool === 'ellipse'
    || tool === 'pillar'
    || tool === 'person'

  const options = useMemo((): google.maps.MapOptions | undefined => {
    // google.maps is only available after the JS API finishes loading.
    if (!isLoaded || typeof google === 'undefined') {
      return undefined
    }

    const boot = mapBootRef.current
    return {
      // Include center/zoom only for first construction — never re-apply from pan feedback.
      ...(mapReadyRef.current
        ? {}
        : {
            center: { lat: boot.lat, lng: boot.lng },
            zoom: boot.zoom,
            heading: boot.heading,
          }),
      disableDefaultUI: false,
      gestureHandling: tool === 'select' ? 'greedy' : 'cooperative',
      clickableIcons: false,
      mapTypeControl: true,
      mapTypeControlOptions: {
        mapTypeIds: [
          google.maps.MapTypeId.ROADMAP,
          google.maps.MapTypeId.SATELLITE,
          google.maps.MapTypeId.HYBRID,
        ],
      },
      rotateControl: true,
      tilt: 0,
      streetViewControl: false,
      fullscreenControl: false,
      mapTypeId,
    }
    // Intentionally omit latitude/longitude/zoom so pan/idle does not re-setOptions the camera.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isLoaded, mapTypeId, tool])

  if (apiKey === '') {
    return <div className="venue-map-geo-canvas venue-map-geo-canvas--empty">{t('mapPickerMissingApiKey')}</div>
  }
  if (loadError) {
    return <div className="venue-map-geo-canvas venue-map-geo-canvas--empty">{t('venueMapBaseMapError')}</div>
  }
  if (!isLoaded) {
    return <div className="venue-map-geo-canvas venue-map-geo-canvas--empty" aria-hidden />
  }

  const draftPath = hoverPoint ? [...draftPoints, hoverPoint] : draftPoints
  const resolvedOverlay = overlayBounds
  const selectedZone = selectedKey
    ? zones.find((row) => row.key === selectedKey) ?? null
    : null
  const selectedZonePoints = selectedZone ? asGeoPoints(selectedZone.polygon_coordinates) : []
  const selectedIsCircleLike = Boolean(
    selectedZone
    && (
      selectedZone.shape_type === 'circle'
      || selectedZone.shape_type === 'pillar'
      || selectedZone.shape_type === 'person'
      || selectedZone.shape_type === 'ellipse'
    ),
  )
  const showShapeRotate = Boolean(
    mapInstance
    && tool === 'select'
    && selectedZone?.shape_type
    && selectedZonePoints.length > 0
    && (selectedIsCircleLike || selectedZonePoints.length >= 3),
  )

  return (
    <div className="venue-map-geo-canvas">
      <GoogleMap
        mapContainerClassName="venue-map-geo-canvas__map"
        options={options}
        onLoad={handleLoad}
        onClick={handleMapClick}
        onIdle={() => {
          if (shapeDraggingRef.current) return
          if (mapRef.current) emitCamera(mapRef.current)
        }}
        onMouseMove={(event) => {
          if (tool === 'select' || tool === 'delete') return
          const latLng = event.latLng
          if (!latLng) return
          const raw = { lat: latLng.lat(), lng: latLng.lng() }
          if (tool === 'path') {
            const point = resolvePathPoint(raw)
            const snapped = haversineMeters(point.lat, point.lng, raw.lat, raw.lng) > 0.35
            setHoverPoint(point)
            setSnappedHover(snapped)
            onDraftPoint?.(point)
            return
          }
          setHoverPoint(raw)
          setSnappedHover(false)
          onDraftPoint?.(raw)
        }}
        onDblClick={() => {
          if (tool === 'polygon' && draftPoints.length >= 3) {
            createShapeZone('polygon', draftPoints)
            setDraftPoints([])
            setHoverPoint(null)
            onDraftPoint?.(null)
          }
          if (tool === 'path' && draftPoints.length >= 2) {
            finishPath()
          }
        }}
      >
        {mapInstance && imageUrl && resolvedOverlay ? (
          <RotatableFloorOverlay
            map={mapInstance}
            imageUrl={imageUrl}
            bounds={resolvedOverlay}
            opacity={overlayOpacity}
            rotation={overlayRotation}
            removeBackground={removeBackground}
            draggable={tool === 'select'}
            selected={overlaySelected}
            onSelect={() => {
              setOverlaySelected(true)
              onSelect(null)
              onSelectPath(null)
            }}
            onRotationChange={onOverlayRotationChange}
            onBoundsChange={onOverlayBoundsChange}
          />
        ) : null}

        {zones.map((zone) => (
          <EditableZoneShape
            key={zone.key}
            zone={zone}
            selected={zone.key === selectedKey}
            canEdit={canEditShapes}
            drawingPassThrough={drawingPathOrShape}
            tool={tool}
            map={mapInstance}
            draggingRef={shapeDraggingRef}
            locale={locale}
            onSelectZone={selectZoneShape}
            onDeleteZoneShape={deleteZoneShape}
            onCommitPolygon={commitPolygonEdit}
            onCommitCircle={commitCircleEdit}
            onDraftPathPoint={draftPathPoint}
            resolvePathPoint={resolvePathPointStable}
            onHover={handleShapeHover}
          />
        ))}

        {showShapeRotate && mapInstance && selectedZone ? (
          <>
            <ShapeMoveHandle
              map={mapInstance}
              points={selectedZonePoints}
              radiusMeters={selectedIsCircleLike ? (selectedZone.shape_radius ?? 8) : null}
              label={t('venueMapMoveShape')}
              onInteractionChange={(active) => {
                shapeDraggingRef.current = active ? selectedZone.key : null
                if (active) setHoverTip(null)
              }}
              onMove={(nextPoints) => {
                updateZoneGeometry(selectedZone.key, {
                  polygon_coordinates: nextPoints,
                })
              }}
            />
            <ShapeRotateOverlay
              map={mapInstance}
              points={selectedZonePoints}
              radiusMeters={selectedIsCircleLike ? (selectedZone.shape_radius ?? 8) : null}
              rotation={selectedZone.shape_rotation ?? 0}
              onInteractionChange={(active) => {
                shapeDraggingRef.current = active ? selectedZone.key : null
                if (active) setHoverTip(null)
              }}
              onRotate={(nextPoints, nextRotation) => {
                updateZoneGeometry(selectedZone.key, {
                  polygon_coordinates: nextPoints,
                  shape_rotation: nextRotation,
                })
              }}
            />
          </>
        ) : null}

        {paths.map((path) => (
          <EditablePathShape
            key={path.key}
            path={path}
            selected={path.key === selectedPathKey}
            canEdit={canEditShapes}
            drawingPassThrough={drawingPathOrShape}
            tool={tool}
            map={mapInstance}
            draggingRef={shapeDraggingRef}
            locale={locale}
            onSelectPath={selectPathShape}
            onDeletePath={deletePathShape}
            onCommitPath={commitPathEdit}
            onHover={handleShapeHover}
          />
        ))}

        {mapInstance && hoverTip ? (
          <MapHoverTooltip
            map={mapInstance}
            position={hoverTip.position}
            label={hoverTip.name}
          />
        ) : null}

        {draftPath.length >= 2 ? (
          <Polyline
            path={draftPath}
            options={{
              strokeColor: tool === 'path' ? DEFAULT_PATH_COLOR : '#2563eb',
              strokeOpacity: 0.85,
              strokeWeight: 2,
              clickable: false,
              zIndex: 10,
            }}
          />
        ) : null}
        {tool === 'path' && hoverPoint && snappedHover ? (
          <Circle
            center={hoverPoint}
            radius={2.5}
            options={{
              fillColor: '#16a34a',
              fillOpacity: 0.9,
              strokeColor: '#fff',
              strokeWeight: 2,
              clickable: false,
              zIndex: 12,
            }}
          />
        ) : null}
        {draftPoints[0] && hoverPoint && (tool === 'circle' || tool === 'ellipse' || tool === 'pillar' || tool === 'person') ? (
          <>
            <Circle
              center={draftPoints[0]}
              radius={Math.max(1, haversineMeters(draftPoints[0].lat, draftPoints[0].lng, hoverPoint.lat, hoverPoint.lng))}
              options={{
                fillColor: tool === 'person' || tool === 'pillar' ? '#0f766e' : '#2563eb',
                fillOpacity: tool === 'person' || tool === 'pillar' ? 0.08 : 0.2,
                strokeColor: tool === 'person' || tool === 'pillar' ? '#0f766e' : '#2563eb',
                strokeWeight: 1,
                clickable: false,
                zIndex: 10,
              }}
            />
            {mapInstance && (tool === 'person' || tool === 'pillar') ? (
              <GeoMarkerOverlay
                map={mapInstance}
                center={draftPoints[0]}
                radiusMeters={Math.max(1, haversineMeters(draftPoints[0].lat, draftPoints[0].lng, hoverPoint.lat, hoverPoint.lng))}
                shapeType={tool}
                fill="#0f766e"
                stroke="#111827"
                opacity={0.7}
                selected
              />
            ) : null}
          </>
        ) : null}
      </GoogleMap>
    </div>
  )
}
