import { useEffect, useMemo, useState } from 'react'
import { MapContainer, Marker, TileLayer, useMap, useMapEvents } from 'react-leaflet'
import L from 'leaflet'
import TextInput from '@/components/forms/TextInput'
import { ValidationError } from '@/components/forms/TextInput'
import { useLocale } from '@/hooks/useLocale'
import { wrapperClassName } from '@/lib/formFieldStyles'
import 'leaflet/dist/leaflet.css'

const mapMarkerIcon = L.divIcon({
  className: 'map-picker-marker',
  html: `
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 36" width="28" height="36" aria-hidden="true">
      <path d="M12 0C5.373 0 0 5.373 0 12c0 9 12 24 12 24s12-15 12-24C24 5.373 18.627 0 12 0z" />
      <circle fill="#ffffff" cx="12" cy="12" r="4.5" />
    </svg>
  `,
  iconSize: [28, 36],
  iconAnchor: [14, 36],
})

type MapPickerProps = {
  label: string
  latitude: string
  longitude: string
  onLatitudeChange: (value: string) => void
  onLongitudeChange: (value: string) => void
  onCoordinatesChange?: (latitude: string, longitude: string) => void
  error?: string
  latitudeError?: string
  longitudeError?: string
  'data-form-field-latitude'?: string
  'data-form-field-longitude'?: string
}

function toNumber(value: string): number | null {
  const trimmed = value.trim()
  if (trimmed === '') return null

  const parsed = Number(trimmed)
  if (!Number.isFinite(parsed)) return null

  return parsed
}

function getPoint(lat: number | null, lng: number | null): [number, number] | null {
  if (lat === null || lng === null || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
    return null
  }

  return [lat, lng]
}

function MapClickHandler({
  onPick,
}: {
  onPick: (latitude: number, longitude: number) => void
}) {
  useMapEvents({
    click(event) {
      onPick(event.latlng.lat, event.latlng.lng)
    },
  })

  return null
}

function MapResize() {
  const map = useMap()

  useEffect(() => {
    const id = window.setTimeout(() => map.invalidateSize(), 100)

    return () => window.clearTimeout(id)
  }, [map])

  return null
}

function MapRecenter({ center, zoom }: { center: [number, number]; zoom: number }) {
  const map = useMap()

  useEffect(() => {
    map.setView(center, zoom, { animate: false })
    const id = window.setTimeout(() => map.invalidateSize(), 50)

    return () => window.clearTimeout(id)
  }, [center, map, zoom])

  return null
}

export default function MapPicker({
  label,
  latitude,
  longitude,
  onLatitudeChange,
  onLongitudeChange,
  onCoordinatesChange,
  error,
  latitudeError,
  longitudeError,
  'data-form-field-latitude': dataFormFieldLatitude,
  'data-form-field-longitude': dataFormFieldLongitude,
}: MapPickerProps) {
  const { locale, t } = useLocale()
  const [mounted, setMounted] = useState(false)
  const resolvedLatitudeError = latitudeError ?? error
  const resolvedLongitudeError = longitudeError ?? error
  const mapError = error ?? latitudeError ?? longitudeError

  useEffect(() => {
    setMounted(true)
  }, [])

  const lat = toNumber(latitude)
  const lng = toNumber(longitude)
  const point = getPoint(lat, lng)
  const center = useMemo<[number, number]>(
    () => point ?? [30.0444, 31.2357],
    [point],
  )
  const zoom = point ? 13 : 5

  function formatCoordinate(value: number): string {
    return value.toFixed(6)
  }

  return (
    <div className={wrapperClassName(mapError, 'grid gap-3')}>
      <span className="text-sm font-medium text-[var(--ink)]">{label}</span>
      <div className="grid gap-3 sm:grid-cols-2">
        <TextInput
          label={t('mapPickerLatitude')}
          name="latitude"
          type="text"
          inputMode="decimal"
          autoComplete="off"
          value={latitude}
          onChange={(event) => onLatitudeChange(event.target.value)}
          error={resolvedLatitudeError}
          data-form-field={dataFormFieldLatitude}
        />
        <TextInput
          label={t('mapPickerLongitude')}
          name="longitude"
          type="text"
          inputMode="decimal"
          autoComplete="off"
          value={longitude}
          onChange={(event) => onLongitudeChange(event.target.value)}
          error={resolvedLongitudeError}
          data-form-field={dataFormFieldLongitude}
        />
      </div>
      <div
        className={wrapperClassName(mapError, 'overflow-hidden rounded-lg border border-[var(--border)] bg-slate-100 dark:bg-slate-900')}
        style={{ minHeight: '14rem' }}
      >
        {mounted ? (
          <MapContainer
            center={center}
            zoom={zoom}
            className="z-0 h-56 w-full"
            style={{ height: '14rem', width: '100%' }}
            scrollWheelZoom
          >
            <TileLayer
              attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
              url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />
            <MapResize />
            <MapRecenter center={center} zoom={zoom} />
            <MapClickHandler
              onPick={(nextLat, nextLng) => {
                const nextLatitude = formatCoordinate(nextLat)
                const nextLongitude = formatCoordinate(nextLng)

                if (onCoordinatesChange) {
                  onCoordinatesChange(nextLatitude, nextLongitude)
                  return
                }

                onLatitudeChange(nextLatitude)
                onLongitudeChange(nextLongitude)
              }}
            />
            {point && <Marker key={`${point[0]}-${point[1]}`} position={point} icon={mapMarkerIcon} />}
          </MapContainer>
        ) : (
          <div className="h-56 w-full animate-pulse bg-slate-200 dark:bg-slate-700" aria-hidden />
        )}
      </div>
      <p className="text-xs text-[var(--muted)]">
        {t('mapPickerHelp')}
      </p>
      {mapError ? <ValidationError message={mapError} /> : null}
    </div>
  )
}
