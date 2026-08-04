import LocalizedLink from '@/components/routing/LocalizedLink'
import { useEffect, useMemo, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import VenueMapViewer from '@/components/venue-map/VenueMapViewer'
import type { MapPoint, ZoneShapeType } from '@/components/venue-map/types'
import { PageContent, PageHeader } from '@/components/layout'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import { CHECK_IN_SUMMARY_POLL_INTERVAL_MS } from '@/lib/checkin-polling'

type EventRow = { id: string; name: { en: string; ar: string } }

type ZoneOccupancy = {
  event_zone_id: string
  scanner_code: string | null
  name: { en: string; ar: string }
  inside_count: number
  capacity: number | null
  utilization: number | null
  level: string
  coverage: string
  last_scan_at: string | null
}

type OccupancySummary = {
  zones: ZoneOccupancy[]
  totals: { inside: number; capacity: number | null; tracked_zones: number }
  generated_at: string
}

type Analytics = {
  range: string
  hourly: Array<{ hour: string; zone_id: string; entries: number }>
  peaks: Array<{ event_zone_id: string; peak_inside: number }>
  generated_at: string
}

type MapZone = {
  id: string
  name: { en: string; ar: string }
  label: string | null
  type: string
  shape_type: ZoneShapeType | null
  coordinate_space?: 'relative' | 'geo'
  polygon_coordinates: MapPoint[] | null
  shape_radius: number | null
  shape_rotation?: number | null
  shape_radius_y?: number | null
  fill_color: string | null
  stroke_color: string | null
  opacity: number | null
  stroke_width: number | null
  navigate_url?: string | null
  lat?: number | null
  lng?: number | null
}

type Props = {
  event: EventRow
  tenantId: string
  venue: {
    id: string
    name: { en: string; ar: string }
    latitude?: number | null
    longitude?: number | null
  } | null
  map: {
    image_url: string | null
    width: number | null
    height: number | null
    overlay_opacity?: number
    remove_background?: boolean
    show_base_map?: boolean
    map_center_lat?: number | null
    map_center_lng?: number | null
    map_zoom?: number | null
    map_heading?: number | null
    map_type?: string | null
    overlay_north?: number | null
    overlay_south?: number | null
    overlay_east?: number | null
    overlay_west?: number | null
    overlay_rotation?: number | null
  } | null
  zones: MapZone[]
  initialSummary: OccupancySummary
  initialAnalytics: Analytics
}

const HEAT: Record<string, string> = {
  empty: '#22c55e',
  low: '#84cc16',
  medium: '#eab308',
  high: '#f97316',
  critical: '#ef4444',
  tracked: '#3b82f6',
}

export default function OccupancyMapPage({
  event,
  tenantId,
  venue,
  map,
  zones,
  initialSummary,
  initialAnalytics,
}: Props) {
  const { locale, t } = useLocale()
  const [summary, setSummary] = useState(initialSummary)
  const [analytics, setAnalytics] = useState(initialAnalytics)
  const [stale, setStale] = useState(false)

  useEffect(() => {
    let active = true

    async function poll() {
      try {
        const [summaryRes, analyticsRes] = await Promise.all([
          fetch(`/api/v1/tenant/events/${event.id}/zone-occupancy/summary`, {
            credentials: 'include',
            headers: { Accept: 'application/json', 'X-Tenant-ID': tenantId },
          }),
          fetch(`/api/v1/tenant/events/${event.id}/zone-occupancy/analytics`, {
            credentials: 'include',
            headers: { Accept: 'application/json', 'X-Tenant-ID': tenantId },
          }),
        ])
        if (!active) return
        if (summaryRes.ok) {
          const body = await summaryRes.json()
          setSummary(body.data as OccupancySummary)
          setStale(false)
        } else {
          setStale(true)
        }
        if (analyticsRes.ok) {
          const body = await analyticsRes.json()
          setAnalytics(body.data as Analytics)
        }
      } catch {
        if (active) setStale(true)
      }
    }

    void poll()
    const timer = window.setInterval(() => void poll(), CHECK_IN_SUMMARY_POLL_INTERVAL_MS)
    return () => {
      active = false
      window.clearInterval(timer)
    }
  }, [event.id, tenantId])

  const occupancyById = useMemo(() => {
    const mapById: Record<string, ZoneOccupancy> = {}
    for (const zone of summary.zones) {
      mapById[zone.event_zone_id] = zone
    }
    return mapById
  }, [summary.zones])

  const peakById = useMemo(() => {
    const mapById: Record<string, number> = {}
    for (const peak of analytics.peaks) {
      mapById[peak.event_zone_id] = peak.peak_inside
    }
    return mapById
  }, [analytics.peaks])

  const heatZones = useMemo(() => zones.map((zone) => {
    const occ = occupancyById[zone.id]
    const level = occ?.level ?? 'empty'
    const count = occ?.inside_count ?? 0
    const capacity = occ?.capacity
    const labelBase = locale === 'ar'
      ? (zone.name.ar || zone.name.en || zone.label || '')
      : (zone.name.en || zone.name.ar || zone.label || '')
    const countLabel = capacity != null ? `${count}/${capacity}` : String(count)
    const capacityLabel = capacity != null ? String(capacity) : '—'

    return {
      ...zone,
      fill_color: HEAT[level] ?? HEAT.tracked,
      opacity: 55,
      label: `${labelBase} · ${countLabel}`,
      hover_detail: `${t('occupancyMapInside')}: ${count} · ${t('occupancyMapCapacity')}: ${capacityLabel}`,
      navigate_url: occ?.scanner_code
        ? `/tenant/events/${event.id}/scanner?code=${occ.scanner_code}`
        : null,
    }
  }), [event.id, locale, occupancyById, t, zones])

  const hasCoords = (map?.map_center_lat != null && map?.map_center_lng != null)
    || (venue?.latitude != null && venue?.longitude != null)

  return (
    <DashboardLayout title={t('occupancyMapTitle')}>
      <PageHeader
        title={t('occupancyMapTitle')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('occupancyMapTitle') },
        ]}
        actions={(
          <div className="flex flex-wrap gap-2">
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/scanner`}>
              {t('scannerPageScanner')}
            </LocalizedLink>
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/check-in-dashboard`}>
              {t('checkInDashboard')}
            </LocalizedLink>
          </div>
        )}
      />
      <PageContent>
        {stale ? (
          <p role="alert" className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {t('occupancyMapStale')}
          </p>
        ) : null}

        <div className="mb-4 grid gap-3 sm:grid-cols-3">
          <div className="ta-card ta-stat-card">
            <p className="ta-stat-label">{t('occupancyMapInside')}</p>
            <p className="ta-stat-value">{summary.totals.inside}</p>
          </div>
          <div className="ta-card ta-stat-card">
            <p className="ta-stat-label">{t('occupancyMapCapacity')}</p>
            <p className="ta-stat-value">{summary.totals.capacity ?? '—'}</p>
          </div>
          <div className="ta-card ta-stat-card">
            <p className="ta-stat-label">{t('occupancyMapZones')}</p>
            <p className="ta-stat-value">{summary.totals.tracked_zones}</p>
          </div>
        </div>

        <div className="mb-4 flex flex-wrap gap-3 text-xs text-[var(--muted)]">
          {Object.entries(HEAT).filter(([key]) => key !== 'tracked').map(([key, color]) => (
            <span key={key} className="inline-flex items-center gap-2">
              <span className="inline-block h-3 w-3 rounded-sm" style={{ background: color }} />
              {t(`occupancyLevel_${key}`)}
            </span>
          ))}
        </div>

        {venue && hasCoords ? (
          <div className="ta-card overflow-hidden p-0">
            <VenueMapViewer
              imageUrl={map?.image_url ?? null}
              width={map?.width ?? 1200}
              height={map?.height ?? 800}
              zones={heatZones}
              locale={locale}
              navigateLabel={t('scannerPageScanner')}
              navigateHint={t('occupancyMapOpenScanner')}
              enableRouting={false}
              overlayOpacity={map?.overlay_opacity ?? 0.85}
              removeBackground={Boolean(map?.remove_background)}
              showBaseMap={map?.show_base_map !== false}
              venueLatitude={venue.latitude ?? null}
              venueLongitude={venue.longitude ?? null}
              mapCenterLat={map?.map_center_lat ?? null}
              mapCenterLng={map?.map_center_lng ?? null}
              mapZoom={map?.map_zoom ?? null}
              mapHeading={map?.map_heading ?? null}
              mapType={map?.map_type ?? null}
              overlayNorth={map?.overlay_north ?? null}
              overlaySouth={map?.overlay_south ?? null}
              overlayEast={map?.overlay_east ?? null}
              overlayWest={map?.overlay_west ?? null}
              overlayRotation={map?.overlay_rotation ?? 0}
              persistentZoneLabels
            />
          </div>
        ) : (
          <p className="ta-card text-sm text-[var(--muted)]">{t('occupancyMapNoMap')}</p>
        )}

        <section className="ta-card mt-6">
          <h2 className="mb-4 text-lg font-semibold">{t('occupancyMapNowTable')}</h2>
          <DataTable
            rows={summary.zones as Array<Record<string, unknown> & ZoneOccupancy>}
            getRowKey={(row) => row.event_zone_id}
            columns={[
              {
                key: 'name',
                header: t('name'),
                render: (row) => row.name[locale] || row.name.en,
              },
              {
                key: 'scanner_code',
                header: t('eventZoneScannerCode'),
                render: (row) => row.scanner_code ?? '—',
              },
              {
                key: 'inside_count',
                header: t('occupancyMapInside'),
                render: (row) => String(row.inside_count),
              },
              {
                key: 'capacity',
                header: t('occupancyMapCapacity'),
                render: (row) => row.capacity != null ? String(row.capacity) : '—',
              },
              {
                key: 'utilization',
                header: t('occupancyMapUtilization'),
                render: (row) => row.utilization != null ? `${Math.round(row.utilization * 100)}%` : '—',
              },
              {
                key: 'peak',
                header: t('occupancyMapPeak'),
                render: (row) => String(peakById[row.event_zone_id] ?? 0),
              },
              {
                key: 'scanner',
                header: t('scannerPageScanner'),
                render: (row) => row.scanner_code ? (
                  <LocalizedLink
                    className="text-[var(--brand)] hover:underline"
                    href={`/tenant/events/${event.id}/scanner?code=${row.scanner_code}`}
                  >
                    {t('occupancyMapOpenScanner')}
                  </LocalizedLink>
                ) : '—',
              },
            ]}
          />
        </section>
      </PageContent>
    </DashboardLayout>
  )
}
