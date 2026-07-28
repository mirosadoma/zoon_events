import { useCallback, useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react'
import { GoogleMap, Marker, useJsApiLoader } from '@react-google-maps/api'
import type { Libraries } from '@react-google-maps/api'
import TextInput from '@/components/forms/TextInput'
import { ValidationError } from '@/components/forms/TextInput'
import { useLocale } from '@/hooks/useLocale'
import { wrapperClassName } from '@/lib/formFieldStyles'

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

type PlacePrediction = {
  placeId: string
  description: string
  mainText: string
  secondaryText: string
}

const DEFAULT_CENTER = { lat: 30.0444, lng: 31.2357 }
const MAP_CONTAINER_STYLE = { height: '30rem', width: '100%', minHeight: '30rem' }
const MAP_LIBRARIES: Libraries = ['places', 'geometry']
/** Wait until the user pauses typing before requesting place predictions. */
const SEARCH_DEBOUNCE_MS = 550
const SEARCH_MIN_CHARS = 1

function toNumber(value: string): number | null {
  const trimmed = value.trim()
  if (trimmed === '') return null

  const parsed = Number(trimmed)
  if (!Number.isFinite(parsed)) return null

  return parsed
}

function getPoint(lat: number | null, lng: number | null): google.maps.LatLngLiteral | null {
  if (lat === null || lng === null || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
    return null
  }

  return { lat, lng }
}

function formatCoordinate(value: number): string {
  return value.toFixed(6)
}

function applyCoordinates(
  nextLatitude: string,
  nextLongitude: string,
  onCoordinatesChange: ((latitude: string, longitude: string) => void) | undefined,
  onLatitudeChange: (value: string) => void,
  onLongitudeChange: (value: string) => void,
) {
  if (onCoordinatesChange) {
    onCoordinatesChange(nextLatitude, nextLongitude)
    return
  }

  onLatitudeChange(nextLatitude)
  onLongitudeChange(nextLongitude)
}

function MapPlaceSearch({
  map,
  onPlaceResolved,
}: {
  map: google.maps.Map | null
  onPlaceResolved: (place: {
    latitude: string
    longitude: string
    location: google.maps.LatLng
  }) => void
}) {
  const { t } = useLocale()
  const rootRef = useRef<HTMLDivElement>(null)
  const attributionRef = useRef<HTMLDivElement>(null)
  const skipSearchRef = useRef(false)
  const latestQueryRef = useRef('')
  const lastSearchedQueryRef = useRef('')
  const onPlaceResolvedRef = useRef(onPlaceResolved)
  const mapRef = useRef(map)
  const [query, setQuery] = useState('')
  const [predictions, setPredictions] = useState<PlacePrediction[]>([])
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [activeIndex, setActiveIndex] = useState(-1)
  const [searchError, setSearchError] = useState<string | null>(null)

  useEffect(() => {
    onPlaceResolvedRef.current = onPlaceResolved
  }, [onPlaceResolved])

  useEffect(() => {
    mapRef.current = map
  }, [map])

  useEffect(() => {
    function handlePointerDown(event: MouseEvent) {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false)
        setActiveIndex(-1)
      }
    }

    document.addEventListener('mousedown', handlePointerDown)
    return () => document.removeEventListener('mousedown', handlePointerDown)
  }, [])

  useEffect(() => {
    const trimmed = query.trim()
    latestQueryRef.current = trimmed

    if (skipSearchRef.current) {
      skipSearchRef.current = false
      lastSearchedQueryRef.current = trimmed
      return
    }

    if (trimmed.length < SEARCH_MIN_CHARS) {
      lastSearchedQueryRef.current = ''
      return
    }

    if (trimmed === lastSearchedQueryRef.current) {
      return
    }

    if (!window.google?.maps?.places?.AutocompleteService) {
      return
    }

    // While the user is still typing, only reset the timer — do not show loading yet.
    let cancelled = false
    const timer = window.setTimeout(() => {
      if (cancelled || latestQueryRef.current !== trimmed) {
        return
      }

      if (trimmed === lastSearchedQueryRef.current) {
        return
      }

      setLoading(true)
      setSearchError(null)

      const service = new google.maps.places.AutocompleteService()
      service.getPlacePredictions({ input: trimmed }, (results, status) => {
        if (cancelled || latestQueryRef.current !== trimmed) {
          return
        }

        setLoading(false)
        lastSearchedQueryRef.current = trimmed

        if (
          status !== google.maps.places.PlacesServiceStatus.OK
          && status !== google.maps.places.PlacesServiceStatus.ZERO_RESULTS
        ) {
          setPredictions([])
          setOpen(false)
          setSearchError(t('mapPickerSearchFailed'))
          return
        }

        const next = (results ?? []).map((prediction) => ({
          placeId: prediction.place_id,
          description: prediction.description,
          mainText: prediction.structured_formatting?.main_text || prediction.description,
          secondaryText: prediction.structured_formatting?.secondary_text || '',
        }))

        setPredictions(next)
        setOpen(next.length > 0)
        setActiveIndex(next.length > 0 ? 0 : -1)
        setSearchError(null)
      })
    }, SEARCH_DEBOUNCE_MS)

    return () => {
      cancelled = true
      window.clearTimeout(timer)
    }
    // Intentionally only react to query text — `t` is unstable across renders.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [query])

  const listOpen = open && predictions.length > 0 && query.trim().length >= SEARCH_MIN_CHARS

  const resolvePlace = useCallback((prediction: PlacePrediction) => {
    if (!window.google?.maps?.places?.PlacesService) {
      setSearchError(t('mapPickerPlacesUnavailable'))
      return
    }

    const attributionNode = attributionRef.current
    if (!attributionNode) {
      return
    }

    setLoading(true)
    setSearchError(null)

    const service = new google.maps.places.PlacesService(mapRef.current ?? attributionNode)
    service.getDetails(
      {
        placeId: prediction.placeId,
        fields: ['geometry', 'name'],
      },
      (place, status) => {
        setLoading(false)

        if (status !== google.maps.places.PlacesServiceStatus.OK || !place?.geometry?.location) {
          setSearchError(t('mapPickerSearchFailed'))
          return
        }

        const location = place.geometry.location
        const label = (place.name || prediction.mainText || prediction.description).trim()

        skipSearchRef.current = true
        lastSearchedQueryRef.current = label
        setQuery(label)
        setPredictions([])
        setOpen(false)
        setActiveIndex(-1)
        setLoading(false)

        onPlaceResolvedRef.current({
          latitude: formatCoordinate(location.lat()),
          longitude: formatCoordinate(location.lng()),
          location,
        })
      },
    )
  }, [t])

  function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (!listOpen || predictions.length === 0) {
      return
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setActiveIndex((current) => (current + 1) % predictions.length)
      return
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActiveIndex((current) => (current <= 0 ? predictions.length - 1 : current - 1))
      return
    }

    if (event.key === 'Enter' && activeIndex >= 0) {
      event.preventDefault()
      resolvePlace(predictions[activeIndex])
      return
    }

    if (event.key === 'Escape') {
      setOpen(false)
      setActiveIndex(-1)
    }
  }

  function clearSearchState() {
    setPredictions([])
    setOpen(false)
    setLoading(false)
    setSearchError(null)
    setActiveIndex(-1)
    lastSearchedQueryRef.current = ''
  }

  return (
    <div ref={rootRef} className="map-picker-search relative z-20">
      <div className="relative">
        <input
          type="text"
          role="combobox"
          aria-expanded={listOpen}
          aria-controls="map-picker-place-list"
          aria-autocomplete="list"
          aria-activedescendant={activeIndex >= 0 ? `map-picker-place-${activeIndex}` : undefined}
          value={query}
          onChange={(event) => {
            const nextValue = event.target.value
            skipSearchRef.current = false
            setQuery(nextValue)

            if (nextValue.trim().length < SEARCH_MIN_CHARS) {
              clearSearchState()
            }
          }}
          onFocus={() => {
            if (predictions.length > 0 && query.trim().length >= SEARCH_MIN_CHARS) {
              setOpen(true)
            }
          }}
          onKeyDown={handleKeyDown}
          placeholder={t('mapPickerSearchPlaceholder')}
          aria-label={t('mapPickerSearchPlaceholder')}
          className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2.5 pe-9 text-sm text-[var(--ink)] shadow-sm outline-none placeholder:text-[var(--muted)] focus:border-[var(--brand)] focus:ring-2 focus:ring-[color-mix(in_srgb,var(--brand)_25%,transparent)]"
        />
        {loading ? (
          <span
            className="pointer-events-none absolute inset-y-0 end-3 flex items-center"
            aria-hidden
          >
            <span className="h-4 w-4 animate-spin rounded-full border-2 border-[var(--muted)] border-t-transparent" />
          </span>
        ) : null}
      </div>
      <div ref={attributionRef} className="sr-only" aria-hidden />

      {listOpen ? (
        <ul
          id="map-picker-place-list"
          role="listbox"
          className="map-picker-search-results absolute inset-x-0 top-[calc(100%+0.35rem)] z-50 max-h-64 overflow-auto rounded-lg border border-[var(--border)] bg-[var(--surface)] py-1 shadow-lg"
        >
          {predictions.map((prediction, index) => {
            const active = index === activeIndex
            return (
              <li key={prediction.placeId} role="presentation">
                <button
                  id={`map-picker-place-${index}`}
                  type="button"
                  role="option"
                  aria-selected={active}
                  className={`flex w-full flex-col gap-0.5 px-3 py-2 text-start text-sm transition ${
                    active
                      ? 'bg-[color-mix(in_srgb,var(--brand)_14%,transparent)] text-[var(--ink)]'
                      : 'text-[var(--ink)] hover:bg-[color-mix(in_srgb,var(--brand)_8%,transparent)]'
                  }`}
                  onMouseEnter={() => setActiveIndex(index)}
                  onMouseDown={(event) => event.preventDefault()}
                  onClick={() => resolvePlace(prediction)}
                >
                  <span className="font-medium">{prediction.mainText}</span>
                  {prediction.secondaryText ? (
                    <span className="text-xs text-[var(--muted)]">{prediction.secondaryText}</span>
                  ) : null}
                </button>
              </li>
            )
          })}
        </ul>
      ) : null}

      {searchError ? (
        <p className="mt-1 text-xs text-[var(--danger)]" role="status">{searchError}</p>
      ) : null}
    </div>
  )
}

