import { useEffect, useRef } from 'react'
import { metersToLatDegrees } from '@/components/venue-map/geoCoordinates'
import type { GeoPoint } from '@/components/venue-map/geoCoordinates'
import type { ZoneShapeType } from '@/components/venue-map/types'

const PERSON_PATH = [
  'M 0 -9.5',
  'a 3.6 3.6 0 1 1 0 7.2',
  'a 3.6 3.6 0 1 1 0 -7.2',
  'M -5.2 0.5',
  'c 0 -2.4 2.2 -3.6 5.2 -3.6',
  's 5.2 1.2 5.2 3.6',
  'v 2.2',
  'c 0 1.1 -0.9 2 -2 2',
  'h -1.1',
  'v 6.8',
  'c 0 1 -0.8 1.8 -1.8 1.8',
  'h -0.6',
  'c -1 0 -1.8 -0.8 -1.8 -1.8',
  'v -6.8',
  'h -1.1',
  'c -1.1 0 -2 -0.9 -2 -2',
  'z',
].join(' ')

type MarkerKind = Extract<ZoneShapeType, 'pillar' | 'person'>

type Props = {
  map: google.maps.Map
  center: GeoPoint
  radiusMeters: number
  shapeType: MarkerKind
  rotation?: number
  fill: string
  stroke: string
  opacity: number
  strokeWidth?: number
  selected?: boolean
  /** Visual-only layer sits above an invisible editable Circle. */
  interactive?: boolean
}

function radiusToPixels(
  projection: google.maps.MapCanvasProjection,
  center: GeoPoint,
  radiusMeters: number,
): number {
  const origin = projection.fromLatLngToDivPixel(new google.maps.LatLng(center.lat, center.lng))
  const edge = projection.fromLatLngToDivPixel(
    new google.maps.LatLng(center.lat + metersToLatDegrees(radiusMeters), center.lng),
  )
  if (!origin || !edge) return 16
  return Math.max(8, Math.abs(edge.y - origin.y))
}

function buildMarkerHtml(shapeType: MarkerKind): string {
  if (shapeType === 'pillar') {
    return `
      <div class="venue-map-geo-marker__pillar">
        <span class="venue-map-geo-marker__pillar-cap venue-map-geo-marker__pillar-cap--top"></span>
        <span class="venue-map-geo-marker__pillar-body"></span>
        <span class="venue-map-geo-marker__pillar-cap venue-map-geo-marker__pillar-cap--bottom"></span>
      </div>
    `
  }

  return `
    <div class="venue-map-geo-marker__person">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <g transform="translate(12 12)">
          <path d="${PERSON_PATH}"></path>
        </g>
      </svg>
    </div>
  `
}

/**
 * Person / pillar icon anchored to a lat/lng point on the Google Map.
 */
export default function GeoMarkerOverlay({
  map,
  center,
  radiusMeters,
  shapeType,
  rotation = 0,
  fill,
  stroke,
  opacity,
  strokeWidth = 2,
  selected = false,
  interactive = false,
}: Props) {
  const overlayRef = useRef<google.maps.OverlayView | null>(null)
  const centerRef = useRef(center)
  const radiusRef = useRef(radiusMeters)
  const rotationRef = useRef(rotation)
  const styleRef = useRef({ fill, stroke, opacity, strokeWidth, selected, shapeType })

  centerRef.current = center
  radiusRef.current = radiusMeters
  rotationRef.current = rotation
  styleRef.current = { fill, stroke, opacity, strokeWidth, selected, shapeType }

  useEffect(() => {
    const root = document.createElement('div')
    root.className = 'venue-map-geo-marker'
    root.innerHTML = buildMarkerHtml(shapeType)
    root.style.pointerEvents = interactive ? 'auto' : 'none'

    class MarkerOverlay extends google.maps.OverlayView {
      onAdd() {
        this.getPanes()?.overlayMouseTarget.appendChild(root)
      }

      draw() {
        const projection = this.getProjection()
        if (!projection) return
        const point = centerRef.current
        const px = projection.fromLatLngToDivPixel(new google.maps.LatLng(point.lat, point.lng))
        if (!px) return

        const radiusPx = radiusToPixels(projection, point, radiusRef.current)
        const size = Math.max(16, radiusPx * 2)
        const style = styleRef.current

        root.style.left = `${px.x}px`
        root.style.top = `${px.y}px`
        root.style.width = `${size}px`
        root.style.height = `${size}px`
        root.style.marginLeft = `${-size / 2}px`
        root.style.marginTop = `${-size / 2}px`
        root.style.transform = `rotate(${rotationRef.current}deg)`
        root.style.setProperty('--marker-fill', style.fill)
        root.style.setProperty('--marker-stroke', style.stroke)
        root.style.setProperty('--marker-opacity', String(Math.min(1, Math.max(0.15, style.opacity + 0.2))))
        root.style.setProperty('--marker-stroke-width', String(Math.max(1, style.strokeWidth)))
        root.classList.toggle('is-selected', style.selected)
        root.classList.toggle('is-person', style.shapeType === 'person')
        root.classList.toggle('is-pillar', style.shapeType === 'pillar')
      }

      onRemove() {
        root.remove()
      }
    }

    const overlay = new MarkerOverlay()
    overlay.setMap(map)
    overlayRef.current = overlay

    return () => {
      overlay.setMap(null)
      overlayRef.current = null
    }
  }, [map, shapeType, interactive])

  useEffect(() => {
    overlayRef.current?.draw()
  }, [center, radiusMeters, rotation, fill, stroke, opacity, strokeWidth, selected])

  return null
}

export function isGeoMarkerShape(shapeType: string | null | undefined): shapeType is MarkerKind {
  return shapeType === 'pillar' || shapeType === 'person'
}
