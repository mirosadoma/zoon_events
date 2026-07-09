import { useEffect, useMemo } from 'react'
import { MapContainer, Marker, TileLayer, useMapEvents } from 'react-leaflet'
import L from 'leaflet'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
import TextInput from '@/components/forms/TextInput'
import 'leaflet/dist/leaflet.css'

type MapPickerProps = {
  label: string
  latitude: string
  longitude: string
  onLatitudeChange: (value: string) => void
  onLongitudeChange: (value: string) => void
  error?: string
}

const defaultIcon = L.icon({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
  iconSize: [25, 41],
  iconAnchor: [12, 41],
})

function toNumber(value: string): number | null {
  const parsed = Number(value)
  if (Number.isNaN(parsed)) return null

  return parsed
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

export default function MapPicker({
  label,
  latitude,
  longitude,
  onLatitudeChange,
  onLongitudeChange,
  error,
}: MapPickerProps) {
  useEffect(() => {
    L.Marker.prototype.options.icon = defaultIcon
  }, [])

  const lat = toNumber(latitude)
  const lng = toNumber(longitude)
  const hasPoint = lat !== null && lng !== null && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180
  const center = useMemo<[number, number]>(
    () => (hasPoint ? [lat, lng] : [30.0444, 31.2357]),
    [hasPoint, lat, lng],
  )

  return (
    <div className="grid gap-3">
      <span className="text-sm font-medium text-[var(--ink)]">{label}</span>
      <div className="grid gap-3 md:grid-cols-2">
        <TextInput
          label="Latitude"
          name="latitude"
          type="number"
          step="any"
          value={latitude}
          onChange={(event) => onLatitudeChange(event.target.value)}
        />
        <TextInput
          label="Longitude"
          name="longitude"
          type="number"
          step="any"
          value={longitude}
          onChange={(event) => onLongitudeChange(event.target.value)}
        />
      </div>
      <div className="overflow-hidden rounded-lg border border-[var(--border)]">
        <MapContainer
          center={center}
          zoom={hasPoint ? 13 : 5}
          className="h-56 w-full"
          scrollWheelZoom
        >
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />
          <MapClickHandler
            onPick={(nextLat, nextLng) => {
              onLatitudeChange(nextLat.toFixed(6))
              onLongitudeChange(nextLng.toFixed(6))
            }}
          />
          {hasPoint && <Marker position={[lat, lng]} />}
        </MapContainer>
      </div>
      <p className="text-xs text-[var(--muted)]">
        Click on the map to set coordinates, or enter latitude and longitude manually.
      </p>
      {error && <span role="alert" className="text-red-600 dark:text-red-400">{error}</span>}
    </div>
  )
}
