import { useState } from 'react'
import { Copy, ExternalLink } from 'lucide-react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import VenueMapEditor from '@/components/venue-map/VenueMapEditor'
import type { MapZone, VenueMapData } from '@/components/venue-map/types'
import { PageContent, PageHeader } from '@/components/layout'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'

type Props = {
  event: {
    id: string
    slug?: string
    name: { en: string; ar: string }
  }
  tenantId: string
  venue: {
    id: string
    name: { en: string; ar: string }
    latitude: number | null
    longitude: number | null
  }
  publicMapPath?: string | null
  map: VenueMapData | null
  zones: MapZone[]
  paths?: Array<Record<string, unknown>>
  zoneTypes: string[]
  shapeTypes: string[]
}

export default function VenueMapPage({
  event,
  tenantId,
  venue,
  publicMapPath: publicMapPathProp,
  map,
  zones,
  paths = [],
  zoneTypes,
}: Props) {
  const { locale, t, localizedPath } = useLocale()
  const { toast } = useToast()
  const [copied, setCopied] = useState(false)
  const venuesHref = `/tenant/events/${event.id}/venues`
  const relativePublicPath = publicMapPathProp
    ?? (event.slug ? `/events/${event.slug}/venues/${venue.id}/map` : null)
  const publicMapPath = relativePublicPath ? localizedPath(relativePublicPath) : null
  const publicMapUrl = publicMapPath
    ? `${typeof window !== 'undefined' ? window.location.origin : ''}${publicMapPath}`
    : null

  async function copyPublicLink() {
    if (!publicMapUrl) return
    try {
      await navigator.clipboard.writeText(publicMapUrl)
      setCopied(true)
      toast(t('venueMapLinkCopied'), 'success')
      window.setTimeout(() => setCopied(false), 2000)
    } catch {
      toast(t('requestFailed'), 'error')
    }
  }

  return (
    <DashboardLayout title={t('venueMapTitle')}>
      <PageHeader
        title={t('venueMapTitle')}
        description={t('venueMapHint')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('overviewEvents'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('eventVenues'), href: venuesHref },
          { label: venue.name[locale] || venue.name.en, href: `/tenant/events/${event.id}/venues/${venue.id}/edit` },
          { label: t('venueMapTitle') },
        ]}
        actions={(
          <div className="flex flex-wrap gap-2">
            {publicMapPath ? (
              <a className="button-secondary" href={publicMapPath} target="_blank" rel="noreferrer">
                <ExternalLink size={16} />
                {t('venueMapOpenPublic')}
              </a>
            ) : null}
            <LocalizedLink className="button-secondary" href={venuesHref}>
              {t('back')}
            </LocalizedLink>
          </div>
        )}
      />

      <PageContent>
        {publicMapUrl ? (
          <div className="venue-map-public-share mb-4 rounded-lg border border-[var(--border)] bg-[var(--surface)] p-4">
            <p className="font-semibold text-[var(--ink)]">{t('venueMapPublicLink')}</p>
            <p className="mt-1 text-sm text-[var(--muted)]">{t('venueMapPublicLinkHint')}</p>
            <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
              <code className="min-w-0 flex-1 break-all rounded-md border border-[var(--border)] bg-[color-mix(in_srgb,var(--surface)_88%,black)] px-3 py-2 text-sm text-[var(--brand)]">
                {publicMapUrl}
              </code>
              <button type="button" className="button-primary inline-flex items-center justify-center gap-2" onClick={() => void copyPublicLink()}>
                <Copy size={16} />
                {copied ? t('venueMapLinkCopied') : t('venueMapCopyLink')}
              </button>
            </div>
          </div>
        ) : null}

        <VenueMapEditor
          eventId={event.id}
          tenantId={tenantId}
          venueId={venue.id}
          venueLatitude={venue.latitude}
          venueLongitude={venue.longitude}
          initialMap={map}
          initialZones={zones.map((zone) => ({
            ...zone,
            key: zone.id ?? crypto.randomUUID(),
          }))}
          initialPaths={paths}
          zoneTypes={zoneTypes}
        />
      </PageContent>
    </DashboardLayout>
  )
}
