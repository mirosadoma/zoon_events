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
  centeredLocalFlat,
  clamp01,
  normalizeDegrees,
  pointsToFlat,
  rectangleFromCorners,
  regularPolygonPoints,
  relativeRadius,
  snapRelative,
  toPixel,
  toRelative,
} from '@/components/venue-map/coordinates'
import {
  defaultFillForType,
  type EditorTool,
  type MapZone,
  type RelativePoint,
  type ZoneShapeType,
} from '@/components/venue-map/types'

type Props = {
  imageUrl: string | null
  naturalWidth: number
  naturalHeight: number
  zones: MapZone[]
  selectedKey: string | null
  tool: EditorTool
  locale: 'en' | 'ar'
  onSelect: (key: string | null) => void
  onZonesChange: (zones: MapZone[]) => void
  onDraftPoint?: (point: RelativePoint | null) => void
}

function useHtmlImage(url: string | null): HTMLImageElement | null {
  const [image, setImage] = useState<HTMLImageElement | null>(null)

  useEffect(() => {
    if (!url) {
      setImage(null)
      return
    }

    const element = new window.Image()
    element.crossOrigin = 'anonymous'
    element.onload = () => setImage(element)
    element.onerror = () => setImage(null)
    element.src = url

    return () => {
      element.onload = null
      element.onerror = null
    }
  }, [url])

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
  selectedKey,
  tool,
  locale,
  onSelect,
  onZonesChange,
  onDraftPoint,
}: Props) {
  const containerRef = useRef<HTMLDivElement | null>(null)
  const stageRef = useRef<Konva.Stage | null>(null)
  const transformerRef = useRef<Konva.Transformer | null>(null)
  const shapeRefs = useRef<Record<string, Konva.Node>>({})
  const image = useHtmlImage(imageUrl)

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
  const fitScale = Math.min(size.width / imageWidth, size.height / imageHeight, 1)
  const stageScale = fitScale * scale

  const anchors = useMemo(() => {
    const points: RelativePoint[] = []
    for (const zone of zones) {
      if (zone.polygon_coordinates) {
        points.push(...zone.polygon_coordinates)
      }
    }
    points.push(...draftPoints)
    return points
  }, [zones, draftPoints])

  useEffect(() => {
    const transformer = transformerRef.current
    if (!transformer) return

    const selected = selectedKey ? shapeRefs.current[selectedKey] : null
    if (selected && tool === 'select') {
      transformer.nodes([selected])
      transformer.forceUpdate()
      transformer.getLayer()?.batchDraw()
    } else {
      transformer.nodes([])
      transformer.getLayer()?.batchDraw()
    }
  }, [selectedKey, tool, zones])

  function pointerRelative(): RelativePoint | null {
    const stage = stageRef.current
    if (!stage) return null
    const pointer = stage.getPointerPosition()
    if (!pointer) return null

    const x = (pointer.x - position.x) / stageScale
    const y = (pointer.y - position.y) / stageScale
    return snapRelative(toRelative(x, y, imageWidth, imageHeight), anchors)
  }

  function updateZone(key: string, patch: Partial<MapZone>) {
    onZonesChange(zones.map((zone) => (zone.key === key ? { ...zone, ...patch } : zone)))
  }

  function createShapeZone(
    shapeType: ZoneShapeType,
    points: RelativePoint[],
    radius: number | null = null,
    radiusY: number | null = null,
  ) {
    // Prefer the selected zone so organizers can replace its shape without deleting the zone.
    const selected = selectedKey
      ? zones.find((zone) => zone.key === selectedKey)
      : null
    const base = selected ?? zones.find((zone) => !zone.shape_type)

    if (base) {
      onZonesChange(zones.map((zone) => (
        zone.key === base.key
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
      onSelect(base.key)
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
        setHoverTooltip(null)
        setHoveredKey(null)
      }
      return
    }

    const point = pointerRelative()
    if (!point) return

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

    if (tool === 'circle' || tool === 'ellipse') {
      if (!rectStart) {
        setRectStart(point)
        setDraftPoints([point])
        onDraftPoint?.(point)
        return
      }

      const aspect = imageWidth / imageHeight
      const radius = relativeRadius(rectStart, point, aspect)
      createShapeZone(
        tool === 'ellipse' ? 'ellipse' : 'circle',
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
    const mousePointTo = {
      x: (pointer.x - position.x) / (fitScale * oldScale),
      y: (pointer.y - position.y) / (fitScale * oldScale),
    }

    setScale(nextScale)
    setPosition({
      x: pointer.x - mousePointTo.x * fitScale * nextScale,
      y: pointer.y - mousePointTo.y * fitScale * nextScale,
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
        onSelect(zone.key)
      },
      onDragEnd: (event: Konva.KonvaEventObject<DragEvent>) => {
        const node = event.target

        if (zone.shape_type === 'circle' || zone.shape_type === 'ellipse') {
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

        if (zone.shape_type === 'circle' || zone.shape_type === 'ellipse') {
          const scaleX = Math.abs(node.scaleX())
          const scaleY = Math.abs(node.scaleY())
          const baseRadiusX = (zone.shape_radius ?? 0.05) * imageWidth
          const baseRadiusY = (zone.shape_radius_y ?? zone.shape_radius ?? 0.05) * imageHeight
          node.scaleX(1)
          node.scaleY(1)

          if (zone.shape_type === 'circle') {
            const circle = node as Konva.Circle
            const nextRadiusPx = Math.max(4, baseRadiusX * ((scaleX + scaleY) / 2))
            circle.radius(nextRadiusPx)
            updateZone(zone.key, {
              polygon_coordinates: [toRelative(node.x(), node.y(), imageWidth, imageHeight)],
              shape_radius: Number(Math.max(0.001, clamp01(nextRadiusPx / imageWidth)).toFixed(6)),
              shape_radius_y: null,
              shape_rotation: nextRotation,
            })
            return
          }

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

  return (
    <div ref={containerRef} className="venue-map-canvas">
      <Stage
        ref={stageRef}
        width={size.width}
        height={size.height}
        scaleX={stageScale}
        scaleY={stageScale}
        x={position.x}
        y={position.y}
        draggable={tool === 'select'}
        onDragEnd={(event) => {
          if (event.target !== stageRef.current) return
          setPosition({ x: event.target.x(), y: event.target.y() })
        }}
        onWheel={handleWheel}
        onMouseMove={() => {
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
        }}
        onClick={handleStageClick}
      >
        <Layer>
          {image ? (
            <KonvaImage image={image} width={imageWidth} height={imageHeight} listening={false} />
          ) : (
            <Rect width={imageWidth} height={imageHeight} fill="#e5e7eb" listening={false} />
          )}

          {zones.map((zone) => renderZone(zone))}

          {draftPreview ? (
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

          <Transformer
            ref={transformerRef}
            rotateEnabled={tool === 'select'}
            rotationSnaps={[0, 45, 90, 135, 180, 225, 270, 315]}
            keepRatio={selectedKey !== null && zones.find((zone) => zone.key === selectedKey)?.shape_type === 'circle'}
            enabledAnchors={
              selectedKey !== null && (
                zones.find((zone) => zone.key === selectedKey)?.shape_type === 'circle'
                || zones.find((zone) => zone.key === selectedKey)?.shape_type === 'ellipse'
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
