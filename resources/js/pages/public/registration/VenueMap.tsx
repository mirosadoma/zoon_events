import VenueMapViewer from '@/components/venue-map/VenueMapViewer'
import type { RelativePoint, ZoneShapeType } from '@/components/venue-map/types'
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
  polygon_coordinates: RelativePoint[] | null
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

type Props = {
  locale: 'en' | 'ar'
  event: RegistrationHeroEvent
  venue: { id: string; name: { en: string; ar: string } }
  map: {
    image_url: string | null
    width: number | null
    height: number | null
  } | null
  zones: PublicZone[]
}

export default function PublicVenueMapPage({ locale: pageLocale, event, venue, map, zones }: Props) {
  const { locale, t } = useLocale()
  const resolvedLocale = pageLocale || locale

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

        {map?.image_url ? (
          <VenueMapViewer
            imageUrl={map.image_url}
            width={map.width ?? 1200}
            height={map.height ?? 800}
            zones={zones}
            locale={resolvedLocale}
            navigateLabel={t('venueMapNavigate')}
            navigateHint={t('venueMapNavigateHint')}
          />
        ) : (
          <p className="text-[var(--muted)]">{t('venueMapPublicEmpty')}</p>
        )}
      </section>
    </div>
  )
}
