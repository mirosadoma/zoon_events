import { useCallback, useEffect, useMemo, useState } from 'react'
import { GoogleMap, Marker, useJsApiLoader } from '@react-google-maps/api'
import type { Libraries } from '@react-google-maps/api'
import { useLocale } from '@/hooks/useLocale'
import { formatDateTime } from '@/lib/formatters'
import { coloredPinIcon } from '@/lib/mapMarkerColor'

export type EventVenueMarker = {
  venue_id: string
  venue_name: { en: string; ar: string }
  latitude: number
  longitude: number
  start_at?: string | null
  end_at?: string | null
  timezone?: string | null
  address?: string | null
  registered: number
  checked_in: number
  checkin_rate: number | null
  color: string
}

const DEFAULT_CENTER = { lat: 30.0444, lng: 31.2357 }
const MAP_CONTAINER_STYLE = { height: '26rem', width: '100%', minHeight: '26rem' }
const MAP_LIBRARIES: Libraries = ['geometry']

function formatRange(
  start: string | null | undefined,
  end: string | null | undefined,
  locale: 'en' | 'ar',
  timeZone?: string | null,
): string {
  const fmt = (value: string) => formatDateTime(value, locale, timeZone || undefined) || value

  if (start && end) return `${fmt(start)} → ${fmt(end)}`
  if (start) return fmt(start)
  if (end) return fmt(end)
  return '—'
}

