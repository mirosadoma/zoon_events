import VenueMapViewer from '@/components/venue-map/VenueMapViewer'
import type { MapPoint, ZoneShapeType } from '@/components/venue-map/types'
import RegistrationEventHero, { type RegistrationHeroEvent } from '@/components/registration/RegistrationEventHero'
import RegistrationPageControls from '@/components/registration/RegistrationPageControls'
import { useLocale } from '@/hooks/useLocale'

type PublicZone = {
  id: string
  name: { en: string; ar: string }
  description?: { en: string | null; ar: string | null } | null
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
  navigate_url: string | null
  lat?: number | null
  lng?: number | null
}

type PublicPath = {
  id: string
  name: { en: string; ar: string }
  coordinate_space?: 'relative' | 'geo'
  polyline_coordinates: MapPoint[]
  from_zone_id: string | null
  to_zone_id: string | null
  stroke_color: string | null
  stroke_width: number | null
  opacity: number | null
}

type Props = {
  locale: 'en' | 'ar'
  event: RegistrationHeroEvent
  venue: {
    id: string
    name: { en: string; ar: string }
    latitude?: number | null
    longitude?: number | null
  }
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
  zones: PublicZone[]
  paths?: PublicPath[]
}

export default function PublicVenueMapPage({
  locale: pageLocale,
  event,
  venue,
  map,
  zones,
  paths = [],
}: Props) {
  const { locale, t } = useLocale()
  const resolvedLocale = pageLocale || locale
  const hasCoords = (map?.map_center_lat != null && map?.map_center_lng != null)
    || (venue.latitude != null && venue.longitude != null)
  const hasShapes = zones.some((zone) => zone.shape_type && zone.polygon_coordinates?.length)
    || paths.some((path) => path.polyline_coordinates.length >= 2)
  const canShowMap = Boolean(map?.image_url || (hasCoords && (hasShapes || map)))

  return (
    <div className="registration-public-page">
      <RegistrationPageControls locale={resolvedLocale} />
      <RegistrationEventHero locale={resolvedLocale} event={event} />

      <section className="venue-map-public-section mx-auto w-full max-w-6xl px-4 py-8">
        <h1 className="mb-2 text-2xl font-semibold text-[var(--ink)]">
          {t('venueMapTitle')}
        </h1>
        <p className="mb-6 text-[var(--muted)]">
          {venue.name[resolvedLocale] || venue.name.en}
        </p>

        {canShowMap && hasCoords ? (
          <VenueMapViewer
            imageUrl={map?.image_url ?? null}
            width={map?.width ?? 1200}
            height={map?.height ?? 800}
            zones={zones}
            paths={paths}
            locale={resolvedLocale}
            navigateLabel={t('venueMapNavigate')}
            navigateHint={t('venueMapNavigateHint')}
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
            overlayRotation={map?.overlay_rotation ?? null}
          />
        ) : (
          <p className="text-[var(--muted)]">{t('venueMapPublicEmpty')}</p>
        )}
      </section>
    </div>
  )
}
