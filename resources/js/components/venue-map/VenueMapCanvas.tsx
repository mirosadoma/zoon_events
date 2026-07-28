import {
  Circle,
  Ellipse,
  Image as KonvaImage,
  Layer,
  Line,
  Rect,
  Stage,
  Transformer,
} from 'react-konva'
import type Konva from 'konva'
import { useEffect, useMemo, useRef, useState } from 'react'
import {
  absoluteFromCenteredLocal,
  bakeRotationIntoPoints,
  centeredLocalFlat,
  centroid,
  clamp01,
  normalizeDegrees,
  pointsToFlat,
  rectangleFromCorners,
  regularPolygonPoints,
  relativeRadius,
  rotatePixelAround,
  snapRelative,
  supportsVertexEditing,
  toPixel,
  toRelative,
} from '@/components/venue-map/coordinates'
import {
  DEFAULT_PATH_COLOR,
  defaultFillForType,
  type EditorTool,
  type MapPath,
  type MapZone,
  type RelativePoint,
  type ZoneShapeType,
} from '@/components/venue-map/types'
import VenueMapBaseLayer, { type VenueBaseMapType } from '@/components/venue-map/VenueMapBaseLayer'
import VenueMapMarkerShape, { isMarkerShape, isPointRadiusShape } from '@/components/venue-map/VenueMapMarkerShape'
import { removeNearWhiteBackground } from '@/components/venue-map/removeImageBackground'

type Props = {
  imageUrl: string | null
  naturalWidth: number
  naturalHeight: number
  zones: MapZone[]
  paths: MapPath[]
  selectedKey: string | null
  selectedPathKey: string | null
  tool: EditorTool
  locale: 'en' | 'ar'
  overlayOpacity?: number
  removeBackground?: boolean
  showBaseMap?: boolean
  baseMapType?: VenueBaseMapType
  baseMapInteractive?: boolean
  baseMapZoom?: number
  baseMapHeading?: number
  onBaseMapTypeChange?: (mapTypeId: VenueBaseMapType) => void
  onBaseMapHeadingChange?: (heading: number) => void
  venueLatitude?: number | null
  venueLongitude?: number | null
  onSelect: (key: string | null) => void
  onSelectPath: (key: string | null) => void
  onZonesChange: (zones: MapZone[]) => void
  onPathsChange: (paths: MapPath[]) => void
  onDraftPoint?: (point: RelativePoint | null) => void
}

function useFloorPlanImage(
  url: string | null,
  removeBackground: boolean,
): HTMLImageElement | null {
  const [image, setImage] = useState<HTMLImageElement | null>(null)

  useEffect(() => {
    if (!url) {
      setImage(null)
      return
    }

    let cancelled = false
    const element = new window.Image()
    // Only request CORS when we need pixel access for background removal.
    if (removeBackground) {
      element.crossOrigin = 'anonymous'
    }
    element.onload = () => {
      void (async () => {
        if (cancelled) return
        if (!removeBackground) {
          setImage(element)
          return
        }
        const processed = await removeNearWhiteBackground(element)
        if (!cancelled) setImage(processed)
      })()
    }
    element.onerror = () => {
      if (cancelled) return
      // Retry without CORS if the first attempt failed (common on local storage).
      if (removeBackground) {
        const fallback = new window.Image()
        fallback.onload = () => {
          if (!cancelled) setImage(fallback)
        }
        fallback.onerror = () => {
          if (!cancelled) setImage(null)
        }
        fallback.src = url
        return
      }
      setImage(null)
    }
    element.src = url

    return () => {
      cancelled = true
      element.onload = null
      element.onerror = null
    }
  }, [url, removeBackground])

  return image
}

function zoneLabel(zone: MapZone, locale: 'en' | 'ar'): string {
  if (zone.label?.trim()) return zone.label.trim()
  return locale === 'ar'
    ? (zone.zone_name_ar || zone.zone_name_en)
    : (zone.zone_name_en || zone.zone_name_ar)
}

function withDefaults(zone: MapZone): MapZone {
  return {
    ...zone,
    shape_rotation: zone.shape_rotation ?? 0,
    fill_color: zone.fill_color ?? defaultFillForType(zone.type),
    stroke_color: zone.stroke_color ?? '#111827',
    opacity: zone.opacity ?? 45,
    stroke_width: zone.stroke_width ?? 2,
  }
}

