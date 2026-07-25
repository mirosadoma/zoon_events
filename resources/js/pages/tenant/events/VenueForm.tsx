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
import { PageContent, PageHeader } from '@/components/layout'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import { buildVenuePayload } from '@/lib/venuePayload'
import type { EventVenueRow } from '@/pages/tenant/events/Venues'

type CountryOption = {
  id: string
  code: string
  name_en: string
  name_ar: string
  cities: Array<{ id: string; name_en: string; name_ar: string }>
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

function remapErrorsForIndex(errors: Record<string, string>, index: number): Record<string, string> {
  const remapped: Record<string, string> = {}

  for (const [key, value] of Object.entries(errors)) {
    if (key === 'venues') {
      remapped.venues = value
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
}: Props) {
  const { locale, t, localizedPath } = useLocale()
  const { toast } = useToast()
  const [draft, setDraft] = useState<VenueFormRow>(() => (
    mode === 'edit' && venue ? toFormRow(venue) : emptyVenueRow()
  ))
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)

  const listHref = `/tenant/events/${event.id}/venues`
  const pageTitle = mode === 'edit' ? t('eventVenuesEditTitle') : t('eventVenuesAddTitle')

  const existingRows = useMemo(
    () => venues.map((row) => toFormRow(row)),
    [venues],
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

      await apiFetch<{ venues: EventVenueRow[] }>(`/api/v1/tenant/events/${event.id}/venues`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: { venues: payload },
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
          <LocalizedLink className="button-secondary" href={listHref}>
            {t('cancel')}
          </LocalizedLink>
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