export default function EventReportVenuesMap({
  markers,
  emptyLabel,
  missingApiKeyLabel,
}: {
  markers: EventVenueMarker[]
  emptyLabel: string
  missingApiKeyLabel: string
}) {
  const { locale, t } = useLocale()
  const apiKey = ((import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined) ?? '').trim()
  const [map, setMap] = useState<google.maps.Map | null>(null)
  const [activeId, setActiveId] = useState<string | null>(null)
  const [selectedVenueId, setSelectedVenueId] = useState<string | null>(null)

  const { isLoaded, loadError } = useJsApiLoader({
    id: 'zoon-google-maps',
    googleMapsApiKey: apiKey,
    libraries: MAP_LIBRARIES,
    language: locale,
  })

  const venueChips = useMemo(
    () => markers.map((marker) => ({
      venue_id: marker.venue_id,
      name: locale === 'ar'
        ? marker.venue_name.ar || marker.venue_name.en
        : marker.venue_name.en || marker.venue_name.ar,
      color: marker.color,
    })),
    [markers, locale],
  )

  const visibleMarkers = useMemo(
    () => (selectedVenueId ? markers.filter((marker) => marker.venue_id === selectedVenueId) : markers),
    [markers, selectedVenueId],
  )

  const active = visibleMarkers.find((marker) => marker.venue_id === activeId) ?? null

  const fitBounds = useCallback((nextMap: google.maps.Map, points: EventVenueMarker[]) => {
    if (points.length === 0) return
    if (points.length === 1) {
      nextMap.setCenter({ lat: points[0].latitude, lng: points[0].longitude })
      nextMap.setZoom(13)
      return
    }
    const bounds = new google.maps.LatLngBounds()
    for (const point of points) {
      bounds.extend({ lat: point.latitude, lng: point.longitude })
    }
    nextMap.fitBounds(bounds, 56)
  }, [])

  useEffect(() => {
    if (map && visibleMarkers.length > 0) fitBounds(map, visibleMarkers)
  }, [map, visibleMarkers, fitBounds])

  useEffect(() => {
    setActiveId(null)
  }, [selectedVenueId])

  function toggleVenueFilter(venueId: string) {
    setSelectedVenueId((current) => (current === venueId ? null : venueId))
  }

  if (!apiKey) {
    return (
      <div className="flex h-64 items-center justify-center rounded-xl border border-[var(--border)] bg-[var(--surface)] text-sm text-[var(--muted)]">
        {missingApiKeyLabel}
      </div>
    )
  }

  if (markers.length === 0) {
    return (
      <div className="flex h-64 items-center justify-center rounded-xl border border-dashed border-[var(--border)] bg-[var(--surface)] text-sm text-[var(--muted)]">
        {emptyLabel}
      </div>
    )
  }

  if (loadError) {
    return (
      <div className="flex h-64 items-center justify-center rounded-xl border border-[var(--border)] bg-[var(--surface)] text-sm text-[var(--muted)]">
        {t('mapPickerLoadFailed')}
      </div>
    )
  }

  if (!isLoaded) {
    return <div className="h-64 animate-pulse rounded-xl border border-[var(--border)] bg-slate-100 dark:bg-slate-800" />
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center gap-2" role="group" aria-label={t('reportVenuesFilterLabel')}>
        <button
          type="button"
          aria-pressed={selectedVenueId === null}
          title={t('reportVenuesShowAll')}
          onClick={() => setSelectedVenueId(null)}
          className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition ${
            selectedVenueId === null
              ? 'border-[var(--brand)] bg-[var(--brand-soft)] text-[var(--ink)] ring-1 ring-[var(--brand)]/40'
              : 'border-[var(--border)] bg-[var(--surface)] text-[var(--muted)] hover:border-[var(--brand)]/50 hover:text-[var(--ink)]'
          }`}
        >
          {t('reportVenuesAll')}
        </button>
        {venueChips.map((item) => {
          const selected = selectedVenueId === item.venue_id
          const dimmed = selectedVenueId !== null && !selected

          return (
            <button
              key={item.venue_id}
              type="button"
              aria-pressed={selected}
              title={selected ? t('reportVenuesShowAll') : t('reportVenuesFilterOne')}
              onClick={() => toggleVenueFilter(item.venue_id)}
              className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition ${
                selected
                  ? 'border-[var(--brand)] bg-[var(--brand-soft)] text-[var(--ink)] ring-1 ring-[var(--brand)]/40'
                  : dimmed
                    ? 'border-[var(--border)] bg-[var(--surface)] text-[var(--muted)] opacity-55 hover:opacity-100'
                    : 'border-[var(--border)] bg-[var(--surface)] text-[var(--ink)] hover:border-[var(--brand)]/50'
              }`}
            >
              <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: item.color }} />
              {item.name}
            </button>
          )
        })}
      </div>

      <div className="relative overflow-hidden rounded-xl border border-[var(--border)]">
        <GoogleMap
          mapContainerStyle={MAP_CONTAINER_STYLE}
          center={DEFAULT_CENTER}
          zoom={5}
          onLoad={(nextMap) => {
            setMap(nextMap)
            fitBounds(nextMap, visibleMarkers)
          }}
          onUnmount={() => setMap(null)}
          onClick={() => setActiveId(null)}
          options={{
            fullscreenControl: false,
            streetViewControl: false,
            mapTypeControl: false,
            clickableIcons: false,
          }}
        >
          {visibleMarkers.map((marker) => (
            <Marker
              key={marker.venue_id}
              position={{ lat: marker.latitude, lng: marker.longitude }}
              icon={coloredPinIcon(marker.color)}
              onMouseOver={() => setActiveId(marker.venue_id)}
              onMouseOut={() => setActiveId((current) => (current === marker.venue_id ? null : current))}
              onClick={() => setActiveId(marker.venue_id)}
            />
          ))}
        </GoogleMap>

        {active ? (
          <div
            className="pointer-events-none absolute bottom-4 left-4 right-4 z-10 mx-auto max-w-md rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] p-4 shadow-[var(--card-shadow)] sm:left-auto sm:right-4"
            role="dialog"
            aria-label={locale === 'ar' ? active.venue_name.ar : active.venue_name.en}
          >
            <p className="text-base font-semibold text-[var(--ink)]">
              {locale === 'ar'
                ? active.venue_name.ar || active.venue_name.en
                : active.venue_name.en || active.venue_name.ar}
            </p>
            {active.address ? (
              <p className="mt-1 text-xs text-[var(--muted)]">{active.address}</p>
            ) : null}
            <p className="mt-2 text-xs text-[var(--muted)]">
              {formatRange(active.start_at, active.end_at, locale, active.timezone)}
            </p>
            <dl className="mt-3 grid grid-cols-3 gap-2 text-center">
              <div className="rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-2">
                <dt className="text-[10px] uppercase tracking-wide text-[var(--muted)]">{t('reportRegistrations')}</dt>
                <dd className="mt-1 text-lg font-semibold tabular-nums text-[var(--ink)]">{active.registered}</dd>
              </div>
              <div className="rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-2">
                <dt className="text-[10px] uppercase tracking-wide text-[var(--muted)]">{t('reportCheckedInAttendees')}</dt>
                <dd className="mt-1 text-lg font-semibold tabular-nums text-[var(--ink)]">{active.checked_in}</dd>
              </div>
              <div className="rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-2">
                <dt className="text-[10px] uppercase tracking-wide text-[var(--muted)]">{t('reportCheckinRate')}</dt>
                <dd className="mt-1 text-lg font-semibold tabular-nums text-[var(--ink)]">
                  {active.checkin_rate === null ? '—' : `${active.checkin_rate}%`}
                </dd>
              </div>
            </dl>
          </div>
        ) : null}
      </div>
    </div>
  )
}
