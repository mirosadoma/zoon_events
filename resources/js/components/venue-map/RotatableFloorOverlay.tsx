import { useEffect, useRef } from 'react'
import type { OverlayBounds } from '@/components/venue-map/geoCoordinates'
import { resolveFloorPlanImageSrc } from '@/components/venue-map/resolveFloorPlanImageSrc'

type Corner = 'nw' | 'ne' | 'sw' | 'se'

type Props = {
  map: google.maps.Map
  imageUrl: string
  bounds: OverlayBounds
  opacity: number
  rotation: number
  removeBackground?: boolean
  draggable?: boolean
  selected?: boolean
  onSelect?: () => void
  onBoundsChange?: (bounds: OverlayBounds) => void
  onRotationChange?: (degrees: number) => void
}

type PixelBox = {
  left: number
  top: number
  width: number
  height: number
  cx: number
  cy: number
}

function normalizeDegrees(value: number): number {
  return ((value % 360) + 360) % 360
}

function clampBounds(bounds: OverlayBounds): OverlayBounds {
  const north = Math.max(bounds.north, bounds.south)
  const south = Math.min(bounds.north, bounds.south)
  const east = Math.max(bounds.east, bounds.west)
  const west = Math.min(bounds.east, bounds.west)
  const minSpan = 0.00005
  return {
    north: north === south ? north + minSpan / 2 : north,
    south: north === south ? south - minSpan / 2 : south,
    east: east === west ? east + minSpan / 2 : east,
    west: east === west ? west - minSpan / 2 : west,
  }
}

function positionBox(el: HTMLElement, left: number, top: number, width: number, height: number) {
  el.style.left = `${left}px`
  el.style.top = `${top}px`
  el.style.width = `${width}px`
  el.style.height = `${height}px`
}

function localCorner(corner: Corner, halfW: number, halfH: number): { x: number; y: number } {
  switch (corner) {
    case 'nw': return { x: -halfW, y: -halfH }
    case 'ne': return { x: halfW, y: -halfH }
    case 'sw': return { x: -halfW, y: halfH }
    case 'se': return { x: halfW, y: halfH }
  }
}

function oppositeCorner(corner: Corner): Corner {
  switch (corner) {
    case 'nw': return 'se'
    case 'ne': return 'sw'
    case 'sw': return 'ne'
    case 'se': return 'nw'
  }
}

function rotateLocal(x: number, y: number, degrees: number): { x: number; y: number } {
  const rad = (degrees * Math.PI) / 180
  const cos = Math.cos(rad)
  const sin = Math.sin(rad)
  return {
    x: x * cos - y * sin,
    y: x * sin + y * cos,
  }
}

/**
 * Floor-plan image anchored to lat/lng via OverlayView projection.
 * Bounds change only on explicit user drag/resize — map pan/zoom never retargets them.
 * Hit/drag is enabled only while selected so map gestures work over the image otherwise.
 */
