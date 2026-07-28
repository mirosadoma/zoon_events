import { useEffect, useRef } from 'react'
import { normalizeDegrees } from '@/components/venue-map/coordinates'
import {
  centroidGeo,
  metersToLatDegrees,
  metersToLngDegrees,
  rotateGeoPointsAround,
  type GeoPoint,
} from '@/components/venue-map/geoCoordinates'

type Props = {
  map: google.maps.Map
  points: GeoPoint[]
  /** Circle-like shapes use radius instead of polygon vertices. */
  radiusMeters?: number | null
  rotation: number
  onRotate: (nextPoints: GeoPoint[], nextRotation: number) => void
  onInteractionChange?: (active: boolean) => void
}

const HANDLE_OUTSET_PX = 22

/** Rim anchors for circle-like shapes — sit on the outline and follow rotation. */
function circleAnchorPoints(
  center: GeoPoint,
  radiusMeters: number,
  rotationDegrees: number,
): GeoPoint[] {
  // 45° offsets so knobs feel like corners of the surrounding square.
  const points: GeoPoint[] = []
  for (let i = 0; i < 4; i += 1) {
    const angle = ((rotationDegrees + 45 + i * 90) * Math.PI) / 180
    points.push({
      lat: center.lat + metersToLatDegrees(radiusMeters * Math.cos(angle)),
      lng: center.lng + metersToLngDegrees(radiusMeters * Math.sin(angle), center.lat),
    })
  }
  return points
}

function resolveAnchorPoints(
  points: GeoPoint[],
  radiusMeters: number | null | undefined,
  rotation: number,
): GeoPoint[] {
  if (radiusMeters != null && radiusMeters > 0 && points.length >= 1) {
    return circleAnchorPoints(points[0], radiusMeters, rotation)
  }
  return points
}

function resolvePivot(
  points: GeoPoint[],
  radiusMeters: number | null | undefined,
): GeoPoint {
  if (radiusMeters != null && radiusMeters > 0 && points.length >= 1) {
    return points[0]
  }
  return centroidGeo(points)
}

/**
 * Rotate knobs locked to the shape's own corners/vertices — they move with the shape.
 */