function GoogleMapCanvas({
  apiKey,
  latitude,
  longitude,
  onLatitudeChange,
  onLongitudeChange,
  onCoordinatesChange,
}: {
  apiKey: string
  latitude: string
  longitude: string
  onLatitudeChange: (value: string) => void
  onLongitudeChange: (value: string) => void
  onCoordinatesChange?: (latitude: string, longitude: string) => void
}) {
  const { locale, t } = useLocale()
  const [map, setMap] = useState<google.maps.Map | null>(null)
  const { isLoaded, loadError } = useJsApiLoader({
    id: 'zoon-google-maps',
    googleMapsApiKey: apiKey,
    libraries: MAP_LIBRARIES,
    language: locale,
  })

  const lat = toNumber(latitude)
  const lng = toNumber(longitude)
  const point = getPoint(lat, lng)
  const center = useMemo(() => point ?? DEFAULT_CENTER, [point])
  const zoom = point ? 15 : 5

  const handleMapClick = useCallback((event: google.maps.MapMouseEvent) => {
    const nextLat = event.latLng?.lat()
    const nextLng = event.latLng?.lng()

    if (nextLat === undefined || nextLng === undefined) {
      return
    }

    applyCoordinates(
      formatCoordinate(nextLat),
      formatCoordinate(nextLng),
      onCoordinatesChange,
      onLatitudeChange,
      onLongitudeChange,
    )
  }, [onCoordinatesChange, onLatitudeChange, onLongitudeChange])

  const handleMarkerDragEnd = useCallback((event: google.maps.MapMouseEvent) => {
    const nextLat = event.latLng?.lat()
    const nextLng = event.latLng?.lng()

    if (nextLat === undefined || nextLng === undefined) {
      return
    }

    applyCoordinates(
      formatCoordinate(nextLat),
      formatCoordinate(nextLng),
      onCoordinatesChange,
      onLatitudeChange,
      onLongitudeChange,
    )
  }, [onCoordinatesChange, onLatitudeChange, onLongitudeChange])

  const handlePlaceResolved = useCallback((place: {
    latitude: string
    longitude: string
    location: google.maps.LatLng
  }) => {
    applyCoordinates(
      place.latitude,
      place.longitude,
      onCoordinatesChange,
      onLatitudeChange,
      onLongitudeChange,
    )

    map?.panTo(place.location)
    map?.setZoom(16)
  }, [map, onCoordinatesChange, onLatitudeChange, onLongitudeChange])

  if (loadError) {
    return (
      <div className="flex min-h-[30rem] items-center justify-center px-4 text-center text-sm text-[var(--danger)]">
        {t('mapPickerLoadFailed')}
      </div>
    )
  }

  if (!isLoaded) {
    return <div className="min-h-[30rem] w-full animate-pulse bg-slate-200 dark:bg-slate-700" aria-hidden />
  }

  return (
    <div className="grid gap-3">
      <MapPlaceSearch map={map} onPlaceResolved={handlePlaceResolved} />

      <div className="overflow-hidden rounded-lg">
        <GoogleMap
          mapContainerClassName="z-0 min-h-[30rem] w-full"
          mapContainerStyle={MAP_CONTAINER_STYLE}
          center={center}
          zoom={zoom}
          onLoad={setMap}
          onClick={handleMapClick}
          options={{
            mapTypeControl: true,
            streetViewControl: false,
            fullscreenControl: false,
            clickableIcons: false,
          }}
        >
          {point ? (
            <Marker
              position={point}
              draggable
              onDragEnd={handleMarkerDragEnd}
            />
          ) : null}
        </GoogleMap>
      </div>
    </div>
  )
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
  const { t } = useLocale()
  const apiKey = ((import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined) ?? '')
    .trim()
    .replace(/^["']|["']$/g, '')
  const resolvedLatitudeError = latitudeError ?? error
  const resolvedLongitudeError = longitudeError ?? error
  const mapError = error ?? latitudeError ?? longitudeError

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
        className={wrapperClassName(mapError, 'overflow-visible rounded-lg border border-[var(--border)] bg-slate-100 p-3 dark:bg-slate-900')}
        style={{ minHeight: '30rem' }}
      >
        {apiKey === '' ? (
          <div className="flex min-h-[30rem] items-center justify-center px-4 text-center text-sm text-[var(--muted)]">
            {t('mapPickerMissingApiKey')}
          </div>
        ) : (
          <GoogleMapCanvas
            apiKey={apiKey}
            latitude={latitude}
            longitude={longitude}
            onLatitudeChange={onLatitudeChange}
            onLongitudeChange={onLongitudeChange}
            onCoordinatesChange={onCoordinatesChange}
          />
        )}
      </div>
      <p className="text-xs text-[var(--muted)]">
        {t('mapPickerHelp')}
      </p>
      {mapError ? <ValidationError message={mapError} /> : null}
    </div>
  )
}