export default function RotatableFloorOverlay({
  map,
  imageUrl,
  bounds,
  opacity,
  rotation,
  removeBackground = false,
  draggable = false,
  selected = false,
  onSelect,
  onBoundsChange,
  onRotationChange,
}: Props) {
  const overlayRef = useRef<google.maps.OverlayView | null>(null)
  const boundsRef = useRef(bounds)
  const rotationRef = useRef(rotation)
  const draggableRef = useRef(draggable)
  const selectedRef = useRef(selected)
  const opacityRef = useRef(opacity)
  const onSelectRef = useRef(onSelect)
  const onBoundsChangeRef = useRef(onBoundsChange)
  const onRotationChangeRef = useRef(onRotationChange)
  const interactingRef = useRef(false)

  if (!interactingRef.current) {
    boundsRef.current = bounds
    rotationRef.current = rotation
  }
  draggableRef.current = draggable
  selectedRef.current = selected
  opacityRef.current = opacity
  onSelectRef.current = onSelect
  onBoundsChangeRef.current = onBoundsChange
  onRotationChangeRef.current = onRotationChange

  useEffect(() => {
    const visual = document.createElement('div')
    visual.className = 'venue-map-floor-overlay venue-map-floor-overlay--visual'

    const imageWrap = document.createElement('div')
    imageWrap.className = 'venue-map-floor-overlay__image-wrap'
    const image = document.createElement('img')
    image.className = 'venue-map-floor-overlay__image'
    image.alt = ''
    image.draggable = false
    imageWrap.appendChild(image)
    visual.appendChild(imageWrap)

    const hit = document.createElement('div')
    hit.className = 'venue-map-floor-overlay venue-map-floor-overlay__hit'

    const controls = document.createElement('div')
    controls.className = 'venue-map-floor-overlay venue-map-floor-overlay--controls'

    const corners: Corner[] = ['nw', 'ne', 'sw', 'se']
    const resizeEls = new Map<Corner, HTMLButtonElement>()
    const rotateEls = new Map<Corner, HTMLButtonElement>()

    let cancelled = false

    function unlockMap() {
      map.setOptions({ draggable: true, gestureHandling: 'greedy' })
    }

    function pixelToLatLng(x: number, y: number): google.maps.LatLng | null {
      return overlayRef.current?.getProjection()?.fromDivPixelToLatLng(new google.maps.Point(x, y)) ?? null
    }

    function latLngToPixel(lat: number, lng: number): google.maps.Point | null {
      return overlayRef.current?.getProjection()?.fromLatLngToDivPixel(new google.maps.LatLng(lat, lng)) ?? null
    }

    function commitBounds(next: OverlayBounds) {
      const clamped = clampBounds(next)
      boundsRef.current = clamped
      onBoundsChangeRef.current?.(clamped)
      overlayRef.current?.draw()
    }

    function positionHandles(box: PixelBox, degrees: number) {
      const halfW = box.width / 2
      const halfH = box.height / 2
      for (const corner of corners) {
        const local = localCorner(corner, halfW, halfH)
        const rotated = rotateLocal(local.x, local.y, degrees)
        const px = box.cx - box.left + rotated.x
        const py = box.cy - box.top + rotated.y
        const resize = resizeEls.get(corner)
        if (resize) {
          resize.style.left = `${px}px`
          resize.style.top = `${py}px`
        }
        const len = Math.hypot(rotated.x, rotated.y) || 1
        const rotateBtn = rotateEls.get(corner)
        if (rotateBtn) {
          rotateBtn.style.left = `${px + (rotated.x / len) * 26}px`
          rotateBtn.style.top = `${py + (rotated.y / len) * 26}px`
        }
      }
    }

    hit.addEventListener('pointerdown', (event) => {
      if (!draggableRef.current) return
      event.preventDefault()
      event.stopPropagation()

      if (!selectedRef.current) {
        onSelectRef.current?.()
        return
      }

      if (!onBoundsChangeRef.current) return

      interactingRef.current = true
      map.setOptions({ draggable: false, gestureHandling: 'none' })

      const startX = event.clientX
      const startY = event.clientY
      const start = { ...boundsRef.current }
      const sw = latLngToPixel(start.south, start.west)
      const ne = latLngToPixel(start.north, start.east)
      if (!sw || !ne) {
        interactingRef.current = false
        unlockMap()
        return
      }

      let moved = false

      function onMove(moveEvent: PointerEvent) {
        const dx = moveEvent.clientX - startX
        const dy = moveEvent.clientY - startY
        if (!moved && Math.hypot(dx, dy) < 4) return
        moved = true
        const nextSw = pixelToLatLng(sw!.x + dx, sw!.y + dy)
        const nextNe = pixelToLatLng(ne!.x + dx, ne!.y + dy)
        if (!nextSw || !nextNe) return
        boundsRef.current = clampBounds({
          north: nextNe.lat(),
          south: nextSw.lat(),
          east: nextNe.lng(),
          west: nextSw.lng(),
        })
        overlayRef.current?.draw()
      }

      function onUp() {
        window.removeEventListener('pointermove', onMove)
        window.removeEventListener('pointerup', onUp)
        unlockMap()
        interactingRef.current = false
        if (moved) commitBounds(boundsRef.current)
      }

      window.addEventListener('pointermove', onMove)
      window.addEventListener('pointerup', onUp)
    })

    for (const corner of corners) {
      const resize = document.createElement('button')
      resize.type = 'button'
      resize.className = `venue-map-floor-overlay__resize venue-map-floor-overlay__resize--${corner}`
      resizeEls.set(corner, resize)
      controls.appendChild(resize)

      resize.addEventListener('pointerdown', (event) => {
        if (!draggableRef.current || !selectedRef.current || !onBoundsChangeRef.current) return
        event.preventDefault()
        event.stopPropagation()
        interactingRef.current = true
        map.setOptions({ draggable: false, gestureHandling: 'none' })

        const start = { ...boundsRef.current }
        const sw = latLngToPixel(start.south, start.west)
        const ne = latLngToPixel(start.north, start.east)
        if (!sw || !ne) {
          interactingRef.current = false
          unlockMap()
          return
        }
        const left = Math.min(sw.x, ne.x)
        const top = Math.min(sw.y, ne.y)
        const width = Math.abs(ne.x - sw.x)
        const height = Math.abs(sw.y - ne.y)
        const cx = left + width / 2
        const cy = top + height / 2
        const rot = rotationRef.current
        const fixedLocal = localCorner(oppositeCorner(corner), width / 2, height / 2)

        function onMove(moveEvent: PointerEvent) {
          const projection = overlayRef.current?.getProjection()
          if (!projection) return
          const mapRect = map.getDiv().getBoundingClientRect()
          const latLng = projection.fromContainerPixelToLatLng(
            new google.maps.Point(moveEvent.clientX - mapRect.left, moveEvent.clientY - mapRect.top),
          )
          if (!latLng) return
          const divPt = projection.fromLatLngToDivPixel(latLng)
          if (!divPt) return
          const inv = rotateLocal(divPt.x - cx, divPt.y - cy, -rot)
          const localLeft = Math.min(inv.x, fixedLocal.x)
          const localRight = Math.max(inv.x, fixedLocal.x)
          const localTop = Math.min(inv.y, fixedLocal.y)
          const localBottom = Math.max(inv.y, fixedLocal.y)
          const nextW = Math.max(24, localRight - localLeft)
          const nextH = Math.max(24, localBottom - localTop)
          const mid = rotateLocal((localLeft + localRight) / 2, (localTop + localBottom) / 2, rot)
          const nextCx = cx + mid.x
          const nextCy = cy + mid.y
          const nextSw = pixelToLatLng(nextCx - nextW / 2, nextCy + nextH / 2)
          const nextNe = pixelToLatLng(nextCx + nextW / 2, nextCy - nextH / 2)
          if (!nextSw || !nextNe) return
          boundsRef.current = clampBounds({
            north: nextNe.lat(),
            south: nextSw.lat(),
            east: nextNe.lng(),
            west: nextSw.lng(),
          })
          overlayRef.current?.draw()
        }

        function onUp() {
          window.removeEventListener('pointermove', onMove)
          window.removeEventListener('pointerup', onUp)
          unlockMap()
          interactingRef.current = false
          commitBounds(boundsRef.current)
        }

        window.addEventListener('pointermove', onMove)
        window.addEventListener('pointerup', onUp)
      })

      const rotateBtn = document.createElement('button')
      rotateBtn.type = 'button'
      rotateBtn.className = 'venue-map-floor-overlay__rotate is-hidden'
      rotateEls.set(corner, rotateBtn)
      controls.appendChild(rotateBtn)

      rotateBtn.addEventListener('pointerdown', (event) => {
        if (!draggableRef.current || !selectedRef.current || !onRotationChangeRef.current) return
        event.preventDefault()
        event.stopPropagation()
        interactingRef.current = true
        map.setOptions({ draggable: false, gestureHandling: 'none' })
        const rect = visual.getBoundingClientRect()
        const pivotX = rect.left + rect.width / 2
        const pivotY = rect.top + rect.height / 2
        const startAngle = Math.atan2(event.clientY - pivotY, event.clientX - pivotX)
        const startRotation = rotationRef.current

        function onMove(moveEvent: PointerEvent) {
          const angle = Math.atan2(moveEvent.clientY - pivotY, moveEvent.clientX - pivotX)
          const next = normalizeDegrees(startRotation + ((angle - startAngle) * 180) / Math.PI)
          rotationRef.current = next
          onRotationChangeRef.current?.(next)
          overlayRef.current?.draw()
        }

        function onUp() {
          window.removeEventListener('pointermove', onMove)
          window.removeEventListener('pointerup', onUp)
          unlockMap()
          interactingRef.current = false
        }

        window.addEventListener('pointermove', onMove)
        window.addEventListener('pointerup', onUp)
      })
    }

    const onDocMove = (event: PointerEvent) => {
      if (!selectedRef.current || interactingRef.current) return
      let nearest: Corner | null = null
      let nearestDist = Number.POSITIVE_INFINITY
      for (const [corner, button] of resizeEls) {
        const rect = button.getBoundingClientRect()
        const dist = Math.hypot(event.clientX - (rect.left + rect.width / 2), event.clientY - (rect.top + rect.height / 2))
        if (dist < nearestDist) {
          nearestDist = dist
          nearest = corner
        }
      }
      const show = nearestDist <= 36 ? nearest : null
      for (const [corner, button] of rotateEls) {
        button.classList.toggle('is-hidden', corner !== show)
      }
    }
    window.addEventListener('pointermove', onDocMove)

    class FloorChrome extends google.maps.OverlayView {
      onAdd() {
        const panes = this.getPanes()
        // mapPane moves with the map (geo-fixed). Mouse targets sit above for handles/hit.
        panes?.mapPane.appendChild(visual)
        panes?.overlayMouseTarget.appendChild(controls)
        // Hit must receive events; keep it in the mouse-target pane as a sibling layer.
        panes?.overlayMouseTarget.appendChild(hit)
      }

      draw() {
        const projection = this.getProjection()
        if (!projection) return
        const current = boundsRef.current
        const sw = projection.fromLatLngToDivPixel(new google.maps.LatLng(current.south, current.west))
        const ne = projection.fromLatLngToDivPixel(new google.maps.LatLng(current.north, current.east))
        if (!sw || !ne) return

        const left = Math.min(sw.x, ne.x)
        const top = Math.min(sw.y, ne.y)
        const width = Math.max(1, Math.abs(ne.x - sw.x))
        const height = Math.max(1, Math.abs(sw.y - ne.y))
        const box: PixelBox = {
          left,
          top,
          width,
          height,
          cx: left + width / 2,
          cy: top + height / 2,
        }

        positionBox(visual, left, top, width, height)
        positionBox(controls, left, top, width, height)
        positionBox(hit, left, top, width, height)

        const editable = Boolean(selectedRef.current && draggableRef.current)
        const degrees = rotationRef.current

        imageWrap.style.transform = `rotate(${degrees}deg)`
        imageWrap.style.opacity = String(Math.min(1, Math.max(0.1, opacityRef.current)))
        imageWrap.classList.toggle('is-editable', editable)

        controls.style.display = editable ? 'block' : 'none'
        controls.style.pointerEvents = editable ? 'auto' : 'none'

        hit.style.transform = `rotate(${degrees}deg)`
        hit.style.cursor = editable ? 'grab' : (draggableRef.current ? 'pointer' : 'default')
        // Allow click-to-select always in select tool; drag only after selected.
        // When not editable, keep hit clickable for selection but do not capture pans:
        // pointer-events auto + no drag handler start unless selected means map still pans
        // if we don't preventDefault on non-select... We only stopPropagation on pointerdown
        // when draggable. For unselected, pointerdown calls onSelect and returns — that still
        // blocks the map pan start on that gesture. Prefer: unselected → pointer-events none,
        // selection via map click (GeoCanvas). Selected → pointer-events auto for drag.
        hit.style.pointerEvents = editable ? 'auto' : 'none'

        positionHandles(box, degrees)
        for (const button of resizeEls.values()) {
          button.style.display = editable ? 'block' : 'none'
        }
        if (!editable) {
          for (const button of rotateEls.values()) button.classList.add('is-hidden')
        }
      }

      onRemove() {
        visual.remove()
        controls.remove()
        hit.remove()
      }
    }

    const chrome = new FloorChrome()
    chrome.setMap(map)
    overlayRef.current = chrome

    void resolveFloorPlanImageSrc(imageUrl, removeBackground).then((src) => {
      if (cancelled) return
      image.src = src
      chrome.draw()
    })

    return () => {
      cancelled = true
      window.removeEventListener('pointermove', onDocMove)
      unlockMap()
      chrome.setMap(null)
      overlayRef.current = null
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [map, imageUrl, removeBackground])

  useEffect(() => {
    if (interactingRef.current) return
    boundsRef.current = bounds
    rotationRef.current = rotation
    opacityRef.current = opacity
    overlayRef.current?.draw()
  }, [bounds, opacity, rotation])

  useEffect(() => {
    selectedRef.current = selected
    draggableRef.current = draggable
    overlayRef.current?.draw()
  }, [selected, draggable])

  return null
}

export { normalizeDegrees as normalizeOverlayRotation }
