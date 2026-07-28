import { Circle, GoogleMap, Polygon, Polyline, useJsApiLoader } from '@react-google-maps/api'
import type { Libraries } from '@react-google-maps/api'
import { Fragment, useMemo, useState } from 'react'
import {
  boundsFromCamera,
  isGeoPoint,
  isRelativePoint,
  relativePointsToGeo,
  relativeRadiusToMeters,
  type GeoPoint,
  type OverlayBounds,
} from '@/components/venue-map/geoCoordinates'
import { routeToDestination, type RoutingPath, type RoutingZone } from '@/components/venue-map/indoorRouting'
import MapHoverTooltip from '@/components/venue-map/MapHoverTooltip'
import RotatableFloorOverlay from '@/components/venue-map/RotatableFloorOverlay'
import GeoMarkerOverlay, { isGeoMarkerShape } from '@/components/venue-map/GeoMarkerOverlay'
import ZoneFillImageOverlay from '@/components/venue-map/ZoneFillImageOverlay'
import { defaultFillForType, type MapPoint } from '@/components/venue-map/types'
import { useLocale } from '@/hooks/useLocale'

type PublicZone = RoutingZone & {
  name: { en: string; ar: string }
  description?: { en: string | null; ar: string | null } | null
  label: string | null
  shape_rotation?: number | null
  shape_radius_y?: number | null
  fill_color: string | null
  fill_image_url?: string | null
  stroke_color: string | null
  opacity: number | null
  stroke_width: number | null
  navigate_url: string | null
  lat?: number | null
  lng?: number | null
}

type PublicPath = RoutingPath & {
  name: { en: string; ar: string }
  from_zone_id: string | null
  to_zone_id: string | null
  stroke_color: string | null
  stroke_width: number | null
  opacity: number | null
}

type Props = {
  imageUrl: string | null
  width: number
  height: number
  zones: PublicZone[]
  paths?: PublicPath[]
  locale: 'en' | 'ar'
  navigateLabel: string
  navigateHint?: string
  overlayOpacity?: number
  removeBackground?: boolean
  showBaseMap?: boolean
  venueLatitude?: number | null
  venueLongitude?: number | null
  mapCenterLat?: number | null
  mapCenterLng?: number | null
  mapZoom?: number | null
  mapHeading?: number | null
  mapType?: string | null
  overlayNorth?: number | null
  overlaySouth?: number | null
  overlayEast?: number | null
  overlayWest?: number | null
  overlayRotation?: number | null
}

const MAP_LIBRARIES: Libraries = ['places', 'geometry']

function asGeoPoints(points: MapPoint[] | null | undefined, bounds: OverlayBounds | null): GeoPoint[] {
  if (!points?.length) return []
  if (points.every(isGeoPoint)) {
    return points.map((point) => ({ lat: Number(point.lat), lng: Number(point.lng) }))
  }
  if (bounds && points.every(isRelativePoint)) {
    return relativePointsToGeo(points, bounds)
  }
  return []
}

function resolveRadiusMeters(
  zone: PublicZone,
  bounds: OverlayBounds | null,
): number {
  const radius = zone.shape_radius ?? 8
  if (zone.coordinate_space === 'geo' || (zone.polygon_coordinates?.some(isGeoPoint))) {
    return Math.max(1, radius)
  }
  if (bounds) {
    return relativeRadiusToMeters(radius, bounds)
  }
  return Math.max(1, radius)
}

function zoneDisplayName(zone: PublicZone, locale: 'en' | 'ar'): string {
  return locale === 'ar'
    ? (zone.name.ar || zone.name.en || zone.label || '').trim()
    : (zone.name.en || zone.name.ar || zone.label || '').trim()
}

