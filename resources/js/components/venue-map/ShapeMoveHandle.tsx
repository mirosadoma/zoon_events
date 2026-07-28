import { useEffect, useRef } from 'react'
import {
  boundsFromGeoPoints,
  centroidGeo,
  circleBoundsFromCenterRadius,
  type GeoPoint,
  type OverlayBounds,
} from '@/components/venue-map/geoCoordinates'

type Props = {
  map: google.maps.Map
  points: GeoPoint[]
  radiusMeters?: number | null
  onMove: (nextPoints: GeoPoint[]) => void
  onInteractionChange?: (active: boolean) => void
  label?: string
}

const HANDLE_OUTSET_PX = 28

function resolveBounds(points: GeoPoint[], radiusMeters?: number | null): OverlayBounds | null {
  if (radiusMeters != null && radiusMeters > 0 && points.length >= 1) {
    return circleBoundsFromCenterRadius(points[0], radiusMeters)
  }
  return boundsFromGeoPoints(points)
}

function resolvePivot(points: GeoPoint[], radiusMeters?: number | null): GeoPoint {
  if (radiusMeters != null && radiusMeters > 0 && points.length >= 1) {
    return points[0]
  }
  return centroidGeo(points)
}

function translatePoints(points: GeoPoint[], dLat: number, dLng: number): GeoPoint[] {
  return points.map((point) => ({
    lat: point.lat + dLat,
    lng: point.lng + dLng,
  }))
}

/**
 * Visible drag handle beside the selected shape. Shape body remains mouse-draggable too.
 */
export default function ShapeMoveHandle({
  map,
  points,
  radiusMeters = null,
  onMove,
  onInteractionChange,
  label = 'Move shape',
}: Props) {
  const overlayRef = useRef<google.maps.OverlayView | null>(null)
  const pointsRef = useRef(points)
  const radiusRef = useRef(radiusMeters)
  const onMoveRef = useRef(onMove)
  const onInteractionChangeRef = useRef(onInteractionChange)
  const interactingRef = useRef(false)

  if (!interactingRef.current) {
    pointsRef.current = points
  }
  radiusRef.current = radiusMeters
  onMoveRef.current = onMove
  onInteractionChangeRef.current = onInteractionChange

  useEffect(() => {
    const root = document.createElement('div')
    root.className = 'venue-map-shape-move'
    root.style.left = '0px'
    root.style.top = '0px'
    root.style.width = '0px'
    root.style.height = '0px'

    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'venue-map-shape-move__handle'
    button.setAttribute('aria-label', label)
    button.title = label
    button.innerHTML = `
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 3v18M3 12h18M7.5 7.5l9 9M16.5 7.5l-9 9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    `
    root.appendChild(button)

    function unlockMap() {
      map.setOptions({ draggable: true, gestureHandling: 'greedy' })
    }

    button.addEventListener('pointerdown', (event) => {
      event.preventDefault()
      event.stopPropagation()
      interactingRef.current = true
      onInteractionChangeRef.current?.(true)
      map.setOptions({ draggable: false, gestureHandling: 'none' })

      const startPoints = pointsRef.current.map((point) => ({ ...point }))
      const pivot = resolvePivot(startPoints, radiusRef.current)
      const projection = overlayRef.current?.getProjection()
      const mapRect = map.getDiv().getBoundingClientRect()
      if (!projection) {
        interactingRef.current = false
        onInteractionChangeRef.current?.(false)
        unlockMap()
        return
      }

      const startPivotPx = projection.fromLatLngToContainerPixel(
        new google.maps.LatLng(pivot.lat, pivot.lng),
      )
      if (!startPivotPx) {
        interactingRef.current = false
        onInteractionChangeRef.current?.(false)
        unlockMap()
        return
      }

      // Keep shape under the same cursor offset as when the drag started.
      const cursorOffsetX = event.clientX - (mapRect.left + startPivotPx.x)
      const cursorOffsetY = event.clientY - (mapRect.top + startPivotPx.y)

      function onMovePointer(moveEvent: PointerEvent) {
        const nextLatLng = projection!.fromContainerPixelToLatLng(
          new google.maps.Point(
            moveEvent.clientX - mapRect.left - cursorOffsetX,
            moveEvent.clientY - mapRect.top - cursorOffsetY,
          ),
        )
        if (!nextLatLng) return
        const nextPoints = translatePoints(
          startPoints,
          nextLatLng.lat() - pivot.lat,
          nextLatLng.lng() - pivot.lng,
        )
        pointsRef.current = nextPoints
        onMoveRef.current(nextPoints)
        overlayRef.current?.draw()
      }

      function onUp() {
        window.removeEventListener('pointermove', onMovePointer)
        window.removeEventListener('pointerup', onUp)
        unlockMap()
        interactingRef.current = false
        onInteractionChangeRef.current?.(false)
      }

      window.addEventListener('pointermove', onMovePointer)
      window.addEventListener('pointerup', onUp)
    })

    class MoveOverlay extends google.maps.OverlayView {
      onAdd() {
        this.getPanes()?.overlayMouseTarget.appendChild(root)
      }

      draw() {
        const projection = this.getProjection()
        if (!projection) return
        const current = pointsRef.current
        const bounds = resolveBounds(current, radiusRef.current)
        if (!bounds) return

        const sw = projection.fromLatLngToDivPixel(new google.maps.LatLng(bounds.south, bounds.west))
        const ne = projection.fromLatLngToDivPixel(new google.maps.LatLng(bounds.north, bounds.east))
        if (!sw || !ne) return

        const right = Math.max(sw.x, ne.x)
        const top = Math.min(sw.y, ne.y)
        const bottom = Math.max(sw.y, ne.y)
        const midY = (top + bottom) / 2

        button.style.left = `${right + HANDLE_OUTSET_PX}px`
        button.style.top = `${midY}px`
        button.style.pointerEvents = 'auto'
      }

      onRemove() {
        root.remove()
      }
    }

    const overlay = new MoveOverlay()
    overlay.setMap(map)
    overlayRef.current = overlay

    return () => {
      unlockMap()
      overlay.setMap(null)
      overlayRef.current = null
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [map, label])

  useEffect(() => {
    if (interactingRef.current) return
    pointsRef.current = points
    radiusRef.current = radiusMeters
    overlayRef.current?.draw()
  }, [points, radiusMeters])

  return null
}
