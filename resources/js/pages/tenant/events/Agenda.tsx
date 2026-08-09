import { useEffect, useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import ConfirmModal from '@/components/modals/ConfirmModal'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import SearchInput from '@/components/tables/SearchInput'
import SelectInput from '@/components/forms/SelectInput'
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
import { toTimeLocalValue } from '@/lib/dateTimeLocal'

type LocalizedName = { en: string; ar: string }

export type AgendaItemPayload = {
  id: string
  event_venue_id: string | null
  zone_id: string | null
  agenda_date: string | null
  title_en: string
  title_ar: string
  description_en: string
  description_ar: string
  speaker: string | null
  start_at: string | null
  end_at: string | null
  venue_name?: LocalizedName | null
  zone_name?: LocalizedName | null
}

type VenueOption = {
  id: string
  name: LocalizedName
  start_at: string | null
  end_at: string | null
  zones?: Array<{
    id: string
    venue_id: string
    name: LocalizedName
  }>
}

type Props = {
  event: {
    id: string
    name: LocalizedName
    timezone?: string | null
  }
  tenantId: string
  items: AgendaItemPayload[]
  venues: VenueOption[]
}

function toLocalTime(value: string | null | undefined): string {
  return toTimeLocalValue(value)
}

function formatTimeRange(startAt: string | null, endAt: string | null): string {
  const start = toLocalTime(startAt)
  if (!start) return '—'
  const end = toLocalTime(endAt)
  return end ? `${start} – ${end}` : start
}

function localizedText(value: LocalizedName | null | undefined, locale: 'en' | 'ar'): string {
  if (!value) return ''
  return value[locale] || value.en || value.ar || ''
}

function toSyncPayload(item: AgendaItemPayload) {
  const date = item.agenda_date || item.start_at?.split('T')[0] || ''
  const startTime = toLocalTime(item.start_at)
  const endTime = toLocalTime(item.end_at)

  return {
    id: Number(item.id),
    event_venue_id: item.event_venue_id ? Number(item.event_venue_id) : null,
    zone_id: item.zone_id ? Number(item.zone_id) : null,
    agenda_date: date || null,
    title_en: item.title_en,
    title_ar: item.title_ar,
    description_en: item.description_en || null,
    description_ar: item.description_ar || null,
    speaker: item.speaker || null,
    start_at: date && startTime ? `${date}T${startTime}` : item.start_at,
    end_at: date && endTime ? `${date}T${endTime}` : item.end_at,
  }
}

export default function EventAgendaPage({
  event,
  tenantId,
  items: initialItems,
  venues,
}: Props) {
  const { locale, t, localizedPath } = useLocale()
  const { toast } = useToast()
  const [items, setItems] = useState(initialItems)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [deleteId, setDeleteId] = useState<string | null>(null)
  const [deleting, setDeleting] = useState(false)
  const [search, setSearch] = useState('')
  const [venueId, setVenueId] = useState('')
  const [zoneId, setZoneId] = useState('')
  const [date, setDate] = useState('')

  useEffect(() => {
    setItems(initialItems)
  }, [initialItems])

  const listHref = `/tenant/events/${event.id}/agenda`
  const createHref = `${listHref}/create`

  const venueOptions = useMemo(
    () => [
      { value: '', label: t('eventAgendaFilterAllVenues') },
      ...venues.map((venue) => ({
        value: venue.id,
        label: localizedText(venue.name, locale),
      })),
    ],
    [venues, locale, t],
  )

  const zoneOptions = useMemo(() => {
    const source = venueId
      ? venues.filter((venue) => venue.id === venueId)
      : venues

    const zones = source.flatMap((venue) => venue.zones ?? [])

    return [
      { value: '', label: t('eventAgendaFilterAllZones') },
      ...zones.map((zone) => ({
        value: zone.id,
        label: localizedText(zone.name, locale),
      })),
    ]
  }, [venues, venueId, locale, t])

  const dateOptions = useMemo(() => {
    const dates = Array.from(new Set(
      items
        .map((item) => item.agenda_date || item.start_at?.split('T')[0] || '')
        .filter(Boolean),
    )).sort()

    return [
      { value: '', label: t('eventAgendaFilterAllDates') },
      ...dates.map((value) => ({ value, label: value })),
    ]
  }, [items, t])

  const filteredItems = useMemo(() => {
    const needle = search.trim().toLowerCase()

    return items.filter((item) => {
      if (venueId && item.event_venue_id !== venueId) {
        return false
      }

      if (zoneId && item.zone_id !== zoneId) {
        return false
      }

      const itemDate = item.agenda_date || item.start_at?.split('T')[0] || ''
      if (date && itemDate !== date) {
        return false
      }

      if (!needle) {
        return true
      }

      const haystack = [
        item.title_en,
        item.title_ar,
        item.speaker ?? '',
        item.description_en,
        item.description_ar,
        localizedText(item.venue_name, locale),
        localizedText(item.zone_name, locale),
        itemDate,
      ].join(' ').toLowerCase()

      return haystack.includes(needle)
    })
  }, [items, search, venueId, zoneId, date, locale])

  const tableRows = useMemo(
    () => filteredItems.map((item) => ({
      id: item.id,
      title: locale === 'ar' ? item.title_ar : item.title_en,
      venue: item.venue_name
        ? localizedText(item.venue_name, locale)
        : t('notAvailable'),
      zone: item.zone_name
        ? localizedText(item.zone_name, locale)
        : t('eventAgendaZoneNone'),
      date: item.agenda_date || item.start_at?.split('T')[0] || t('notAvailable'),
      time: formatTimeRange(item.start_at, item.end_at),
      speaker: item.speaker?.trim() || t('notAvailable'),
    })),
    [filteredItems, locale, t],
  )

  function clearFilters() {
    setSearch('')
    setVenueId('')
    setZoneId('')
    setDate('')
  }

  const hasActiveFilters = search.trim() !== '' || venueId !== '' || zoneId !== '' || date !== ''

  const selected = items.find((item) => item.id === selectedId) ?? null
  const notAvailable = t('notAvailable')

  function closePane() {
    setSelectedId(null)
  }

  function goToEdit() {
    if (!selectedId) return
    router.visit(localizedPath(`${listHref}/${selectedId}/edit`))
  }

  async function confirmDelete() {
    if (!deleteId) return

    setDeleting(true)
    try {
      const remaining = items
        .filter((item) => item.id !== deleteId)
        .map((item) => toSyncPayload(item))

      await apiFetch(`/api/v1/tenant/events/${event.id}/agenda`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: { items: remaining },
      })

      setDeleteId(null)
      toast(t('deleted'), 'success')
      router.reload({ only: ['items'] })
    } catch (caught) {
      toast(caught instanceof ApiFetchError ? caught.message : t('requestFailed'), 'error')
    } finally {
      setDeleting(false)
    }
  }

  const deleteTarget = items.find((item) => item.id === deleteId)

  if (venues.length === 0) {
    return (
      <DashboardLayout title={event.name[locale]}>
        <PageHeader
          title={t('eventAgendaTitle')}
          description={t('eventAgendaDescription')}
          breadcrumbs={[
            { label: t('overview'), href: '/dashboard' },
            { label: t('overviewEvents'), href: '/tenant/events' },
            { label: event.name[locale], href: `/tenant/events/${event.id}` },
            { label: t('eventAgendaTitle') },
          ]}
          actions={(
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}`}>
              {t('eventAgendaBack')}
            </LocalizedLink>
          )}
        />
        <PageContent>
          <EmptyState
            title={t('eventAgendaNeedsVenueTitle')}
            detail={t('eventAgendaNeedsVenueDetail')}
            action={(
              <LocalizedLink className="button-primary" href={`/tenant/events/${event.id}/venues`}>
                {t('eventVenues')}
              </LocalizedLink>
            )}
          />
        </PageContent>
      </DashboardLayout>
    )
  }

  return (
    <DashboardLayout title={event.name[locale]}>
      <PageHeader
        title={t('eventAgendaTitle')}
        description={t('eventAgendaDescription')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('overviewEvents'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('eventAgendaTitle') },
        ]}
        actions={(
          <div className="flex flex-wrap gap-2">
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}`}>
              {t('eventAgendaBack')}
            </LocalizedLink>
            <LocalizedLink className="button-primary" href={createHref}>
              {t('eventAgendaAddItem')}
            </LocalizedLink>
          </div>
        )}
      />

      <PageContent>
        {items.length === 0 ? (
          <EmptyState
            title={t('eventAgendaEmpty')}
            detail={t('eventAgendaDescription')}
            action={(
              <LocalizedLink className="button-primary" href={createHref}>
                {t('eventAgendaAddItem')}
              </LocalizedLink>
            )}
          />
        ) : (
          <>
            <FiltersBar>
              <SearchInput
                value={search}
                onChange={setSearch}
                label={t('search')}
                placeholder={t('eventAgendaSearchPlaceholder')}
              />
              <SelectInput
                label={t('eventAgendaPreviewVenue')}
                name="venue_id"
                value={venueId}
                onChange={(e) => {
                  setVenueId(e.target.value)
                  setZoneId('')
                }}
                options={venueOptions}
              />
              <SelectInput
                label={t('eventAgendaZone')}
                name="zone_id"
                value={zoneId}
                onChange={(e) => setZoneId(e.target.value)}
                options={zoneOptions}
              />
              <SelectInput
                label={t('eventAgendaDate')}
                name="agenda_date"
                value={date}
                onChange={(e) => setDate(e.target.value)}
                options={dateOptions}
              />
              {hasActiveFilters ? (
                <button type="button" className="button-secondary" onClick={clearFilters}>
                  {t('clearFilters')}
                </button>
              ) : null}
            </FiltersBar>

            {filteredItems.length === 0 ? (
              <EmptyState
                title={t('eventAgendaNoFilterResults')}
                detail={t('eventAgendaNoFilterResultsDetail')}
                action={(
                  <button type="button" className="button-secondary" onClick={clearFilters}>
                    {t('clearFilters')}
                  </button>
                )}
              />
            ) : (
              <DataTable
                title={t('eventAgendaTitle')}
                columns={[
                  { key: 'title', header: t('name') },
                  { key: 'venue', header: t('eventAgendaPreviewVenue') },
                  { key: 'zone', header: t('eventAgendaZone') },
                  { key: 'date', header: t('eventAgendaDate') },
                  { key: 'time', header: t('eventAgendaTime') },
                  { key: 'speaker', header: t('eventAgendaSpeaker') },
                ]}
                rows={tableRows}
                getRowKey={(row) => String(row.id)}
                selectedRowKey={selectedId}
                onRowClick={(row) => setSelectedId(String(row.id))}
              />
            )}
          </>
        )}
      </PageContent>

      <SideDetailPane
        open={selected !== null}
        title={selected ? (locale === 'ar' ? selected.title_ar : selected.title_en) : ''}
        subtitle={selected?.speaker?.trim() || null}
        onClose={closePane}
        onEdit={goToEdit}
        onDelete={() => setDeleteId(selectedId)}
        footer={selected ? (
          <SideDetailActions>
            <button type="button" className={sideDetailActionClassName('primary')} onClick={goToEdit}>
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
                value: locale === 'ar' ? selected.title_ar : selected.title_en,
              },
              {
                label: t('eventAgendaPreviewVenue'),
                value: selected.venue_name
                  ? localizedText(selected.venue_name, locale)
                  : notAvailable,
              },
              {
                label: t('eventAgendaZone'),
                value: selected.zone_name
                  ? localizedText(selected.zone_name, locale)
                  : t('eventAgendaZoneNone'),
              },
              {
                label: t('eventAgendaDate'),
                value: selected.agenda_date || selected.start_at?.split('T')[0] || notAvailable,
              },
              {
                label: t('eventAgendaTime'),
                value: formatTimeRange(selected.start_at, selected.end_at),
              },
              {
                label: t('eventAgendaSpeaker'),
                value: selected.speaker?.trim() || notAvailable,
              },
            ]}
          />
        ) : null}
      </SideDetailPane>

      <ConfirmModal
        open={deleteId !== null}
        title={t('eventAgendaDeleteTitle')}
        message={t('eventAgendaDeleteMessage', {
          name: deleteTarget
            ? (locale === 'ar' ? deleteTarget.title_ar : deleteTarget.title_en)
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
