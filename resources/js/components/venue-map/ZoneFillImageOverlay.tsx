import { useEffect, useRef } from 'react'
import type { GeoPoint } from '@/components/venue-map/types'

type Props = {
  map: google.maps.Map
  points: GeoPoint[]
  radiusMeters?: number | null
  imageUrl: string
  opacity: number
  /** Degrees — used for circle-like shapes (polygons derive angle from edges). */
  rotation?: number
  zIndex?: number
}

function escapeAttr(value: string): string {
  return value.replace(/"/g, '&quot;')
}

function screenAngleDegrees(pixels: google.maps.Point[]): number {
  if (pixels.length < 2) return 0
  return (Math.atan2(pixels[1].y - pixels[0].y, pixels[1].x - pixels[0].x) * 180) / Math.PI
}

/**
 * Draws a fill image clipped to a polygon (or circle).
 * The whole clipped image rotates with the shape so texture stays locked to it.
 */
export default function ZoneFillImageOverlay({
  map,
  points,
  radiusMeters = null,
  imageUrl,
  opacity,
  rotation = 0,
  zIndex = 2,
}: Props) {
  const overlayRef = useRef<google.maps.OverlayView | null>(null)
  const pointsRef = useRef(points)
  const radiusRef = useRef(radiusMeters)
  const imageUrlRef = useRef(imageUrl)
  const opacityRef = useRef(opacity)
  const rotationRef = useRef(rotation)
  const zIndexRef = useRef(zIndex)

  pointsRef.current = points
  radiusRef.current = radiusMeters
  imageUrlRef.current = imageUrl
  opacityRef.current = opacity
  rotationRef.current = rotation
  zIndexRef.current = zIndex

  useEffect(() => {
    if (typeof google === 'undefined' || !map || points.length === 0) {
      return
    }

    class FillOverlay extends google.maps.OverlayView {
      private div: HTMLDivElement | null = null

      onAdd() {
        const div = document.createElement('div')
        div.style.position = 'absolute'
        div.style.pointerEvents = 'none'
        div.style.overflow = 'hidden'
        div.style.willChange = 'transform'
        this.div = div
        this.getPanes()?.overlayLayer.appendChild(div)
      }

      draw() {
        const projection = this.getProjection()
        const div = this.div
        const currentPoints = pointsRef.current
        if (!projection || !div || currentPoints.length === 0) {
          return
        }

        const currentRadius = radiusRef.current
        const currentImageUrl = escapeAttr(imageUrlRef.current)
        const currentOpacity = opacityRef.current
        const currentRotation = rotationRef.current
        div.style.zIndex = String(zIndexRef.current)
        div.style.opacity = String(currentOpacity)

        const pixels = currentPoints.map((point) => projection.fromLatLngToDivPixel(
          new google.maps.LatLng(point.lat, point.lng),
        )).filter((pixel): pixel is google.maps.Point => pixel !== null)

        if (pixels.length === 0) {
          return
        }

        if (currentRadius != null && currentPoints.length === 1) {
          const center = projection.fromLatLngToDivPixel(
            new google.maps.LatLng(currentPoints[0].lat, currentPoints[0].lng),
          )
          if (!center) return
          const edge = google.maps.geometry?.spherical?.computeOffset
            ? projection.fromLatLngToDivPixel(
              google.maps.geometry.spherical.computeOffset(
                new google.maps.LatLng(currentPoints[0].lat, currentPoints[0].lng),
                currentRadius,
                90,
              ),
            )
            : null
          const radiusPx = edge ? Math.max(4, Math.abs(edge.x - center.x)) : 24
          const size = radiusPx * 2
          div.style.left = `${center.x}px`
          div.style.top = `${center.y}px`
          div.style.width = `${size}px`
          div.style.height = `${size}px`
          div.style.borderRadius = '50%'
          div.style.clipPath = ''
          div.style.transform = `translate(-50%, -50%) rotate(${currentRotation}deg)`
          div.style.transformOrigin = 'center center'
          div.innerHTML = `<img src="${currentImageUrl}" alt="" style="display:block;width:100%;height:100%;object-fit:cover;border-radius:50%;" />`
          return
        }

        // Un-rotate screen points into the shape's local frame, then rotate the
        // whole clipped image together so texture stays locked to the polygon.
        const angle = screenAngleDegrees(pixels)
        const rad = (-angle * Math.PI) / 180
        const cos = Math.cos(rad)
        const sin = Math.sin(rad)
        const cx = pixels.reduce((sum, pixel) => sum + pixel.x, 0) / pixels.length
        const cy = pixels.reduce((sum, pixel) => sum + pixel.y, 0) / pixels.length

        let localMinX = Infinity
        let localMinY = Infinity
        let localMaxX = -Infinity
        let localMaxY = -Infinity
        const localPixels: Array<{ x: number; y: number }> = []
        for (const pixel of pixels) {
          const dx = pixel.x - cx
          const dy = pixel.y - cy
          const lx = dx * cos - dy * sin
          const ly = dx * sin + dy * cos
          localPixels.push({ x: lx, y: ly })
          localMinX = Math.min(localMinX, lx)
          localMinY = Math.min(localMinY, ly)
          localMaxX = Math.max(localMaxX, lx)
          localMaxY = Math.max(localMaxY, ly)
        }

        const width = Math.max(1, localMaxX - localMinX)
        const height = Math.max(1, localMaxY - localMinY)
        const clip = localPixels
          .map((pixel) => `${pixel.x - localMinX}px ${pixel.y - localMinY}px`)
          .join(', ')

        div.style.left = `${cx}px`
        div.style.top = `${cy}px`
        div.style.width = `${width}px`
        div.style.height = `${height}px`
        div.style.borderRadius = ''
        div.style.clipPath = `polygon(${clip})`
        div.style.transform = `translate(-50%, -50%) rotate(${angle}deg)`
        div.style.transformOrigin = 'center center'
        div.innerHTML = `<img src="${currentImageUrl}" alt="" style="display:block;width:100%;height:100%;object-fit:cover;" />`
      }

      onRemove() {
        this.div?.remove()
        this.div = null
      }
    }

    const overlay = new FillOverlay()
    overlay.setMap(map)
    overlayRef.current = overlay

    return () => {
      overlay.setMap(null)
      overlayRef.current = null
    }
  }, [map, points.length > 0])

  useEffect(() => {
    overlayRef.current?.draw()
  }, [points, radiusMeters, imageUrl, opacity, rotation, zIndex])

  return null
}