export default function ShapeRotateOverlay({
  map,
  points,
  radiusMeters = null,
  rotation,
  onRotate,
  onInteractionChange,
}: Props) {
  const overlayRef = useRef<google.maps.OverlayView | null>(null)
  const pointsRef = useRef(points)
  const rotationRef = useRef(rotation)
  const radiusRef = useRef(radiusMeters)
  const onRotateRef = useRef(onRotate)
  const onInteractionChangeRef = useRef(onInteractionChange)
  const interactingRef = useRef(false)

  if (!interactingRef.current) {
    pointsRef.current = points
    rotationRef.current = rotation
  }
  radiusRef.current = radiusMeters
  onRotateRef.current = onRotate
  onInteractionChangeRef.current = onInteractionChange

  useEffect(() => {
    const controls = document.createElement('div')
    controls.className = 'venue-map-shape-rotate'
    const rotateEls: HTMLButtonElement[] = []

    function unlockMap() {
      map.setOptions({ draggable: true, gestureHandling: 'greedy' })
    }

    function ensureButtons(count: number) {
      while (rotateEls.length < count) {
        const button = document.createElement('button')
        button.type = 'button'
        button.className = 'venue-map-floor-overlay__rotate'
        button.setAttribute('aria-label', 'Rotate shape')
        button.addEventListener('pointerdown', (event) => {
          event.preventDefault()
          event.stopPropagation()
          interactingRef.current = true
          onInteractionChangeRef.current?.(true)
          map.setOptions({ draggable: false, gestureHandling: 'none' })

          const startPoints = pointsRef.current.map((point) => ({ ...point }))
          const startRotation = rotationRef.current
          const pivot = resolvePivot(startPoints, radiusRef.current)
          const projection = overlayRef.current?.getProjection()
          const mapRect = map.getDiv().getBoundingClientRect()
          const containerPivot = projection?.fromLatLngToContainerPixel(
            new google.maps.LatLng(pivot.lat, pivot.lng),
          )
          const pivotClient = containerPivot
            ? { x: mapRect.left + containerPivot.x, y: mapRect.top + containerPivot.y }
            : { x: event.clientX, y: event.clientY }

          const startAngle = Math.atan2(event.clientY - pivotClient.y, event.clientX - pivotClient.x)

          function onMove(moveEvent: PointerEvent) {
            const angle = Math.atan2(moveEvent.clientY - pivotClient.y, moveEvent.clientX - pivotClient.x)
            const deltaDeg = ((angle - startAngle) * 180) / Math.PI
            const nextRotation = normalizeDegrees(startRotation + deltaDeg)
            const nextPoints = rotateGeoPointsAround(startPoints, pivot, deltaDeg)
            pointsRef.current = nextPoints
            rotationRef.current = nextRotation
            onRotateRef.current(nextPoints, nextRotation)
            overlayRef.current?.draw()
          }

          function onUp() {
            window.removeEventListener('pointermove', onMove)
            window.removeEventListener('pointerup', onUp)
            unlockMap()
            interactingRef.current = false
            onInteractionChangeRef.current?.(false)
          }

          window.addEventListener('pointermove', onMove)
          window.addEventListener('pointerup', onUp)
        })
        rotateEls.push(button)
        controls.appendChild(button)
      }

      for (let i = 0; i < rotateEls.length; i += 1) {
        rotateEls[i].style.display = i < count ? 'block' : 'none'
      }
    }

    class RotateOverlay extends google.maps.OverlayView {
      onAdd() {
        this.getPanes()?.overlayMouseTarget.appendChild(controls)
      }

      draw() {
        const projection = this.getProjection()
        if (!projection) return

        const currentPoints = pointsRef.current
        const anchors = resolveAnchorPoints(
          currentPoints,
          radiusRef.current,
          rotationRef.current,
        )
        if (anchors.length === 0) return

        const pivot = resolvePivot(currentPoints, radiusRef.current)
        const pivotPx = projection.fromLatLngToDivPixel(
          new google.maps.LatLng(pivot.lat, pivot.lng),
        )
        if (!pivotPx) return

        // Zero-size origin so each knob can use absolute div-pixel coordinates.
        controls.style.left = '0px'
        controls.style.top = '0px'
        controls.style.width = '0px'
        controls.style.height = '0px'
        controls.style.pointerEvents = 'none'

        ensureButtons(anchors.length)

        for (let i = 0; i < anchors.length; i += 1) {
          const anchor = anchors[i]
          const px = projection.fromLatLngToDivPixel(
            new google.maps.LatLng(anchor.lat, anchor.lng),
          )
          const button = rotateEls[i]
          if (!px || !button) continue

          const dx = px.x - pivotPx.x
          const dy = px.y - pivotPx.y
          const len = Math.hypot(dx, dy) || 1
          // Sit just outside the real vertex, along the ray from the shape center.
          button.style.left = `${px.x + (dx / len) * HANDLE_OUTSET_PX}px`
          button.style.top = `${px.y + (dy / len) * HANDLE_OUTSET_PX}px`
          button.style.pointerEvents = 'auto'
        }
      }

      onRemove() {
        controls.remove()
      }
    }

    const overlay = new RotateOverlay()
    overlay.setMap(map)
    overlayRef.current = overlay

    return () => {
      unlockMap()
      overlay.setMap(null)
      overlayRef.current = null
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [map])

  useEffect(() => {
    if (interactingRef.current) return
    pointsRef.current = points
    rotationRef.current = rotation
    radiusRef.current = radiusMeters
    overlayRef.current?.draw()
  }, [points, rotation, radiusMeters])

  return null
}
