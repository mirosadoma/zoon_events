import { GoogleMap, useJsApiLoader } from '@react-google-maps/api'
import type { Libraries } from '@react-google-maps/api'
import { useCallback, useEffect, useMemo, useRef } from 'react'
import { useLocale } from '@/hooks/useLocale'

export type VenueBaseMapType = 'roadmap' | 'satellite' | 'hybrid'

type Props = {
  latitude: number
  longitude: number
  zoom?: number
  heading?: number
  mapTypeId?: VenueBaseMapType
  interactive?: boolean
  onMapTypeIdChange?: (mapTypeId: VenueBaseMapType) => void
  onHeadingChange?: (heading: number) => void
}

const MAP_LIBRARIES: Libraries = ['places', 'geometry']

function isVenueBaseMapType(value: string): value is VenueBaseMapType {
  return value === 'roadmap' || value === 'satellite' || value === 'hybrid'
}

function normalizeHeading(value: number): number {
  const wrapped = ((value % 360) + 360) % 360
  return Math.round(wrapped * 10) / 10
}

export default function VenueMapBaseLayer({
  latitude,
  longitude,
  zoom = 18,
  heading = 0,
  mapTypeId = 'hybrid',
  interactive = true,
  onMapTypeIdChange,
  onHeadingChange,
}: Props) {
  const { locale, t } = useLocale()
  const mapRef = useRef<google.maps.Map | null>(null)
  const apiKey = ((import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined) ?? '')
    .trim()
    .replace(/^["']|["']$/g, '')
  const mapId = ((import.meta.env.VITE_GOOGLE_MAPS_MAP_ID as string | undefined) ?? '')
    .trim()
    .replace(/^["']|["']$/g, '')
  const useVectorHeading = mapId !== ''
  const normalizedHeading = normalizeHeading(heading)

  const { isLoaded, loadError } = useJsApiLoader({
    id: 'zoon-google-maps',
    googleMapsApiKey: apiKey,
    libraries: MAP_LIBRARIES,
    language: locale,
  })

  const center = useMemo(
    () => ({ lat: latitude, lng: longitude }),
    [latitude, longitude],
  )

  const options = useMemo((): google.maps.MapOptions | undefined => {
    // google.maps is only available after the JS API finishes loading.
    if (!isLoaded || typeof google === 'undefined') {
      return undefined
    }

    const next: google.maps.MapOptions = {
      disableDefaultUI: false,
      keyboardShortcuts: interactive,
      gestureHandling: interactive ? 'greedy' : 'none',
      clickableIcons: false,
      draggable: interactive,
      scrollwheel: interactive,
      zoomControl: interactive,
      mapTypeControl: interactive,
      mapTypeControlOptions: interactive
        ? {
            style: google.maps.MapTypeControlStyle.DROPDOWN_MENU,
            mapTypeIds: [
              google.maps.MapTypeId.ROADMAP,
              google.maps.MapTypeId.SATELLITE,
              google.maps.MapTypeId.HYBRID,
            ],
            position: google.maps.ControlPosition.TOP_RIGHT,
          }
        : undefined,
      rotateControl: useVectorHeading && interactive,
      rotateControlOptions: useVectorHeading && interactive
        ? { position: google.maps.ControlPosition.LEFT_BOTTOM }
        : undefined,
      tilt: 0,
      streetViewControl: false,
      fullscreenControl: false,
      mapTypeId,
    }

    if (useVectorHeading) {
      next.mapId = mapId
      if (google.maps.RenderingType?.VECTOR !== undefined) {
        next.renderingType = google.maps.RenderingType.VECTOR
      }
      next.heading = normalizedHeading
      next.headingInteractionEnabled = interactive
    }

    return next
  }, [interactive, isLoaded, mapTypeId, normalizedHeading, mapId, useVectorHeading])

  const applyCamera = useCallback((map: google.maps.Map) => {
    map.panTo({ lat: latitude, lng: longitude })
    map.setZoom(zoom)
    map.setTilt(0)
    if (useVectorHeading) {
      try {
        map.setHeading(normalizedHeading)
      } catch {
        // Ignore heading errors on unsupported map configs.
      }
    }
  }, [latitude, longitude, zoom, normalizedHeading, useVectorHeading])

  const handleLoad = useCallback((map: google.maps.Map) => {
    mapRef.current = map
    applyCamera(map)
  }, [applyCamera])

  useEffect(() => {
    const map = mapRef.current
    if (!map) return
    applyCamera(map)
  }, [applyCamera])

  const handleMapTypeIdChanged = useCallback(() => {
    if (!onMapTypeIdChange || !mapRef.current) return
    const next = mapRef.current.getMapTypeId()
    if (typeof next === 'string' && isVenueBaseMapType(next)) {
      onMapTypeIdChange(next)
    }
  }, [onMapTypeIdChange])

  const handleHeadingChanged = useCallback(() => {
    if (!useVectorHeading || !onHeadingChange || !mapRef.current) return
    onHeadingChange(normalizeHeading(mapRef.current.getHeading() || 0))
  }, [onHeadingChange, useVectorHeading])

  if (apiKey === '') {
    return (
      <div className="venue-map-base-layer venue-map-base-layer--fallback">
        <p>{t('mapPickerMissingApiKey')}</p>
      </div>
    )
  }

  if (loadError) {
    return (
      <div className="venue-map-base-layer venue-map-base-layer--fallback">
        <p>{t('venueMapBaseMapError')}</p>
      </div>
    )
  }

  if (!isLoaded) {
    return <div className="venue-map-base-layer venue-map-base-layer--loading" aria-hidden />
  }

  if (!options) {
    return <div className="venue-map-base-layer venue-map-base-layer--loading" aria-hidden />
  }

  return (
    <div
      className={`venue-map-base-layer${interactive ? ' venue-map-base-layer--interactive' : ''}`}
      aria-hidden={!interactive}
    >
      <div
        className="venue-map-base-layer__rotate"
        style={useVectorHeading || normalizedHeading === 0
          ? undefined
          : {
              transform: `rotate(${normalizedHeading}deg)`,
              transformOrigin: 'center center',
            }}
      >
        <GoogleMap
          mapContainerClassName="venue-map-base-layer__map"
          center={center}
          zoom={zoom}
          options={options}
          onLoad={handleLoad}
          onMapTypeIdChanged={handleMapTypeIdChanged}
          onHeadingChanged={handleHeadingChanged}
        />
      </div>
    </div>
  )
}