export default function VenueMapViewer({
  imageUrl,
  width,
  height,
  zones,
  paths = [],
  locale,
  navigateLabel,
  navigateHint,
  overlayOpacity = 0.85,
  removeBackground = false,
  venueLatitude = null,
  venueLongitude = null,
  mapCenterLat = null,
  mapCenterLng = null,
  mapZoom = null,
  mapHeading = null,
  mapType = null,
  overlayNorth = null,
  overlaySouth = null,
  overlayEast = null,
  overlayWest = null,
  overlayRotation = 0,
}: Props) {
  const { t } = useLocale()
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [hoverTip, setHoverTip] = useState<{ name: string; position: GeoPoint; zoneId?: string } | null>(null)
  const [mapInstance, setMapInstance] = useState<google.maps.Map | null>(null)
  const [currentLocation, setCurrentLocation] = useState<GeoPoint | null>(null)
  const [locationError, setLocationError] = useState<string | null>(null)
  const [startZoneId, setStartZoneId] = useState<string>('')
  const [endZoneId, setEndZoneId] = useState<string>('')
  const [activeRoute, setActiveRoute] = useState<ReturnType<typeof routeToDestination> | null>(null)
  const [routeError, setRouteError] = useState<string | null>(null)

  const apiKey = ((import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined) ?? '')
    .trim()
    .replace(/^["']|["']$/g, '')

  const { isLoaded, loadError } = useJsApiLoader({
    id: 'zoon-google-maps',
    googleMapsApiKey: apiKey,
    libraries: MAP_LIBRARIES,
    language: locale,
  })

  const centerLat = mapCenterLat ?? venueLatitude
  const centerLng = mapCenterLng ?? venueLongitude
  const zoom = Number(mapZoom ?? 18)
  const heading = Number(mapHeading ?? 0)
  const typedMapType = mapType === 'roadmap' || mapType === 'satellite' || mapType === 'hybrid'
    ? mapType
    : 'hybrid'
  const mapCenter = useMemo(() => ({ lat: centerLat ?? 0, lng: centerLng ?? 0 }), [centerLat, centerLng])

  const overlayBounds = useMemo((): OverlayBounds | null => {
    if (
      overlayNorth != null
      && overlaySouth != null
      && overlayEast != null
      && overlayWest != null
    ) {
      return {
        north: Number(overlayNorth),
        south: Number(overlaySouth),
        east: Number(overlayEast),
        west: Number(overlayWest),
      }
    }
    if (centerLat != null && centerLng != null) {
      const aspect = width > 0 && height > 0 ? width / height : 1.6
      return boundsFromCamera(centerLat, centerLng, zoom, aspect)
    }
    return null
  }, [
    overlayNorth,
    overlaySouth,
    overlayEast,
    overlayWest,
    centerLat,
    centerLng,
    zoom,
    width,
    height,
  ])

  const selected = zones.find((zone) => zone.id === selectedId) ?? null
  const endZone = zones.find((zone) => zone.id === endZoneId) ?? null
  const endZoneName = endZone ? zoneDisplayName(endZone, locale) : null
  const zoneOptions = useMemo(
    () => zones
      .filter((zone) => zone.shape_type)
      .map((zone) => ({
        value: zone.id,
        label: zoneDisplayName(zone, locale),
      })),
    [zones, locale],
  )

  function requestCurrentLocation() {
    if (!('geolocation' in navigator)) {
      setLocationError(t('venueMapRouteGeoUnsupported'))
      return
    }
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const point = { lat: position.coords.latitude, lng: position.coords.longitude }
        setCurrentLocation(point)
        setLocationError(null)
        setRouteError(null)
        if (mapInstance) {
          mapInstance.panTo(point)
        }
      },
      () => {
        setLocationError(t('venueMapRouteGeoDenied'))
      },
      {
        enableHighAccuracy: true,
        maximumAge: 15_000,
        timeout: 12_000,
      },
    )
  }

  function drawRoute() {
    setRouteError(null)
    if (!endZoneId) {
      setActiveRoute(null)
      setRouteError(t('venueMapRoutePickDestination'))
      return
    }

    if (!startZoneId && !currentLocation) {
      setActiveRoute(null)
      setRouteError(t('venueMapRouteNeedLocationOrStart'))
      return
    }

    const nextRoute = routeToDestination({
      origin: startZoneId ? null : currentLocation,
      startZoneId: startZoneId || null,
      destinationZoneId: endZoneId,
      zones,
      paths,
      overlayBounds,
    })

    if (!nextRoute) {
      setActiveRoute(null)
      setRouteError(t('venueMapRouteNoPath'))
      return
    }

    setActiveRoute(nextRoute)
  }

  const destinationNavigateUrl = useMemo(
    () => {
      if (!endZoneId) return null
      const zone = zones.find((candidate) => candidate.id === endZoneId)
      if (!zone) return null
      if (zone.navigate_url) return zone.navigate_url
      if (zone.lat != null && zone.lng != null) {
        return `https://www.google.com/maps/dir/?api=1&destination=${zone.lat},${zone.lng}`
      }
      return null
    },
    [endZoneId, zones],
  )

  const routeNotice = useMemo(() => {
    if (!endZoneId) return t('venueMapRoutePickDestination')
    if (!activeRoute && routeError) return routeError
    if (!activeRoute) return t('venueMapRoutePressDraw')
    if (activeRoute.usedGateZoneId) {
      const gateZone = zones.find((zone) => zone.id === activeRoute.usedGateZoneId)
      const gateName = gateZone ? zoneDisplayName(gateZone, locale) : t('eventZoneType_gate')
      return t('venueMapRouteViaGate').replace(':gate', gateName)
    }
    return t('venueMapRouteReady')
  }, [activeRoute, endZoneId, locale, routeError, t, zones])

  if (centerLat == null || centerLng == null) {
    return <p className="text-[var(--muted)]">{t('venueMapPublicEmpty')}</p>
  }

  if (apiKey === '') {
    return <div className="venue-map-geo-canvas venue-map-geo-canvas--empty">{t('mapPickerMissingApiKey')}</div>
  }
  if (loadError) {
    return <div className="venue-map-geo-canvas venue-map-geo-canvas--empty">{t('venueMapBaseMapError')}</div>
  }
  if (!isLoaded) {
    return <div className="venue-map-geo-canvas venue-map-geo-canvas--empty" aria-hidden />
  }

  return (
    <div className="venue-map-viewer">
      <div className="venue-map-viewer__controls">
        <button type="button" className="button-secondary" onClick={requestCurrentLocation}>
          {t('venueMapRouteUseMyLocation')}
        </button>
        <label>
          <span>{t('venueMapRouteStart')}</span>
          <select
            className="venue-map-viewer__select"
            value={startZoneId}
            onChange={(event) => {
              setStartZoneId(event.target.value)
              setActiveRoute(null)
              setRouteError(null)
            }}
          >
            <option value="">{t('venueMapRouteCurrentLocation')}</option>
            {zoneOptions.map((zone) => (
              <option key={zone.value} value={zone.value}>{zone.label}</option>
            ))}
          </select>
        </label>
        <label>
          <span>{t('venueMapRouteEnd')}</span>
          <select
            className="venue-map-viewer__select"
            value={endZoneId}
            onChange={(event) => {
              setEndZoneId(event.target.value)
              setSelectedId(event.target.value || null)
              setActiveRoute(null)
              setRouteError(null)
            }}
          >
            <option value="">{t('venueMapRouteSelectDestination')}</option>
            {zoneOptions.map((zone) => (
              <option key={zone.value} value={zone.value}>{zone.label}</option>
            ))}
          </select>
        </label>
        <button
          type="button"
          className="button-primary"
          onClick={drawRoute}
        >
          {t('venueMapRouteDraw')}
        </button>
        <button
          type="button"
          className="button-secondary"
          onClick={() => {
            setEndZoneId('')
            setSelectedId(null)
            setActiveRoute(null)
            setRouteError(null)
          }}
        >
          {t('venueMapRouteClear')}
        </button>
        <a
          className="button-secondary"
          href={destinationNavigateUrl ?? '#'}
          target="_blank"
          rel="noreferrer"
          onClick={(event) => {
            if (!destinationNavigateUrl) {
              event.preventDefault()
            }
          }}
        >
          {navigateLabel}
        </a>
      </div>

      <div className="venue-map-geo-canvas venue-map-viewer__canvas">
        <GoogleMap
          mapContainerClassName="venue-map-geo-canvas__map"
          center={mapCenter}
          zoom={zoom}
          options={{
            disableDefaultUI: false,
            gestureHandling: 'greedy',
            clickableIcons: false,
            mapTypeControl: true,
            rotateControl: true,
            streetViewControl: false,
            fullscreenControl: true,
            mapTypeId: typedMapType,
            heading,
          }}
          onLoad={(map) => setMapInstance(map)}
          onClick={() => {
            setSelectedId(null)
          }}
        >
          {mapInstance && imageUrl && overlayBounds ? (
            <RotatableFloorOverlay
              map={mapInstance}
              imageUrl={imageUrl}
              bounds={overlayBounds}
              opacity={overlayOpacity}
              rotation={Number(overlayRotation ?? 0)}
              removeBackground={removeBackground}
              draggable={false}
              selected={false}
            />
          ) : null}

          {zones.map((zone) => {
            const points = asGeoPoints(zone.polygon_coordinates, overlayBounds)
            if (!zone.shape_type || points.length === 0) return null
            const isSelected = zone.id === selectedId || zone.id === endZoneId
            const fill = zone.fill_color ?? defaultFillForType(zone.type)
            const stroke = zone.stroke_color ?? '#111827'
            const hasFillImage = Boolean(zone.fill_image_url)
            const baseOpacity = (zone.opacity ?? 45) / 100
            const emphasized = hoverTip?.zoneId === zone.id
            const opacity = emphasized ? 1 : baseOpacity
            const tipName = zoneDisplayName(zone, locale)
            const common = {
              onClick: (event: google.maps.MapMouseEvent) => {
                event.stop()
                setSelectedId(zone.id)
                setEndZoneId(zone.id)
              },
              onMouseOver: (event: google.maps.MapMouseEvent) => {
                if (!tipName || !event.latLng) return
                setHoverTip({
                  name: tipName,
                  position: { lat: event.latLng.lat(), lng: event.latLng.lng() },
                  zoneId: zone.id,
                })
              },
              onMouseMove: (event: google.maps.MapMouseEvent) => {
                if (!tipName || !event.latLng) return
                setHoverTip({
                  name: tipName,
                  position: { lat: event.latLng.lat(), lng: event.latLng.lng() },
                  zoneId: zone.id,
                })
              },
              onMouseOut: () => setHoverTip(null),
            }
            const fillImage = mapInstance && hasFillImage && zone.fill_image_url ? (
              <ZoneFillImageOverlay
                map={mapInstance}
                points={points}
                radiusMeters={
                  zone.shape_type === 'circle'
                    || zone.shape_type === 'ellipse'
                    || isGeoMarkerShape(zone.shape_type)
                    ? resolveRadiusMeters(zone, overlayBounds)
                    : null
                }
                imageUrl={zone.fill_image_url}
                opacity={opacity}
                rotation={Number(zone.shape_rotation ?? 0)}
                zIndex={isSelected ? 7 : 2}
              />
            ) : null

            if (zone.shape_type === 'circle' || zone.shape_type === 'ellipse') {
              return (
                <Fragment key={zone.id}>
                  <Circle
                    center={points[0]}
                    radius={resolveRadiusMeters(zone, overlayBounds)}
                    options={{
                      fillColor: fill,
                      fillOpacity: hasFillImage ? 0.05 : opacity,
                      strokeColor: stroke,
                      strokeWeight: isSelected ? 4 : (zone.stroke_width ?? 2),
                      clickable: true,
                      zIndex: isSelected ? 8 : 3,
                    }}
                    {...common}
                  />
                  {fillImage}
                </Fragment>
              )
            }

            if (isGeoMarkerShape(zone.shape_type)) {
              const radius = resolveRadiusMeters(zone, overlayBounds)
              return (
                <Fragment key={zone.id}>
                  <Circle
                    center={points[0]}
                    radius={radius}
                    options={{
                      fillColor: fill,
                      fillOpacity: isSelected ? 0.12 : 0.02,
                      strokeColor: stroke,
                      strokeOpacity: isSelected ? 0.7 : 0.2,
                      strokeWeight: isSelected ? 2 : 1,
                      clickable: true,
                      zIndex: isSelected ? 8 : 3,
                    }}
                    {...common}
                  />
                  {fillImage}
                  {mapInstance ? (
                    <GeoMarkerOverlay
                      map={mapInstance}
                      center={points[0]}
                      radiusMeters={radius}
                      shapeType={zone.shape_type}
                      rotation={Number(zone.shape_rotation ?? 0)}
                      fill={fill}
                      stroke={stroke}
                      opacity={opacity}
                      strokeWidth={zone.stroke_width ?? 2}
                      selected={isSelected}
                    />
                  ) : null}
                </Fragment>
              )
            }

            return (
              <Fragment key={zone.id}>
                <Polygon
                  paths={points}
                  options={{
                    fillColor: fill,
                    fillOpacity: hasFillImage ? 0.05 : opacity,
                    strokeColor: stroke,
                    strokeWeight: isSelected ? 4 : (zone.stroke_width ?? 2),
                    clickable: true,
                    zIndex: isSelected ? 8 : 3,
                  }}
                  {...common}
                />
                {fillImage}
              </Fragment>
            )
          })}

          {currentLocation ? (
            <Circle
              center={currentLocation}
              radius={5}
              options={{
                fillColor: '#0ea5e9',
                fillOpacity: 0.9,
                strokeColor: '#ffffff',
                strokeWeight: 2,
                clickable: false,
                zIndex: 9,
              }}
            />
          ) : null}

          {activeRoute?.approachRoute.length === 2 ? (
            <Polyline
              path={activeRoute.approachRoute}
              options={{
                strokeColor: '#64748b',
                strokeOpacity: 0.85,
                strokeWeight: 3,
                clickable: false,
                zIndex: 9,
                icons: [{
                  icon: {
                    path: 'M 0,-1 0,1',
                    strokeOpacity: 1,
                    scale: 3,
                  },
                  offset: '0',
                  repeat: '12px',
                }],
              }}
            />
          ) : null}

          {activeRoute?.indoorRoute.length ? (
            <>
              <Polyline
                path={activeRoute.indoorRoute}
                options={{
                  strokeColor: '#0f766e',
                  strokeOpacity: 0.9,
                  strokeWeight: 5,
                  clickable: false,
                  zIndex: 10,
                }}
              />
              <Polyline
                path={activeRoute.indoorRoute}
                options={{
                  strokeColor: '#ffffff',
                  strokeOpacity: 1,
                  strokeWeight: 2,
                  clickable: false,
                  zIndex: 11,
                  icons: [{
                    icon: {
                      path: google.maps.SymbolPath.CIRCLE,
                      fillOpacity: 1,
                      fillColor: '#ffffff',
                      strokeOpacity: 1,
                      strokeColor: '#0f766e',
                      scale: 3.5,
                    },
                    offset: '0',
                    repeat: '28px',
                  }],
                }}
              />
            </>
          ) : null}

          {mapInstance && hoverTip ? (
            <MapHoverTooltip
              map={mapInstance}
              position={hoverTip.position}
              label={hoverTip.name}
            />
          ) : null}
        </GoogleMap>
      </div>

      <div className="venue-map-viewer__panel">
        <h2>{endZoneName || t('venueMapRouteTitle')}</h2>
        <p className="text-sm text-[var(--muted)]">{routeNotice}</p>
        {activeRoute ? (
          <p className="text-sm">
            {t('venueMapRouteDistance').replace(':distance', `${Math.round(activeRoute.distanceMeters)}m`)}
          </p>
        ) : null}
        {locationError ? (
          <p className="text-sm text-[var(--danger)]">{locationError}</p>
        ) : null}
        {selected?.navigate_url ? (
          <a className="button-primary" href={selected.navigate_url} target="_blank" rel="noreferrer">
            {navigateLabel}
          </a>
        ) : navigateHint ? (
          <p className="text-sm text-[var(--muted)]">{navigateHint}</p>
        ) : null}
      </div>
    </div>
  )
}
