import { FormEvent, useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import VenueFormFields from '@/components/forms/VenueFormFields'
import {
  emptyVenueRow,
  venueRowsFromEvent,
  type VenueFormRow,
} from '@/components/forms/VenueRepeater'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import SelectInput from '@/components/forms/SelectInput'
import { PageContent, PageHeader } from '@/components/layout'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import { buildVenuePayload } from '@/lib/venuePayload'
import type { EventVenueRow, EventZoneRow } from '@/pages/tenant/events/Venues'

type CountryOption = {
  id: string
  code: string
  name_en: string
  name_ar: string
  cities: Array<{ id: string; name_en: string; name_ar: string }>
}

type ZoneDraft = {
  key: string
  id?: string
  zone_name_en: string
  zone_name_ar: string
  description_en: string
  description_ar: string
  type: string
  floor_type: 'basement' | 'floor' | null
  floor_number: string
  capacity: string
}

type Props = {
  event: {
    id: string
    name: { en: string; ar: string }
  }
  tenantId: string
  venues: EventVenueRow[]
  venue: EventVenueRow | null
  mode: 'create' | 'edit'
  countries: CountryOption[]
  zoneTypes?: string[]
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

function emptyZone(defaultType: string): ZoneDraft {
  return {
    key: crypto.randomUUID(),
    zone_name_en: '',
    zone_name_ar: '',
    description_en: '',
    description_ar: '',
    type: defaultType,
    floor_type: null,
    floor_number: '',
    capacity: '',
  }
}

function zonesFromVenue(venue: EventVenueRow | null, defaultType: string): ZoneDraft[] {
  if (!venue?.zones?.length) {
    return []
  }

  return venue.zones.map((zone: EventZoneRow) => ({
    key: zone.id,
    id: zone.id,
    zone_name_en: zone.zone_name_en ?? zone.name?.en ?? '',
    zone_name_ar: zone.zone_name_ar ?? zone.name?.ar ?? '',
    description_en: zone.description_en ?? '',
    description_ar: zone.description_ar ?? '',
    type: zone.type || defaultType,
    floor_type: zone.floor_type === 'basement' || zone.floor_type === 'floor'
      ? zone.floor_type
      : null,
    floor_number: zone.floor_number !== null && zone.floor_number !== undefined
      ? String(zone.floor_number)
      : '',
    capacity: zone.capacity !== null && zone.capacity !== undefined ? String(zone.capacity) : '',
  }))
}

function remapErrorsForIndex(errors: Record<string, string>, index: number): Record<string, string> {
  const remapped: Record<string, string> = {}

  for (const [key, value] of Object.entries(errors)) {
    if (key === 'venues' || key === 'zones') {
      remapped[key] = value
      continue
    }

    const prefix = `venues.${index}.`
    if (key.startsWith(prefix)) {
      remapped[`venues.0.${key.slice(prefix.length)}`] = value
    }
  }

  return remapped
}

export default function EventVenueFormPage({
  event,
  tenantId,
  venues,
  venue,
  mode,
  countries,
  zoneTypes = ['hall', 'stage', 'room', 'vip'],
}: Props) {
  const { locale, t, localizedPath } = useLocale()
  const { toast } = useToast()
  const defaultType = zoneTypes[0] ?? 'hall'
  const [draft, setDraft] = useState<VenueFormRow>(() => (
    mode === 'edit' && venue ? toFormRow(venue) : emptyVenueRow()
  ))
  const [zones, setZones] = useState<ZoneDraft[]>(() => zonesFromVenue(venue, defaultType))
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)

  const listHref = `/tenant/events/${event.id}/venues`
  const pageTitle = mode === 'edit' ? t('eventVenuesEditTitle') : t('eventVenuesAddTitle')

  const existingRows = useMemo(
    () => venues.map((row) => toFormRow(row)),
    [venues],
  )

  const typeOptions = useMemo(
    () => zoneTypes.map((type) => ({
      value: type,
      label: t(`eventZoneType_${type}` as 'eventZoneType_hall'),
    })),
    [zoneTypes, t],
  )

  async function save(eventForm: FormEvent) {
    eventForm.preventDefault()
    setSaving(true)
    setErrors({})

    try {
      const nextRows = mode === 'create'
        ? [...existingRows, draft]
        : existingRows.map((row) => (row.id === venue?.id ? draft : row))

      const payload = buildVenuePayload(nextRows)
      if (nextRows.length > 0 && payload.length === 0) {
        const message = t('eventVenuesIncomplete')
        setErrors({ venues: message })
        toast(message, 'error')
        return
      }

      const incompleteZone = zones.find((zone) => (
        zone.zone_name_en.trim() === '' || zone.zone_name_ar.trim() === '' || zone.type.trim() === ''
      ))
      if (incompleteZone) {
        const message = t('eventZonesIncomplete')
        setErrors({ zones: message })
        toast(message, 'error')
        return
      }

      const venueResult = await apiFetch<{ venues: EventVenueRow[] }>(`/api/v1/tenant/events/${event.id}/venues`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: { venues: payload },
      })

      const savedVenues = venueResult.venues ?? []
      let venueId = venue?.id

      if (!venueId) {
        const previousIds = new Set(venues.map((row) => row.id))
        venueId = savedVenues.find((row) => !previousIds.has(row.id))?.id
      }

      if (!venueId) {
        toast(t('requestFailed'), 'error')
        return
      }

      await apiFetch(`/api/v1/tenant/events/${event.id}/zones`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: {
          venue_id: Number(venueId),
          zones: zones.map((zone) => ({
            id: zone.id ? Number(zone.id) : undefined,
            zone_name_en: zone.zone_name_en.trim(),
            zone_name_ar: zone.zone_name_ar.trim(),
            description_en: zone.description_en.trim() || null,
            description_ar: zone.description_ar.trim() || null,
            type: zone.type,
            floor_type: zone.floor_type,
            floor_number: zone.floor_type === 'floor' && zone.floor_number.trim() !== ''
              ? Number(zone.floor_number)
              : null,
            capacity: zone.capacity.trim() === '' ? null : Number(zone.capacity),
          })),
        },
      })

      toast(t('saved'), 'success')
      router.visit(localizedPath(listHref))
    } catch (caught) {
      if (caught instanceof ApiFetchError) {
        const focusIndex = mode === 'create'
          ? venues.length
          : venues.findIndex((row) => row.id === venue?.id)
        setErrors(remapErrorsForIndex(caught.errors, Math.max(focusIndex, 0)))
        toast(caught.message, 'error')
      } else {
        toast(t('requestFailed'), 'error')
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <DashboardLayout title={pageTitle}>
      <PageHeader
        title={pageTitle}
        description={t('eventVenuesManageHint')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('overviewEvents'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('eventVenues'), href: listHref },
          { label: pageTitle },
        ]}
        actions={(
          <div className="flex flex-wrap gap-2">
            {mode === 'edit' && venue ? (
              <LocalizedLink
                className="button-secondary"
                href={`/tenant/events/${event.id}/venues/${venue.id}/map`}
              >
                {t('venueMapTitle')}
              </LocalizedLink>
            ) : null}
            <LocalizedLink className="button-secondary" href={listHref}>
              {t('cancel')}
            </LocalizedLink>
          </div>
        )}
      />

      <PageContent>
        <form className="ta-card space-y-4 p-4" onSubmit={(submitEvent) => void save(submitEvent)}>
          {errors.venues ? (
            <p role="alert" className="rounded-lg border border-[var(--danger)]/30 bg-[color-mix(in_srgb,var(--danger)_10%,transparent)] px-3 py-2 text-sm text-[var(--danger)]">
              {errors.venues}
            </p>
          ) : null}
          <VenueFormFields
            venue={draft}
            countries={countries}
            errors={errors}
            onChange={(patch) => setDraft((current) => ({ ...current, ...patch }))}
          />

          <section className="space-y-3 border-t border-[var(--border)] pt-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h2 className="font-semibold text-[var(--ink)]">{t('eventZonesTitle')}</h2>
                <p className="text-sm text-[var(--muted)]">{t('eventZonesHint')}</p>
              </div>
              <button
                type="button"
                className="button-secondary"
                onClick={() => setZones((current) => [...current, emptyZone(defaultType)])}
              >
                {t('eventZonesAdd')}
              </button>
            </div>

            {errors.zones ? (
              <p role="alert" className="text-sm text-[var(--danger)]">{errors.zones}</p>
            ) : null}

            {zones.length === 0 ? (
              <p className="text-sm text-[var(--muted)]">{t('eventZonesEmpty')}</p>
            ) : (
              <div className="grid gap-3 sm:grid-cols-2">
                {zones.map((zone, index) => (
                  <div
                    key={zone.key}
                    className="grid gap-3 rounded-lg border border-[var(--border)] bg-[var(--surface)] p-3 sm:grid-cols-2 border-[var(--brand)]"
                  >
                    <TextInput
                      label={t('eventZoneNameEn')}
                      name={`zone_name_en_${index}`}
                      value={zone.zone_name_en}
                      onChange={(e) => setZones((current) => current.map((row, rowIndex) => (
                        rowIndex === index ? { ...row, zone_name_en: e.target.value } : row
                      )))}
                      required
                    />
                    <TextInput
                      label={t('eventZoneNameAr')}
                      name={`zone_name_ar_${index}`}
                      value={zone.zone_name_ar}
                      onChange={(e) => setZones((current) => current.map((row, rowIndex) => (
                        rowIndex === index ? { ...row, zone_name_ar: e.target.value } : row
                      )))}
                      required
                    />
                    <SelectInput
                      label={t('eventZoneType')}
                      name={`zone_type_${index}`}
                      value={zone.type}
                      onChange={(e) => setZones((current) => current.map((row, rowIndex) => (
                        rowIndex === index ? { ...row, type: e.target.value } : row
                      )))}
                      options={typeOptions}
                    />
                    <TextInput
                      label={t('eventZoneCapacity')}
                      name={`zone_capacity_${index}`}
                      type="number"
                      min={0}
                      value={zone.capacity}
                      onChange={(e) => setZones((current) => current.map((row, rowIndex) => (
                        rowIndex === index ? { ...row, capacity: e.target.value } : row
                      )))}
                    />
                    <div className="sm:col-span-2 flex flex-wrap items-end gap-4">
                      <fieldset className="min-w-0 flex-1 space-y-2">
                        <legend className="text-sm font-medium text-[var(--ink)]">{t('eventZoneFloorType')}</legend>
                        <div className="flex flex-wrap items-center gap-4">
                          <label className="inline-flex items-center gap-2 text-sm">
                            <input
                              type="radio"
                              name={`floor_type_${index}`}
                              checked={zone.floor_type === 'basement'}
                              onChange={() => setZones((current) => current.map((row, rowIndex) => (
                                rowIndex === index
                                  ? { ...row, floor_type: 'basement', floor_number: '' }
                                  : row
                              )))}
                            />
                            <span>{t('eventZoneFloorType_basement')}</span>
                          </label>
                          <label className="inline-flex items-center gap-2 text-sm">
                            <input
                              type="radio"
                              name={`floor_type_${index}`}
                              checked={zone.floor_type === 'floor'}
                              onChange={() => setZones((current) => current.map((row, rowIndex) => (
                                rowIndex === index
                                  ? { ...row, floor_type: 'floor', floor_number: row.floor_number || '1' }
                                  : row
                              )))}
                            />
                            <span>{t('eventZoneFloorType_floor')}</span>
                          </label>
                        </div>
                      </fieldset>
                      {zone.floor_type === 'floor' ? (
                        <div className="w-full max-w-md shrink-0">
                          <TextInput
                            label={t('eventZoneFloorNumber')}
                            name={`floor_number_${index}`}
                            type="number"
                            min={0}
                            max={500}
                            value={zone.floor_number}
                            onChange={(e) => setZones((current) => current.map((row, rowIndex) => (
                              rowIndex === index ? { ...row, floor_number: e.target.value } : row
                            )))}
                          />
                        </div>
                      ) : null}
                    </div>
                    <TextareaInput
                      label={t('eventZoneDescriptionEn')}
                      name={`description_en_${index}`}
                      rows={3}
                      className="min-h-20"
                      value={zone.description_en}
                      onChange={(e) => setZones((current) => current.map((row, rowIndex) => (
                        rowIndex === index ? { ...row, description_en: e.target.value } : row
                      )))}
                    />
                    <TextareaInput
                      label={t('eventZoneDescriptionAr')}
                      name={`description_ar_${index}`}
                      rows={3}
                      className="min-h-20"
                      value={zone.description_ar}
                      onChange={(e) => setZones((current) => current.map((row, rowIndex) => (
                        rowIndex === index ? { ...row, description_ar: e.target.value } : row
                      )))}
                    />
                    <div className="flex items-end sm:col-span-2">
                      <button
                        type="button"
                        className="button-secondary text-[var(--danger)]"
                        onClick={() => setZones((current) => current.filter((_, rowIndex) => rowIndex !== index))}
                      >
                        {t('remove')}
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </section>

          <div className="flex flex-wrap gap-2">
            <SubmitButtonWithLoader label={t('save')} loading={saving} />
            <LocalizedLink className="button-secondary" href={listHref}>
              {t('cancel')}
            </LocalizedLink>
          </div>
        </form>
      </PageContent>
    </DashboardLayout>
  )
}
