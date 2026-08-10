import { useCallback, useEffect, useMemo, useState } from 'react'
import { GoogleMap, Marker } from '@react-google-maps/api'
import { useLocale } from '@/hooks/useLocale'
import { useGoogleMapsLoader } from '@/hooks/useGoogleMapsLoader'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { formatDateTime } from '@/lib/formatters'
import { coloredPinIcon } from '@/lib/mapMarkerColor'

export type PublishedVenueMarker = {
  event_id: string
  event_name: { en: string; ar: string }
  venue_id: string
  venue_name: { en: string; ar: string }
  latitude: number
  longitude: number
  start_at?: string | null
  end_at?: string | null
  timezone?: string | null
  address?: string | null
  color: string
}

const DEFAULT_CENTER = { lat: 30.0444, lng: 31.2357 }
const MAP_CONTAINER_STYLE = { height: '24rem', width: '100%', minHeight: '24rem' }
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

export default function PublishedVenuesMap({
  markers,
  emptyLabel,
  missingApiKeyLabel,
}: {
  markers: PublishedVenueMarker[]
  emptyLabel: string
  missingApiKeyLabel: string
}) {
  const { locale, t } = useLocale()
  const localized = useLocalizedRouter()
  const { apiKey, isLoaded, loadError } = useGoogleMapsLoader()
  const [map, setMap] = useState<google.maps.Map | null>(null)
  const [activeId, setActiveId] = useState<string | null>(null)
  const [selectedEventId, setSelectedEventId] = useState<string | null>(null)

  const legend = useMemo(() => {
    const seen = new Map<string, { event_id: string; name: string; color: string }>()
    for (const marker of markers) {
      if (seen.has(marker.event_id)) continue
      seen.set(marker.event_id, {
        event_id: marker.event_id,
        name: locale === 'ar' ? marker.event_name.ar || marker.event_name.en : marker.event_name.en || marker.event_name.ar,
        color: marker.color,
      })
    }
    return Array.from(seen.values())
  }, [markers, locale])

  const visibleMarkers = useMemo(
    () => (selectedEventId ? markers.filter((marker) => marker.event_id === selectedEventId) : markers),
    [markers, selectedEventId],
  )

  const active = visibleMarkers.find((marker) => marker.venue_id === activeId) ?? null

  const fitBounds = useCallback((nextMap: google.maps.Map, points: PublishedVenueMarker[]) => {
    if (points.length === 0) return
    if (points.length === 1) {
      nextMap.setCenter({ lat: points[0].latitude, lng: points[0].longitude })
      nextMap.setZoom(12)
      return
    }
    const bounds = new google.maps.LatLngBounds()
    for (const point of points) {
      bounds.extend({ lat: point.latitude, lng: point.longitude })
    }
    nextMap.fitBounds(bounds, 56)
  }, [])

  useEffect(() => {
    if (map && visibleMarkers.length > 0) {
      fitBounds(map, visibleMarkers)
    }
  }, [map, visibleMarkers, fitBounds])

  useEffect(() => {
    setActiveId(null)
  }, [selectedEventId])

  function toggleEventFilter(eventId: string) {
    setSelectedEventId((current) => (current === eventId ? null : eventId))
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
    return (
      <div className="h-64 animate-pulse rounded-xl border border-[var(--border)] bg-slate-100 dark:bg-slate-800" />
    )
  }

  return (
    <div className="space-y-3">
      {legend.length > 0 ? (
        <div className="flex flex-wrap items-center gap-2" role="group" aria-label={t('overviewMapFilterLabel')}>
          <button
            type="button"
            aria-pressed={selectedEventId === null}
            title={t('overviewMapShowAllEvents')}
            onClick={() => setSelectedEventId(null)}
            className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition ${
              selectedEventId === null
                ? 'border-[var(--brand)] bg-[var(--brand-soft)] text-[var(--ink)] ring-1 ring-[var(--brand)]/40'
                : 'border-[var(--border)] bg-[var(--surface)] text-[var(--muted)] hover:border-[var(--brand)]/50 hover:text-[var(--ink)]'
            }`}
          >
            {t('overviewMapAllEvents')}
          </button>
          {legend.map((item) => {
            const selected = selectedEventId === item.event_id
            const dimmed = selectedEventId !== null && !selected

            return (
              <button
                key={item.event_id}
                type="button"
                aria-pressed={selected}
                title={selected ? t('overviewMapShowAllEvents') : t('overviewMapFilterEvent')}
                onClick={() => toggleEventFilter(item.event_id)}
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
      ) : null}

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
              onClick={() => localized.visit(`/tenant/events/${marker.event_id}`)}
            />
          ))}
        </GoogleMap>

        {active ? (
          <div
            className="pointer-events-none absolute bottom-4 left-4 right-4 z-10 mx-auto max-w-md rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] p-4 shadow-[var(--card-shadow)] sm:left-auto sm:right-4"
            role="tooltip"
          >
            <p className="text-sm font-semibold text-[var(--ink)]">
              {locale === 'ar'
                ? active.event_name.ar || active.event_name.en
                : active.event_name.en || active.event_name.ar}
            </p>
            <p className="mt-1 text-sm text-[var(--muted)]">
              {locale === 'ar'
                ? active.venue_name.ar || active.venue_name.en
                : active.venue_name.en || active.venue_name.ar}
            </p>
            <p className="mt-2 text-xs text-[var(--muted)]">
              {formatRange(active.start_at, active.end_at, locale, active.timezone)}
            </p>
            {active.address ? (
              <p className="mt-1 truncate text-xs text-[var(--muted)]">{active.address}</p>
            ) : null}
            <p className="mt-3 text-xs font-medium text-[var(--brand)]">
              {t('overviewMapClickHint')}
            </p>
          </div>
        ) : null}
      </div>
    </div>
  )
}

