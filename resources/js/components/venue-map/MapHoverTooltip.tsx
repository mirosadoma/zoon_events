import { useEffect, useRef } from 'react'
import type { GeoPoint } from '@/components/venue-map/geoCoordinates'

type Props = {
  map: google.maps.Map
  position: GeoPoint
  label: string
  detail?: string | null
}

/** Lightweight name tooltip above the map, follows hover position. */
export default function MapHoverTooltip({ map, position, label, detail = null }: Props) {
  const overlayRef = useRef<google.maps.OverlayView | null>(null)
  const elRef = useRef<HTMLDivElement | null>(null)
  const positionRef = useRef(position)
  const labelRef = useRef(label)
  const detailRef = useRef(detail)
  positionRef.current = position
  labelRef.current = label
  detailRef.current = detail

  function renderContent(el: HTMLDivElement) {
    el.replaceChildren()
    const title = document.createElement('div')
    title.className = 'venue-map-hover-tooltip__title'
    title.textContent = labelRef.current
    el.appendChild(title)

    if (detailRef.current) {
      const meta = document.createElement('div')
      meta.className = 'venue-map-hover-tooltip__detail'
      meta.textContent = detailRef.current
      el.appendChild(meta)
    }
  }

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
        renderContent(el)
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
    if (elRef.current) renderContent(elRef.current)
    overlayRef.current?.draw()
  }, [position.lat, position.lng, label, detail])

  return null
}
