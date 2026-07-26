import { Circle, Group, Image as KonvaImage, Layer, Line, Stage } from 'react-konva'
import type Konva from 'konva'
import { useEffect, useMemo, useRef, useState } from 'react'
import { pointsToFlat, toPixel } from '@/components/venue-map/coordinates'
import { defaultFillForType, type RelativePoint } from '@/components/venue-map/types'

type PublicZone = {
  id: string
  name: { en: string; ar: string }
  label: string | null
  type: string
  shape_type: 'polygon' | 'rectangle' | 'circle' | null
  polygon_coordinates: RelativePoint[] | null
  shape_radius: number | null
  fill_color: string | null
  stroke_color: string | null
  opacity: number | null
  stroke_width: number | null
  navigate_url: string | null
  lat?: number | null
  lng?: number | null
}

type Props = {
  imageUrl: string | null
  width: number
  height: number
  zones: PublicZone[]
  locale: 'en' | 'ar'
  navigateLabel: string
  navigateHint?: string
}

export default function VenueMapViewer({
  imageUrl,
  width,
  height,
  zones,
  locale,
  navigateLabel,
  navigateHint,
}: Props) {
  const containerRef = useRef<HTMLDivElement | null>(null)
  const [size, setSize] = useState({ width: 720, height: 480 })
  const [image, setImage] = useState<HTMLImageElement | null>(null)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [hoverId, setHoverId] = useState<string | null>(null)
  const [hoverTooltip, setHoverTooltip] = useState<{ id: string; name: string; x: number; y: number } | null>(null)

  const naturalWidth = Math.max(width, 1)
  const naturalHeight = Math.max(height, 1)
  const aspect = naturalHeight / naturalWidth

  useEffect(() => {
    const element = containerRef.current
    if (!element) return

    const syncSize = (nextWidth: number) => {
      const safeWidth = Math.max(280, nextWidth)
      setSize({
        width: safeWidth,
        height: Math.max(280, Math.round(safeWidth * aspect)),
      })
    }

    syncSize(element.clientWidth)

    const observer = new ResizeObserver((entries) => {
      const entry = entries[0]
      if (!entry) return
      syncSize(entry.contentRect.width)
    })

    observer.observe(element)
    return () => observer.disconnect()
  }, [aspect])

  useEffect(() => {
    if (!imageUrl) {
      setImage(null)
      return
    }
    const element = new window.Image()
    element.onload = () => setImage(element)
    element.src = imageUrl
  }, [imageUrl])

  const scale = useMemo(
    () => size.width / naturalWidth,
    [size.width, naturalWidth],
  )
  const selected = zones.find((zone) => zone.id === selectedId) ?? null

  function labelFor(zone: PublicZone): string {
    if (zone.label?.trim()) return zone.label
    return locale === 'ar' ? (zone.name.ar || zone.name.en) : (zone.name.en || zone.name.ar)
  }

  function directionsUrl(zone: PublicZone): string | null {
    if (zone.lat != null && zone.lng != null) {
      return `https://www.google.com/maps/dir/?api=1&destination=${zone.lat},${zone.lng}`
    }

    return zone.navigate_url
  }

  function updateHoverTooltip(zone: PublicZone, event: Konva.KonvaEventObject<MouseEvent>) {
    const pointer = event.target.getStage()?.getPointerPosition()
    if (!pointer) return
    setHoverId(zone.id)
    setHoverTooltip({
      id: zone.id,
      name: labelFor(zone),
      x: pointer.x,
      y: pointer.y,
    })
  }

  return (
    <div className="venue-map-viewer">
      <div
        ref={containerRef}
        className="venue-map-viewer__canvas"
        style={{ height: size.height }}
      >
        <Stage
          width={size.width}
          height={size.height}
          scaleX={scale}
          scaleY={scale}
        >
          <Layer>
            {image ? (
              <KonvaImage image={image} width={naturalWidth} height={naturalHeight} />
            ) : null}
            {zones.map((zone) => {
              if (!zone.shape_type || !zone.polygon_coordinates?.length) return null
              const active = zone.id === selectedId || zone.id === hoverId
              const fill = zone.fill_color ?? defaultFillForType(zone.type)
              const opacity = Math.min(((zone.opacity ?? 45) / 100) * (active ? 1.25 : 1), 0.85)
              const stroke = zone.stroke_color ?? '#111827'
              const center = zone.polygon_coordinates[0]
              const px = toPixel(center, naturalWidth, naturalHeight)

              const handlers = {
                onMouseEnter: (event: Konva.KonvaEventObject<MouseEvent>) => updateHoverTooltip(zone, event),
                onMouseMove: (event: Konva.KonvaEventObject<MouseEvent>) => updateHoverTooltip(zone, event),
                onMouseLeave: () => {
                  setHoverId((current) => (current === zone.id ? null : current))
                  setHoverTooltip((current) => (current?.id === zone.id ? null : current))
                },
                onClick: () => setSelectedId(zone.id),
              }

              return (
                <Group key={zone.id}>
                  {zone.shape_type === 'circle' ? (
                    <Circle
                      x={px.x}
                      y={px.y}
                      radius={(zone.shape_radius ?? 0.05) * naturalWidth}
                      fill={fill}
                      opacity={opacity}
                      stroke={stroke}
                      strokeWidth={active ? 3 : 2}
                      {...handlers}
                    />
                  ) : (
                    <Line
                      points={pointsToFlat(zone.polygon_coordinates, naturalWidth, naturalHeight)}
                      closed
                      fill={fill}
                      opacity={opacity}
                      stroke={stroke}
                      strokeWidth={active ? 3 : 2}
                      {...handlers}
                    />
                  )}
                </Group>
              )
            })}
          </Layer>
        </Stage>

        {hoverTooltip ? (
          <div
            className="venue-map-hover-popup"
            style={{ left: hoverTooltip.x, top: hoverTooltip.y }}
          >
            {hoverTooltip.name}
          </div>
        ) : null}
      </div>

      {selected ? (
        <div className="venue-map-viewer__popup">
          <div>
            <strong>{labelFor(selected)}</strong>
            {navigateHint ? (
              <p className="mt-1 text-sm text-[var(--muted)]">{navigateHint}</p>
            ) : null}
          </div>
          {directionsUrl(selected) ? (
            <a
              className="button-primary"
              href={directionsUrl(selected) ?? undefined}
              target="_blank"
              rel="noreferrer"
            >
              {navigateLabel}
            </a>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}
