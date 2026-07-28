import { useEffect, useRef } from 'react'
import type { GeoPoint } from '@/components/venue-map/geoCoordinates'

type Props = {
  map: google.maps.Map
  position: GeoPoint
  label: string
}

/** Lightweight name tooltip above the map, follows hover position. */
export default function MapHoverTooltip({ map, position, label }: Props) {
  const overlayRef = useRef<google.maps.OverlayView | null>(null)
  const elRef = useRef<HTMLDivElement | null>(null)
  const positionRef = useRef(position)
  const labelRef = useRef(label)
  positionRef.current = position
  labelRef.current = label

  useEffect(() => {
    const el = document.createElement('div')
    el.className = 'venue-map-hover-tooltip'
    el.setAttribute('role', 'tooltip')
    elRef.current = el

    class TipOverlay extends google.maps.OverlayView {
      onAdd() {
        this.getPanes()?.floatPane.appendChild(el)
      }

      draw() {
        const projection = this.getProjection()
        if (!projection) return
        const point = projection.fromLatLngToDivPixel(
          new google.maps.LatLng(positionRef.current.lat, positionRef.current.lng),
        )
        if (!point) return
        el.textContent = labelRef.current
        el.style.left = `${point.x}px`
        el.style.top = `${point.y}px`
      }

      onRemove() {
        el.remove()
      }
    }

    const overlay = new TipOverlay()
    overlay.setMap(map)
    overlayRef.current = overlay

    return () => {
      overlay.setMap(null)
      overlayRef.current = null
      elRef.current = null
    }
  }, [map])

  useEffect(() => {
    if (elRef.current) elRef.current.textContent = label
    overlayRef.current?.draw()
  }, [position.lat, position.lng, label])

  return null
}
