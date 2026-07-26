import {
  Circle,
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
  clamp01,
  pointsToFlat,
  rectangleFromCorners,
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

  function createShapeZone(shapeType: ZoneShapeType, points: RelativePoint[], radius: number | null = null) {
    const base = zones.find((zone) => zone.key === selectedKey && !zone.shape_type)
      ?? zones.find((zone) => !zone.shape_type)

    if (base) {
      onZonesChange(zones.map((zone) => (
        zone.key === base.key
          ? withDefaults({
            ...zone,
            shape_type: shapeType,
            polygon_coordinates: points,
            shape_radius: radius,
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
      type: 'hall',
      capacity: null,
      shape_type: shapeType,
      polygon_coordinates: points,
      shape_radius: radius,
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

  function handleStageClick() {
    if (tool === 'select' || tool === 'delete') {
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

    if (tool === 'circle') {
      if (!rectStart) {
        setRectStart(point)
        setDraftPoints([point])
        onDraftPoint?.(point)
        return
      }

      const aspect = imageWidth / imageHeight
      createShapeZone('circle', [rectStart], relativeRadius(rectStart, point, aspect))
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

        if (zone.shape_type === 'circle') {
          updateZone(zone.key, {
            polygon_coordinates: [toRelative(node.x(), node.y(), imageWidth, imageHeight)],
          })
          return
        }

        const dx = node.x() / imageWidth
        const dy = node.y() / imageHeight
        node.position({ x: 0, y: 0 })

        if (!zone.polygon_coordinates) return

        updateZone(zone.key, {
          polygon_coordinates: zone.polygon_coordinates.map((point) => ({
            x: Number(Math.min(1, Math.max(0, point.x + dx)).toFixed(6)),
            y: Number(Math.min(1, Math.max(0, point.y + dy)).toFixed(6)),
          })),
        })
      },
      onTransformEnd: (event: Konva.KonvaEventObject<Event>) => {
        const node = event.target
        const scaleX = node.scaleX()
        const scaleY = node.scaleY()

        if (zone.shape_type === 'circle') {
          const circle = node as Konva.Circle
          const nextRadiusPx = Math.max(
            4,
            circle.radius() * ((Math.abs(scaleX) + Math.abs(scaleY)) / 2),
          )
          node.scaleX(1)
          node.scaleY(1)
          circle.radius(nextRadiusPx)

          updateZone(zone.key, {
            polygon_coordinates: [toRelative(node.x(), node.y(), imageWidth, imageHeight)],
            shape_radius: Number(Math.max(0.001, clamp01(nextRadiusPx / imageWidth)).toFixed(6)),
          })
          return
        }

        if (!zone.polygon_coordinates?.length) return

        const transformed = zone.polygon_coordinates.map((point) => {
          const px = toPixel(point, imageWidth, imageHeight)
          const nextX = node.x() + px.x * scaleX
          const nextY = node.y() + px.y * scaleY
          return toRelative(nextX, nextY, imageWidth, imageHeight)
        })

        node.scaleX(1)
        node.scaleY(1)
        node.position({ x: 0, y: 0 })

        updateZone(zone.key, {
          polygon_coordinates: zone.shape_type === 'rectangle'
            ? rectangleFromCorners(transformed[0], transformed[2] ?? transformed[1])
            : transformed,
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

    return (
      <Line
        key={zone.key}
        {...common}
        points={pointsToFlat(zone.polygon_coordinates, imageWidth, imageHeight)}
        closed
        tension={0}
      />
    )
  }

  const draftPreview = useMemo(() => {
    if (draftPoints.length === 0) return null
    const points = hoverPoint ? [...draftPoints, hoverPoint] : draftPoints
    return pointsToFlat(points, imageWidth, imageHeight)
  }, [draftPoints, hoverPoint, imageWidth, imageHeight])

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
            <Rect width={imageWidth} height={imageHeight} fill="#e5e7eb" />
          )}

          {zones.map((zone) => renderZone(zone))}

          {draftPreview ? (
            <Line
              points={draftPreview}
              stroke="#2563eb"
              strokeWidth={2}
              dash={[8, 6]}
              closed={tool === 'rectangle' && draftPoints.length === 1 && !!hoverPoint}
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
            rotateEnabled={false}
            keepRatio={selectedKey !== null && zones.find((zone) => zone.key === selectedKey)?.shape_type === 'circle'}
            enabledAnchors={
              selectedKey !== null && zones.find((zone) => zone.key === selectedKey)?.shape_type === 'circle'
                ? ['top-left', 'top-right', 'bottom-left', 'bottom-right']
                : undefined
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
