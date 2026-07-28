import { useEffect, useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import {
  emptyVenueRow,
  venueRowsFromEvent,
  type VenueFormRow,
} from '@/components/forms/VenueRepeater'
import ConfirmModal from '@/components/modals/ConfirmModal'
import DataTable from '@/components/tables/DataTable'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import SideDetailPane, {
  SideDetailActions,
  SideDetailInfoGrid,
  sideDetailActionClassName,
} from '@/components/layout/SideDetailPane'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import { buildVenuePayload } from '@/lib/venuePayload'

export type EventZoneRow = {
  id: string
  venue_id: string
  name: { en: string; ar: string }
  zone_name_en?: string
  zone_name_ar?: string
  description_en?: string | null
  description_ar?: string | null
  type: string
  floor_type?: 'basement' | 'floor' | null
  floor_number?: number | null
  capacity: number | null
  shape_type?: string | null
  polygon_coordinates?: Array<{ x: number; y: number }> | null
  shape_radius?: number | null
  label?: string | null
  google_maps_url?: string | null
  lat?: number | null
  lng?: number | null
  fill_color?: string | null
  fill_image_url?: string | null
  stroke_color?: string | null
  opacity?: number | null
  stroke_width?: number | null
}

export type EventVenueRow = {
  id: string
  country_id: string
  city_id: string
  name: { en: string; ar: string }
  location_address?: string | null
  latitude?: string | null
  longitude?: string | null
  start_at?: string | null
  end_at?: string | null
  registration_opens_at?: string | null
  registration_closes_at?: string | null
  has_map?: boolean
  zones?: EventZoneRow[]
}

type Props = {
  event: {
    id: string
    name: { en: string; ar: string }
  }
  tenantId: string
  venues: EventVenueRow[]
}

function toFormRow(venue: EventVenueRow): VenueFormRow {
  return venueRowsFromEvent([{
    id: venue.id,
    country_id: venue.country_id,
    city_id: venue.city_id,
    name: venue.name,
    location_address: venue.location_address ?? '',
    latitude: venue.latitude ?? '',
    longitude: venue.longitude ?? '',
    start_at: venue.start_at ?? null,
    end_at: venue.end_at ?? null,
    registration_opens_at: venue.registration_opens_at ?? null,
    registration_closes_at: venue.registration_closes_at ?? null,
  }])[0] ?? emptyVenueRow()
}

export default function EventVenuesPage({
  event,
  tenantId,
  venues: initialVenues,
}: Props) {
  const { locale, t, localizedPath } = useLocale()
  const { toast } = useToast()
  const [venues, setVenues] = useState<EventVenueRow[]>(initialVenues)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [deleteId, setDeleteId] = useState<string | null>(null)
  const [deleting, setDeleting] = useState(false)

  useEffect(() => {
    setVenues(initialVenues)
  }, [initialVenues])

  const createHref = `/tenant/events/${event.id}/venues/create`

  const tableRows = useMemo(
    () => venues.map((venue) => ({
      id: venue.id,
      name: venue.name[locale] || venue.name.en || venue.name.ar,
      zones_count: venue.zones?.length ?? 0,
      address: venue.location_address?.trim() || t('notAvailable'),
      start: venue.start_at
        ? new Date(venue.start_at).toLocaleString(locale === 'ar' ? 'ar' : 'en')
        : t('notAvailable'),
      end: venue.end_at
        ? new Date(venue.end_at).toLocaleString(locale === 'ar' ? 'ar' : 'en')
        : t('notAvailable'),
    })),
    [venues, locale, t],
  )

  async function confirmDelete() {
    if (!deleteId) return

    setDeleting(true)
    try {
      const remaining = venues
        .filter((venue) => venue.id !== deleteId)
        .map((venue) => toFormRow(venue))

      const result = await apiFetch<{ venues: EventVenueRow[] }>(`/api/v1/tenant/events/${event.id}/venues`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: { venues: buildVenuePayload(remaining) },
      })

      setVenues(result.venues ?? [])
      setDeleteId(null)
      if (selectedId === deleteId) {
        setSelectedId(null)
      }
      toast(t('deleted'), 'success')
    } catch (caught) {
      toast(caught instanceof ApiFetchError ? caught.message : t('requestFailed'), 'error')
    } finally {
      setDeleting(false)
    }
  }

  const deleteTarget = venues.find((venue) => venue.id === deleteId)
  const selected = venues.find((venue) => venue.id === selectedId) ?? null
  const notAvailable = t('notAvailable')

  return (
    <DashboardLayout title={event.name[locale]}>
      <PageHeader
        title={t('eventVenues')}
        description={t('eventVenuesManageHint')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('overviewEvents'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('eventVenues') },
        ]}
        actions={(
          <div className="flex flex-wrap gap-2">
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}`}>
              {t('eventAgendaBack')}
            </LocalizedLink>
            <LocalizedLink className="button-primary" href={createHref}>
              {t('addVenue')}
            </LocalizedLink>
          </div>
        )}
      />

      <PageContent>
        {venues.length === 0 ? (
          <EmptyState
            title={t('eventVenuesEmpty')}
            detail={t('eventVenuesManageHint')}
            action={(
              <LocalizedLink className="button-primary" href={createHref}>
                {t('addVenue')}
              </LocalizedLink>
            )}
          />
        ) : (
          <DataTable
            title={t('eventVenues')}
            columns={[
              { key: 'name', header: t('name') },
              {
                key: 'zones_count',
                header: t('eventZonesTitle'),
                render: (row) => (
                  <span className="tabular-nums text-[var(--muted)]">
                    {t('eventVenueZonesCount', { count: String(row.zones_count) })}
                  </span>
                ),
              },
              { key: 'address', header: t('address') },
              { key: 'start', header: t('startAt') },
              { key: 'end', header: t('endAt') },
            ]}
            rows={tableRows}
            getRowKey={(row) => String(row.id)}
            selectedRowKey={selectedId}
            onRowClick={(row) => setSelectedId(String(row.id))}
          />
        )}
      </PageContent>

      <SideDetailPane
        open={selected !== null}
        title={selected ? (selected.name[locale] || selected.name.en || selected.name.ar) : ''}
        subtitle={selected?.location_address?.trim() || null}
        onClose={() => setSelectedId(null)}
        onEdit={selected ? () => router.visit(localizedPath(`/tenant/events/${event.id}/venues/${selected.id}/edit`)) : null}
        onDelete={selected ? () => setDeleteId(selected.id) : null}
        footer={selected ? (
          <SideDetailActions>
            <LocalizedLink
              href={`/tenant/events/${event.id}/venues/${selected.id}/map`}
              className={sideDetailActionClassName('primary')}
            >
              {t('venueMapTitle')}
            </LocalizedLink>
            <button
              type="button"
              className={sideDetailActionClassName()}
              onClick={() => router.visit(localizedPath(`/tenant/events/${event.id}/venues/${selected.id}/edit`))}
            >
              {t('edit')}
            </button>
          </SideDetailActions>
        ) : null}
      >
        {selected ? (
          <SideDetailInfoGrid
            items={[
              {
                label: t('name'),
                value: selected.name[locale] || selected.name.en || selected.name.ar,
              },
              {
                label: t('eventZonesTitle'),
                value: t('eventVenueZonesCount', { count: String(selected.zones?.length ?? 0) }),
              },
              {
                label: t('address'),
                value: selected.location_address?.trim() || notAvailable,
              },
              {
                label: t('startAt'),
                value: selected.start_at
                  ? new Date(selected.start_at).toLocaleString(locale === 'ar' ? 'ar' : 'en')
                  : notAvailable,
              },
              {
                label: t('endAt'),
                value: selected.end_at
                  ? new Date(selected.end_at).toLocaleString(locale === 'ar' ? 'ar' : 'en')
                  : notAvailable,
              },
            ]}
          />
        ) : null}
      </SideDetailPane>

      <ConfirmModal
        open={deleteId !== null}
        title={t('eventVenuesDeleteTitle')}
        message={t('eventVenuesDeleteMessage', {
          name: deleteTarget
            ? (deleteTarget.name[locale] || deleteTarget.name.en || deleteTarget.name.ar)
            : '',
        })}
        confirmLabel={t('delete')}
        cancelLabel={t('cancel')}
        loading={deleting}
        onConfirm={() => void confirmDelete()}
        onCancel={() => setDeleteId(null)}
      />
    </DashboardLayout>
  )
}