export default function VenueMapCanvas({
  imageUrl,
  naturalWidth,
  naturalHeight,
  zones,
  paths,
  selectedKey,
  selectedPathKey,
  tool,
  locale,
  overlayOpacity = 1,
  removeBackground = false,
  showBaseMap = false,
  baseMapType = 'hybrid',
  baseMapInteractive = false,
  baseMapZoom = 18,
  baseMapHeading = 0,
  onBaseMapTypeChange,
  onBaseMapHeadingChange,
  venueLatitude = null,
  venueLongitude = null,
  onSelect,
  onSelectPath,
  onZonesChange,
  onPathsChange,
  onDraftPoint,
}: Props) {
  const containerRef = useRef<HTMLDivElement | null>(null)
  const stageRef = useRef<Konva.Stage | null>(null)
  const transformerRef = useRef<Konva.Transformer | null>(null)
  const shapeRefs = useRef<Record<string, Konva.Node>>({})
  const image = useFloorPlanImage(imageUrl, removeBackground)

  const [size, setSize] = useState({ width: 800, height: 500 })
  const [scale, setScale] = useState(1)
  const [position, setPosition] = useState({ x: 0, y: 0 })
  const [draftPoints, setDraftPoints] = useState<RelativePoint[]>([])
  const [hoverPoint, setHoverPoint] = useState<RelativePoint | null>(null)
  const [rectStart, setRectStart] = useState<RelativePoint | null>(null)
  const [hoverTooltip, setHoverTooltip] = useState<{
    key: string
    name: string
    x: number
    y: number
  } | null>(null)
  const [hoveredKey, setHoveredKey] = useState<string | null>(null)
  // Live vertex drag preview — avoids flooding undo history on every pointer move.
  const [dragPreview, setDragPreview] = useState<MapZone[] | null>(null)
  const [activeVertexIndex, setActiveVertexIndex] = useState<number | null>(null)
  const [activePathVertexIndex, setActivePathVertexIndex] = useState<number | null>(null)
  const draggingVertexRef = useRef<{ key: string; index: number } | null>(null)
  const draggingPathVertexRef = useRef<{ key: string; index: number } | null>(null)
  const zonesRef = useRef(zones)
  const pathsRef = useRef(paths)
  const dragPreviewRef = useRef<MapZone[] | null>(null)
  const pathDragPreviewRef = useRef<MapPath[] | null>(null)
  const [pathDragPreview, setPathDragPreview] = useState<MapPath[] | null>(null)
  const [passThroughToMap, setPassThroughToMap] = useState(false)
  zonesRef.current = zones
  pathsRef.current = paths
  dragPreviewRef.current = dragPreview
  pathDragPreviewRef.current = pathDragPreview

  useEffect(() => {
    const element = containerRef.current
    if (!element) return

    const observer = new ResizeObserver((entries) => {
      const entry = entries[0]
      if (!entry) return
      setSize({
        width: Math.max(320, entry.contentRect.width),
        height: Math.max(360, entry.contentRect.height),
      })
    })

    observer.observe(element)
    return () => observer.disconnect()
  }, [])

  const imageWidth = naturalWidth || image?.naturalWidth || 1200
  const imageHeight = naturalHeight || image?.naturalHeight || 800
  // Fit the floor plan into the resizable frame (scale up or down), then apply user zoom.
  const fitScale = Math.min(size.width / imageWidth, size.height / imageHeight)
  const stageScale = fitScale * scale
  const centerX = (size.width - imageWidth * stageScale) / 2
  const centerY = (size.height - imageHeight * stageScale) / 2
  const stageX = centerX + position.x
  const stageY = centerY + position.y
  const viewTransformRef = useRef({ stageX, stageY, stageScale, imageWidth, imageHeight })
  viewTransformRef.current = { stageX, stageY, stageScale, imageWidth, imageHeight }

  const displayZones = dragPreview ?? zones
  const displayPaths = pathDragPreview ?? paths

  const anchors = useMemo(() => {
    const points: RelativePoint[] = []
    for (const zone of displayZones) {
      if (zone.polygon_coordinates) {
        points.push(...zone.polygon_coordinates)
      }
    }
    for (const path of displayPaths) {
      points.push(...path.polyline_coordinates)
    }
    points.push(...draftPoints)
    return points
  }, [displayZones, displayPaths, draftPoints])

  useEffect(() => {
    if (draggingVertexRef.current || draggingPathVertexRef.current) return
    setDragPreview(null)
    setPathDragPreview(null)
    setActiveVertexIndex(null)
    setActivePathVertexIndex(null)
  }, [zones, paths, selectedKey, selectedPathKey, tool])

  useEffect(() => {
    const transformer = transformerRef.current
    if (!transformer) return

    const selected = selectedKey && !selectedPathKey ? shapeRefs.current[selectedKey] : null
    if (selected && tool === 'select') {
      transformer.nodes([selected])
      transformer.forceUpdate()
      transformer.getLayer()?.batchDraw()
    } else {
      transformer.nodes([])
      transformer.getLayer()?.batchDraw()
    }
  }, [selectedKey, selectedPathKey, tool, displayZones])

  function pointerRelative(): RelativePoint | null {
    const stage = stageRef.current
    if (!stage) return null
    const pointer = stage.getPointerPosition()
    if (!pointer) return null

    const x = (pointer.x - stageX) / stageScale
    const y = (pointer.y - stageY) / stageScale
    return snapRelative(toRelative(x, y, imageWidth, imageHeight), anchors)
  }

  function updateZone(key: string, patch: Partial<MapZone>) {
    onZonesChange(zonesRef.current.map((zone) => (zone.key === key ? { ...zone, ...patch } : zone)))
  }

  const selectedVertexZone = useMemo((): MapZone | null => {
    if (tool !== 'select' || !selectedKey) return null
    const zone = displayZones.find((row) => row.key === selectedKey) ?? null
    if (!zone || !supportsVertexEditing(zone.shape_type) || !zone.polygon_coordinates?.length) {
      return null
    }
    return zone
  }, [tool, selectedKey, displayZones])

  function moveVertexPoints(
    source: MapZone[],
    zoneKey: string,
    index: number,
    nextPoint: RelativePoint,
  ): MapZone[] {
    return source.map((zone) => {
      if (zone.key !== zoneKey || !zone.polygon_coordinates) return zone
      return {
        ...zone,
        shape_rotation: 0,
        polygon_coordinates: zone.polygon_coordinates.map((point, pointIndex) => (
          pointIndex === index ? nextPoint : point
        )),
      }
    })
  }

  function finishPathDraft() {
    if (draftPoints.length < 2) return
    const key = crypto.randomUUID()
    const created: MapPath = {
      key,
      name_en: `Path ${paths.length + 1}`,
      name_ar: `مسار ${paths.length + 1}`,
      polyline_coordinates: draftPoints,
      from_zone_key: null,
      to_zone_key: null,
      stroke_color: DEFAULT_PATH_COLOR,
      stroke_width: 3,
      opacity: 85,
    }
    onPathsChange([...paths, created])
    onSelect(null)
    onSelectPath(key)
    setDraftPoints([])
    setHoverPoint(null)
    onDraftPoint?.(null)
  }

  function movePathVertex(
    source: MapPath[],
    pathKey: string,
    index: number,
    nextPoint: RelativePoint,
  ): MapPath[] {
    return source.map((path) => {
      if (path.key !== pathKey) return path
      return {
        ...path,
        polyline_coordinates: path.polyline_coordinates.map((point, pointIndex) => (
          pointIndex === index ? nextPoint : point
        )),
      }
    })
  }

  function createShapeZone(
    shapeType: ZoneShapeType,
    points: RelativePoint[],
    radius: number | null = null,
    radiusY: number | null = null,
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
            shape_type: shapeType,
            polygon_coordinates: points,
            shape_radius: radius,
            shape_radius_y: radiusY,
            shape_rotation: 0,
            label: zone.label ?? zone.zone_name_en,
          })
          : zone
      )))
      onSelect(selected.key)
      return
    }

    const key = crypto.randomUUID()
    const created = withDefaults({
      key,
      zone_name_en: `Zone ${zones.length + 1}`,
      zone_name_ar: `منطقة ${zones.length + 1}`,
      description_en: null,
      description_ar: null,
      type: 'hall',
      capacity: null,
      shape_type: shapeType,
      polygon_coordinates: points,
      shape_radius: radius,
      shape_rotation: 0,
      shape_radius_y: radiusY,
      label: `Zone ${zones.length + 1}`,
      google_maps_url: null,
      lat: null,
      lng: null,
      fill_color: null,
      stroke_color: null,
      opacity: null,
      stroke_width: null,
    })
    onZonesChange([...zones, created])
    onSelect(key)
  }

  function handleStageClick(event: Konva.KonvaEventObject<MouseEvent>) {
    // Empty-canvas click clears selection (hides rotate handle + corner anchors).
    // Shape clicks cancelBubble; transformer/anchor clicks keep the current selection.
    if (tool === 'select' || tool === 'delete') {
      if (tool === 'select' && event.target === stageRef.current) {
        onSelect(null)
        onSelectPath(null)
        setHoverTooltip(null)
        setHoveredKey(null)
      }
      return
    }

    const point = pointerRelative()
    if (!point) return

    if (tool === 'path') {
      const next = [...draftPoints, point]
      setDraftPoints(next)
      onDraftPoint?.(point)
      return
    }

    if (tool === 'polygon') {
      const next = [...draftPoints, point]
      setDraftPoints(next)
      onDraftPoint?.(point)

      if (next.length >= 3) {
        const first = next[0]
        const closeEnough = Math.hypot(point.x - first.x, point.y - first.y) <= 0.015 && next.length > 3
        if (closeEnough) {
          createShapeZone('polygon', next.slice(0, -1))
          setDraftPoints([])
          setHoverPoint(null)
          onDraftPoint?.(null)
        }
      }
      return
    }

    if (tool === 'rectangle') {
      if (!rectStart) {
        setRectStart(point)
        setDraftPoints([point])
        onDraftPoint?.(point)
        return
      }

      createShapeZone('rectangle', rectangleFromCorners(rectStart, point))
      setRectStart(null)
      setDraftPoints([])
      setHoverPoint(null)
      onDraftPoint?.(null)
      return
    }

    if (tool === 'circle' || tool === 'ellipse' || tool === 'pillar' || tool === 'person') {
      if (!rectStart) {
        setRectStart(point)
        setDraftPoints([point])
        onDraftPoint?.(point)
        return
      }

      const aspect = imageWidth / imageHeight
      const radius = Math.max(0.01, relativeRadius(rectStart, point, aspect))
      createShapeZone(
        tool,
        [rectStart],
        radius,
        tool === 'ellipse' ? radius : null,
      )
      setRectStart(null)
      setDraftPoints([])
      setHoverPoint(null)
      onDraftPoint?.(null)
      return
    }

    if (tool === 'triangle' || tool === 'hexagon') {
      if (!rectStart) {
        setRectStart(point)
        setDraftPoints([point])
        onDraftPoint?.(point)
        return
      }

      const sides = tool === 'triangle' ? 3 : 6
      createShapeZone(tool, regularPolygonPoints(rectStart, point, sides))
      setRectStart(null)
      setDraftPoints([])
      setHoverPoint(null)
      onDraftPoint?.(null)
    }
  }

  function handleWheel(event: Konva.KonvaEventObject<WheelEvent>) {
    event.evt.preventDefault()
    const stage = stageRef.current
    if (!stage) return

    const oldScale = scale
    const pointer = stage.getPointerPosition()
    if (!pointer) return

    const direction = event.evt.deltaY > 0 ? -1 : 1
    const nextScale = Math.min(4, Math.max(0.4, oldScale * (direction > 0 ? 1.08 : 1 / 1.08)))

    const oldStageScale = fitScale * oldScale
    const oldCenterX = (size.width - imageWidth * oldStageScale) / 2
    const oldCenterY = (size.height - imageHeight * oldStageScale) / 2
    const absX = oldCenterX + position.x
    const absY = oldCenterY + position.y

    const mousePointTo = {
      x: (pointer.x - absX) / oldStageScale,
      y: (pointer.y - absY) / oldStageScale,
    }

    const nextStageScale = fitScale * nextScale
    const nextCenterX = (size.width - imageWidth * nextStageScale) / 2
    const nextCenterY = (size.height - imageHeight * nextStageScale) / 2

    setScale(nextScale)
    setPosition({
      x: pointer.x - mousePointTo.x * nextStageScale - nextCenterX,
      y: pointer.y - mousePointTo.y * nextStageScale - nextCenterY,
    })
  }

  function renderZone(zone: MapZone) {
    if (!zone.shape_type || !zone.polygon_coordinates?.length) return null

    const styled = withDefaults(zone)
    const fill = styled.fill_color ?? defaultFillForType(zone.type)
    const stroke = styled.stroke_color ?? '#111827'
    const opacity = (styled.opacity ?? 45) / 100
    const strokeWidth = styled.stroke_width ?? 2
    const selected = zone.key === selectedKey
    const hovered = zone.key === hoveredKey
    const rotation = zone.shape_rotation ?? 0
    const centerPx = toPixel(zone.polygon_coordinates[0], imageWidth, imageHeight)

    const updateHoverTooltip = (event: Konva.KonvaEventObject<MouseEvent>) => {
      const stage = event.target.getStage()
      const pointer = stage?.getPointerPosition()
      if (!pointer) return

      setHoveredKey(zone.key)
      setHoverTooltip({
        key: zone.key,
        name: zoneLabel(zone, locale),
        x: pointer.x,
        y: pointer.y,
      })
    }

    const common = {
      id: zone.key,
      ref: (node: Konva.Node | null) => {
        if (node) shapeRefs.current[zone.key] = node
        else delete shapeRefs.current[zone.key]
      },
      fill,
      opacity: hovered || selected ? Math.min(opacity + 0.15, 0.85) : opacity,
      stroke,
      strokeWidth: selected || hovered ? strokeWidth + 1 : strokeWidth,
      draggable: tool === 'select',
      rotation,
      onMouseEnter: (event: Konva.KonvaEventObject<MouseEvent>) => {
        updateHoverTooltip(event)
        const container = event.target.getStage()?.container()
        if (container) container.style.cursor = tool === 'select' ? 'pointer' : container.style.cursor
      },
      onMouseMove: (event: Konva.KonvaEventObject<MouseEvent>) => {
        updateHoverTooltip(event)
      },
      onMouseLeave: () => {
        setHoveredKey((current) => (current === zone.key ? null : current))
        setHoverTooltip((current) => (current?.key === zone.key ? null : current))
      },
      onClick: (event: Konva.KonvaEventObject<MouseEvent>) => {
        event.cancelBubble = true
        if (tool === 'delete') {
          onZonesChange(zones.map((row) => (
            row.key === zone.key
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
          setHoverTooltip(null)
          setHoveredKey(null)
          return
        }
        onSelect(zone.key === selectedKey ? null : zone.key)
        onSelectPath(null)
      },
      onDragEnd: (event: Konva.KonvaEventObject<DragEvent>) => {
        const node = event.target

        if (isPointRadiusShape(zone.shape_type)) {
          updateZone(zone.key, {
            polygon_coordinates: [toRelative(node.x(), node.y(), imageWidth, imageHeight)],
          })
          return
        }

        if (!zone.polygon_coordinates?.length) return

        const { local } = centeredLocalFlat(zone.polygon_coordinates, imageWidth, imageHeight)
        updateZone(zone.key, {
          polygon_coordinates: absoluteFromCenteredLocal(
            node.x(),
            node.y(),
            local,
            imageWidth,
            imageHeight,
          ),
        })
      },
      onTransformEnd: (event: Konva.KonvaEventObject<Event>) => {
        const node = event.target
        const nextRotation = normalizeDegrees(node.rotation())

        if (isPointRadiusShape(zone.shape_type)) {
          const scaleX = Math.abs(node.scaleX())
          const scaleY = Math.abs(node.scaleY())
          const baseRadiusX = (zone.shape_radius ?? 0.05) * imageWidth
          const baseRadiusY = (zone.shape_radius_y ?? zone.shape_radius ?? 0.05) * imageHeight
          node.scaleX(1)
          node.scaleY(1)

          if (zone.shape_type === 'ellipse') {
            const ellipse = node as Konva.Ellipse
            const nextRadiusX = Math.max(4, baseRadiusX * scaleX)
            const nextRadiusY = Math.max(4, baseRadiusY * scaleY)
            ellipse.radiusX(nextRadiusX)
            ellipse.radiusY(nextRadiusY)
            updateZone(zone.key, {
              polygon_coordinates: [toRelative(node.x(), node.y(), imageWidth, imageHeight)],
              shape_radius: Number(Math.max(0.001, clamp01(nextRadiusX / imageWidth)).toFixed(6)),
              shape_radius_y: Number(Math.max(0.001, clamp01(nextRadiusY / imageHeight)).toFixed(6)),
              shape_rotation: nextRotation,
            })
            return
          }

          const nextRadiusPx = Math.max(4, baseRadiusX * ((scaleX + scaleY) / 2))
          if (zone.shape_type === 'circle') {
            ;(node as Konva.Circle).radius(nextRadiusPx)
          }

          updateZone(zone.key, {
            polygon_coordinates: [toRelative(node.x(), node.y(), imageWidth, imageHeight)],
            shape_radius: Number(Math.max(0.001, clamp01(nextRadiusPx / imageWidth)).toFixed(6)),
            shape_radius_y: null,
            shape_rotation: nextRotation,
          })
          return
        }

        if (!zone.polygon_coordinates?.length) return

        const line = node as Konva.Line
        const scaleX = node.scaleX()
        const scaleY = node.scaleY()
        const scaledLocal = line.points().map((value, index) => (
          index % 2 === 0 ? value * scaleX : value * scaleY
        ))

        node.scaleX(1)
        node.scaleY(1)
        line.points(scaledLocal)

        updateZone(zone.key, {
          polygon_coordinates: absoluteFromCenteredLocal(
            node.x(),
            node.y(),
            scaledLocal,
            imageWidth,
            imageHeight,
          ),
          shape_rotation: nextRotation,
        })
      },
    }

    if (zone.shape_type === 'circle') {
      const radiusPx = (zone.shape_radius ?? 0.05) * imageWidth
      return (
        <Circle
          key={zone.key}
          {...common}
          x={centerPx.x}
          y={centerPx.y}
          radius={radiusPx}
        />
      )
    }

    if (zone.shape_type === 'ellipse') {
      return (
        <Ellipse
          key={zone.key}
          {...common}
          x={centerPx.x}
          y={centerPx.y}
          radiusX={(zone.shape_radius ?? 0.05) * imageWidth}
          radiusY={(zone.shape_radius_y ?? zone.shape_radius ?? 0.05) * imageHeight}
        />
      )
    }

    if (zone.shape_type === 'pillar' || zone.shape_type === 'person') {
      const markerType = zone.shape_type
      const radiusPx = (zone.shape_radius ?? 0.05) * imageWidth
      return (
        <VenueMapMarkerShape
          key={zone.key}
          shapeType={markerType}
          x={centerPx.x}
          y={centerPx.y}
          radiusPx={radiusPx}
          rotation={rotation}
          fill={fill}
          opacity={hovered || selected ? Math.min(opacity + 0.15, 0.85) : opacity}
          stroke={stroke}
          strokeWidth={selected || hovered ? strokeWidth + 1 : strokeWidth}
          draggable={tool === 'select'}
          shapeRef={(node) => {
            if (node) shapeRefs.current[zone.key] = node
            else delete shapeRefs.current[zone.key]
          }}
          onMouseEnter={common.onMouseEnter}
          onMouseMove={common.onMouseMove}
          onMouseLeave={common.onMouseLeave}
          onClick={common.onClick}
          onDragEnd={common.onDragEnd}
          onTransformEnd={common.onTransformEnd}
        />
      )
    }

    const centered = centeredLocalFlat(zone.polygon_coordinates, imageWidth, imageHeight)

    return (
      <Line
        key={zone.key}
        {...common}
        x={centered.centerX}
        y={centered.centerY}
        points={centered.local}
        closed
        tension={0}
      />
    )
  }

  const draftPreview = useMemo(() => {
    if (draftPoints.length === 0) return null

    if (
      (tool === 'triangle' || tool === 'hexagon')
      && draftPoints.length === 1
      && hoverPoint
    ) {
      return pointsToFlat(
        regularPolygonPoints(draftPoints[0], hoverPoint, tool === 'triangle' ? 3 : 6),
        imageWidth,
        imageHeight,
      )
    }

    const points = hoverPoint ? [...draftPoints, hoverPoint] : draftPoints
    return pointsToFlat(points, imageWidth, imageHeight)
  }, [draftPoints, hoverPoint, imageWidth, imageHeight, tool])

  const hasBaseMap = Boolean(
    showBaseMap
    && venueLatitude != null
    && venueLongitude != null
    && Number.isFinite(venueLatitude)
    && Number.isFinite(venueLongitude),
  )
  const floorOpacity = Math.min(1, Math.max(0, overlayOpacity))
  const mapReceivesPointer = hasBaseMap && (baseMapInteractive || passThroughToMap)

  function clientOverFloorPlan(clientX: number, clientY: number): boolean {
    const container = containerRef.current
    if (!container) return true
    const rect = container.getBoundingClientRect()
    const pointerX = clientX - rect.left
    const pointerY = clientY - rect.top
    const view = viewTransformRef.current
    if (view.stageScale === 0) return true
    const localX = (pointerX - view.stageX) / view.stageScale
    const localY = (pointerY - view.stageY) / view.stageScale
    return localX >= 0
      && localY >= 0
      && localX <= view.imageWidth
      && localY <= view.imageHeight
  }

  function syncMapPassThrough(clientX: number, clientY: number) {
    if (!hasBaseMap) {
      setPassThroughToMap(false)
      return
    }
    if (baseMapInteractive) {
      setPassThroughToMap(true)
      return
    }
    const passThrough = !clientOverFloorPlan(clientX, clientY)
    const content = stageRef.current?.content
    if (content instanceof HTMLElement) {
      content.style.pointerEvents = passThrough ? 'none' : ''
    }
    setPassThroughToMap(passThrough)
  }

  useEffect(() => {
    const content = stageRef.current?.content
    if (!(content instanceof HTMLElement)) return

    if (baseMapInteractive) {
      content.style.pointerEvents = 'none'
      setPassThroughToMap(true)
      return
    }

    content.style.pointerEvents = ''
    setPassThroughToMap(false)
  }, [baseMapInteractive])

  useEffect(() => {
    if (hasBaseMap) return
    const content = stageRef.current?.content
    if (content instanceof HTMLElement) {
      content.style.pointerEvents = ''
    }
    setPassThroughToMap(false)
  }, [hasBaseMap])

  return (
    <div
      ref={containerRef}
      className={`venue-map-canvas${hasBaseMap ? ' venue-map-canvas--has-base-map' : ''}${mapReceivesPointer ? ' venue-map-canvas--base-map-interactive' : ''}`}
      onMouseMove={(event) => syncMapPassThrough(event.clientX, event.clientY)}
      onMouseLeave={() => {
        if (!baseMapInteractive) setPassThroughToMap(false)
      }}
    >
      {hasBaseMap ? (
        <VenueMapBaseLayer
          latitude={venueLatitude as number}
          longitude={venueLongitude as number}
          zoom={baseMapZoom}
          heading={baseMapHeading}
          mapTypeId={baseMapType}
          interactive
          onMapTypeIdChange={onBaseMapTypeChange}
          onHeadingChange={onBaseMapHeadingChange}
        />
      ) : null}
      <Stage
        ref={stageRef}
        width={size.width}
        height={size.height}
        scaleX={stageScale}
        scaleY={stageScale}
        x={stageX}
        y={stageY}
        draggable={
          !mapReceivesPointer
          && tool === 'select'
          && activeVertexIndex === null
          && activePathVertexIndex === null
        }
        onDragStart={(event) => {
          if (event.target !== stageRef.current) return
          if (!hasBaseMap || baseMapInteractive) return
          const pointer = event.target.getStage()?.getPointerPosition()
          if (!pointer) return
          const container = containerRef.current
          if (!container) return
          const rect = container.getBoundingClientRect()
          if (!clientOverFloorPlan(rect.left + pointer.x, rect.top + pointer.y)) {
            event.target.stopDrag()
            setPassThroughToMap(true)
          }
        }}
        onDragEnd={(event) => {
          if (event.target !== stageRef.current) return
          setPosition({
            x: event.target.x() - centerX,
            y: event.target.y() - centerY,
          })
        }}
        onMouseDown={(event) => {
          if (!hasBaseMap || baseMapInteractive) return
          if (clientOverFloorPlan(event.evt.clientX, event.evt.clientY)) return
          const content = stageRef.current?.content
          if (content instanceof HTMLElement) {
            content.style.pointerEvents = 'none'
          }
          setPassThroughToMap(true)
        }}
        onWheel={(event) => {
          if (hasBaseMap && !baseMapInteractive && !clientOverFloorPlan(event.evt.clientX, event.evt.clientY)) {
            const content = stageRef.current?.content
            if (content instanceof HTMLElement) {
              content.style.pointerEvents = 'none'
            }
            setPassThroughToMap(true)

            const target = document.elementFromPoint(event.evt.clientX, event.evt.clientY)
            target?.dispatchEvent(new WheelEvent('wheel', {
              bubbles: true,
              cancelable: true,
              clientX: event.evt.clientX,
              clientY: event.evt.clientY,
              deltaX: event.evt.deltaX,
              deltaY: event.evt.deltaY,
              deltaZ: event.evt.deltaZ,
              deltaMode: event.evt.deltaMode,
            }))
            return
          }
          handleWheel(event)
        }}
        onMouseMove={(event) => {
          syncMapPassThrough(event.evt.clientX, event.evt.clientY)
          if (tool === 'select' || tool === 'delete') return
          const point = pointerRelative()
          setHoverPoint(point)
          onDraftPoint?.(point)
        }}
        onDblClick={() => {
          if (tool === 'polygon' && draftPoints.length >= 3) {
            createShapeZone('polygon', draftPoints)
            setDraftPoints([])
            setHoverPoint(null)
            onDraftPoint?.(null)
          }
          if (tool === 'path' && draftPoints.length >= 2) {
            finishPathDraft()
          }
        }}
        onClick={handleStageClick}
      >
        <Layer>
          {image ? (
            <KonvaImage
              image={image}
              width={imageWidth}
              height={imageHeight}
              opacity={floorOpacity}
              listening={false}
            />
          ) : (
            <Rect
              width={imageWidth}
              height={imageHeight}
              fill={hasBaseMap ? 'rgba(0,0,0,0)' : '#e5e7eb'}
              listening={false}
            />
          )}

          {displayZones.map((zone) => renderZone(zone))}

          {displayPaths.map((path) => {
            const selected = path.key === selectedPathKey
            const flat = pointsToFlat(path.polyline_coordinates, imageWidth, imageHeight)
            const stroke = path.stroke_color ?? DEFAULT_PATH_COLOR
            const strokeWidth = path.stroke_width ?? 3
            const opacity = (path.opacity ?? 85) / 100

            return (
              <Line
                key={`path-${path.key}`}
                points={flat}
                stroke={stroke}
                strokeWidth={selected ? strokeWidth + 1 : strokeWidth}
                opacity={opacity}
                lineCap="round"
                lineJoin="round"
                hitStrokeWidth={16}
                onClick={(event) => {
                  event.cancelBubble = true
                  if (tool === 'delete') {
                    onPathsChange(paths.filter((row) => row.key !== path.key))
                    onSelectPath(null)
                    return
                  }
                  onSelect(null)
                  onSelectPath(path.key)
                }}
                onMouseEnter={(event) => {
                  const container = event.target.getStage()?.container()
                  if (container) container.style.cursor = tool === 'select' ? 'pointer' : container.style.cursor
                }}
              />
            )
          })}

          {(tool === 'pillar' || tool === 'person') && draftPoints[0] && hoverPoint ? (
            <VenueMapMarkerShape
              shapeType={tool === 'pillar' ? 'pillar' : 'person'}
              x={toPixel(draftPoints[0], imageWidth, imageHeight).x}
              y={toPixel(draftPoints[0], imageWidth, imageHeight).y}
              radiusPx={Math.max(
                8,
                relativeRadius(draftPoints[0], hoverPoint, imageWidth / imageHeight) * imageWidth,
              )}
              fill="#2563eb"
              opacity={0.45}
              stroke="#1d4ed8"
              strokeWidth={2}
              listening={false}
            />
          ) : null}

          {draftPreview && tool !== 'pillar' && tool !== 'person' ? (
            <Line
              points={draftPreview}
              stroke="#2563eb"
              strokeWidth={2}
              dash={[8, 6]}
              closed={
                (tool === 'rectangle' || tool === 'triangle' || tool === 'hexagon')
                && draftPoints.length === 1
                && !!hoverPoint
              }
              lineCap="round"
              lineJoin="round"
            />
          ) : null}

          {draftPoints.map((point, index) => {
            const px = toPixel(point, imageWidth, imageHeight)
            return (
              <Circle
                key={`draft-${index}`}
                x={px.x}
                y={px.y}
                radius={5}
                fill="#2563eb"
                listening={false}
              />
            )
          })}

          {selectedVertexZone?.polygon_coordinates?.map((point, index) => {
            const zone = selectedVertexZone
            const rotation = zone.shape_rotation ?? 0
            const center = centroid(zone.polygon_coordinates!)
            const centerPx = toPixel(center, imageWidth, imageHeight)
            const px = toPixel(point, imageWidth, imageHeight)
            const visual = rotatePixelAround(px.x, px.y, centerPx.x, centerPx.y, rotation)
            const isDragging = activeVertexIndex === index

            return (
              <Circle
                key={`vertex-${zone.key}-${index}`}
                x={visual.x}
                y={visual.y}
                radius={7}
                fill="#ffffff"
                stroke="#2563eb"
                strokeWidth={2}
                draggable
                perfectDrawEnabled={false}
                hitStrokeWidth={12}
                dragBoundFunc={(pos) => {
                  // pos is absolute (stage/container space). Clamp in image-local space,
                  // then convert back — otherwise zoom/fit/center makes edge points jump inward.
                  const view = viewTransformRef.current
                  if (view.stageScale === 0) return pos
                  const localX = (pos.x - view.stageX) / view.stageScale
                  const localY = (pos.y - view.stageY) / view.stageScale
                  const clampedX = Math.min(view.imageWidth, Math.max(0, localX))
                  const clampedY = Math.min(view.imageHeight, Math.max(0, localY))
                  return {
                    x: view.stageX + clampedX * view.stageScale,
                    y: view.stageY + clampedY * view.stageScale,
                  }
                }}
                onMouseDown={(event) => {
                  event.cancelBubble = true
                }}
                onTouchStart={(event) => {
                  event.cancelBubble = true
                }}
                onClick={(event) => {
                  event.cancelBubble = true
                  onSelect(zone.key)
                  onSelectPath(null)
                }}
                onDragStart={(event) => {
                  event.cancelBubble = true
                  draggingVertexRef.current = { key: zone.key, index }
                  setActiveVertexIndex(index)

                  if ((zone.shape_rotation ?? 0) !== 0 && zone.polygon_coordinates) {
                    const baked = bakeRotationIntoPoints(
                      zone.polygon_coordinates,
                      zone.shape_rotation ?? 0,
                      imageWidth,
                      imageHeight,
                    )
                    const next = zonesRef.current.map((row) => (
                      row.key === zone.key
                        ? { ...row, polygon_coordinates: baked, shape_rotation: 0 }
                        : row
                    ))
                    dragPreviewRef.current = next
                    setDragPreview(next)
                  } else {
                    dragPreviewRef.current = zonesRef.current
                    setDragPreview(zonesRef.current)
                  }
                }}
                onDragMove={(event) => {
                  event.cancelBubble = true
                  const nextPoint = toRelative(event.target.x(), event.target.y(), imageWidth, imageHeight)
                  const next = moveVertexPoints(
                    dragPreviewRef.current ?? zonesRef.current,
                    zone.key,
                    index,
                    nextPoint,
                  )
                  dragPreviewRef.current = next
                  setDragPreview(next)
                }}
                onDragEnd={(event) => {
                  event.cancelBubble = true
                  const nextPoint = toRelative(event.target.x(), event.target.y(), imageWidth, imageHeight)
                  const nextZones = moveVertexPoints(
                    dragPreviewRef.current ?? zonesRef.current,
                    zone.key,
                    index,
                    nextPoint,
                  )
                  draggingVertexRef.current = null
                  dragPreviewRef.current = null
                  setActiveVertexIndex(null)
                  setDragPreview(null)
                  onZonesChange(nextZones)
                }}
                onMouseEnter={(event) => {
                  const container = event.target.getStage()?.container()
                  if (container) container.style.cursor = 'grab'
                }}
                onMouseLeave={() => {
                  if (isDragging) return
                  const container = stageRef.current?.container()
                  if (container) container.style.cursor = tool === 'select' ? 'default' : container.style.cursor
                }}
              />
            )
          })}

          {tool === 'select' && selectedPathKey ? (
            (displayPaths.find((path) => path.key === selectedPathKey)?.polyline_coordinates ?? []).map((point, index) => {
              const px = toPixel(point, imageWidth, imageHeight)
              const isDragging = activePathVertexIndex === index

              return (
                <Circle
                  key={`path-vertex-${selectedPathKey}-${index}`}
                  x={px.x}
                  y={px.y}
                  radius={6}
                  fill="#ffffff"
                  stroke="#2563eb"
                  strokeWidth={2}
                  draggable
                  perfectDrawEnabled={false}
                  hitStrokeWidth={12}
                  dragBoundFunc={(pos) => {
                    const view = viewTransformRef.current
                    if (view.stageScale === 0) return pos
                    const localX = (pos.x - view.stageX) / view.stageScale
                    const localY = (pos.y - view.stageY) / view.stageScale
                    const clampedX = Math.min(view.imageWidth, Math.max(0, localX))
                    const clampedY = Math.min(view.imageHeight, Math.max(0, localY))
                    return {
                      x: view.stageX + clampedX * view.stageScale,
                      y: view.stageY + clampedY * view.stageScale,
                    }
                  }}
                  onMouseDown={(event) => {
                    event.cancelBubble = true
                  }}
                  onDragStart={(event) => {
                    event.cancelBubble = true
                    draggingPathVertexRef.current = { key: selectedPathKey, index }
                    setActivePathVertexIndex(index)
                    pathDragPreviewRef.current = pathsRef.current
                    setPathDragPreview(pathsRef.current)
                  }}
                  onDragMove={(event) => {
                    event.cancelBubble = true
                    const nextPoint = toRelative(event.target.x(), event.target.y(), imageWidth, imageHeight)
                    const next = movePathVertex(
                      pathDragPreviewRef.current ?? pathsRef.current,
                      selectedPathKey,
                      index,
                      nextPoint,
                    )
                    pathDragPreviewRef.current = next
                    setPathDragPreview(next)
                  }}
                  onDragEnd={(event) => {
                    event.cancelBubble = true
                    const nextPoint = toRelative(event.target.x(), event.target.y(), imageWidth, imageHeight)
                    const nextPaths = movePathVertex(
                      pathDragPreviewRef.current ?? pathsRef.current,
                      selectedPathKey,
                      index,
                      nextPoint,
                    )
                    draggingPathVertexRef.current = null
                    pathDragPreviewRef.current = null
                    setActivePathVertexIndex(null)
                    setPathDragPreview(null)
                    onPathsChange(nextPaths)
                  }}
                  onMouseEnter={(event) => {
                    const container = event.target.getStage()?.container()
                    if (container) container.style.cursor = 'grab'
                  }}
                  onMouseLeave={() => {
                    if (isDragging) return
                    const container = stageRef.current?.container()
                    if (container) container.style.cursor = tool === 'select' ? 'default' : container.style.cursor
                  }}
                />
              )
            })
          ) : null}

          <Transformer
            ref={transformerRef}
            rotateEnabled={tool === 'select' && !selectedPathKey}
            rotationSnaps={[0, 45, 90, 135, 180, 225, 270, 315]}
            keepRatio={
              selectedKey !== null && (
                displayZones.find((zone) => zone.key === selectedKey)?.shape_type === 'circle'
                || isMarkerShape(displayZones.find((zone) => zone.key === selectedKey)?.shape_type)
              )
            }
            enabledAnchors={
              selectedKey !== null && supportsVertexEditing(
                displayZones.find((zone) => zone.key === selectedKey)?.shape_type,
              )
                ? []
                : selectedKey !== null && isPointRadiusShape(
                  displayZones.find((zone) => zone.key === selectedKey)?.shape_type,
                )
                  ? ['top-left', 'top-right', 'bottom-left', 'bottom-right']
                  : [
                    'top-left',
                    'top-center',
                    'top-right',
                    'middle-right',
                    'bottom-right',
                    'bottom-center',
                    'bottom-left',
                    'middle-left',
                  ]
            }
            boundBoxFunc={(oldBox, newBox) => (newBox.width < 8 || newBox.height < 8 ? oldBox : newBox)}
          />
        </Layer>
      </Stage>

      {hoverTooltip ? (
        <div
          className="venue-map-hover-popup"
          style={{
            left: hoverTooltip.x,
            top: hoverTooltip.y,
          }}
        >
          {hoverTooltip.name}
        </div>
      ) : null}
    </div>
  )
}
