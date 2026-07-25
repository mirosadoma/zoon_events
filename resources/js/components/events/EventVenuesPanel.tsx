import { FormEvent, useEffect, useMemo, useState } from 'react'
import VenueRepeater, {
  emptyVenueRow,
  venueRowsFromEvent,
  type VenueFormRow,
} from '@/components/forms/VenueRepeater'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import { buildVenuePayload } from '@/lib/venuePayload'

type CountryOption = {
  id: string
  code: string
  name_en: string
  name_ar: string
  cities: Array<{ id: string; name_en: string; name_ar: string }>
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
}

type Props = {
  eventId: string
  tenantId: string
  venues: EventVenueRow[]
  countries: CountryOption[]
}

export default function EventVenuesPanel({ eventId, tenantId, venues: initialVenues, countries }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [venues, setVenues] = useState<EventVenueRow[]>(initialVenues)
  const [editing, setEditing] = useState(false)
  const [rows, setRows] = useState<VenueFormRow[]>([emptyVenueRow()])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    setVenues(initialVenues)
  }, [initialVenues])

  const tableRows = useMemo(
    () => venues.map((venue) => ({
      id: venue.id,
      name: venue.name[locale] || venue.name.en || venue.name.ar,
      address: venue.location_address?.trim() || t('notAvailable'),
      start: venue.start_at ? new Date(venue.start_at).toLocaleString(locale === 'ar' ? 'ar' : 'en') : t('notAvailable'),
      end: venue.end_at ? new Date(venue.end_at).toLocaleString(locale === 'ar' ? 'ar' : 'en') : t('notAvailable'),
    })),
    [venues, locale, t],
  )

  function startEdit() {
    setRows(venues.length === 0 ? [emptyVenueRow()] : venueRowsFromEvent(venues.map((venue) => ({
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
    }))))
    setErrors({})
    setEditing(true)
  }

  async function save(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setErrors({})

    try {
      const payload = buildVenuePayload(rows)
      if (rows.length > 0 && payload.length === 0) {
        const message = locale === 'ar'
          ? 'أكمل بيانات المكان (الاسم، الدولة، المدينة، والتواريخ) قبل الحفظ.'
          : 'Complete venue details (names, country, city, and dates) before saving.'
        setErrors({ venues: message })
        toast(message, 'error')
        return
      }

      const result = await apiFetch<{ venues: EventVenueRow[] }>(`/api/v1/tenant/events/${eventId}/venues`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: { venues: payload },
      })
      setVenues(result.venues ?? [])
      setEditing(false)
      toast(t('saved'), 'success')
    } catch (caught) {
      if (caught instanceof ApiFetchError) {
        setErrors(caught.errors)
        toast(caught.message, 'error')
      } else {
        toast(t('requestFailed'), 'error')
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <section id="venues" className="ta-card mt-6 space-y-4 p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold">{t('venues')}</h2>
          <p className="text-sm text-[var(--muted)]">{t('eventVenuesManageHint')}</p>
        </div>
        {!editing ? (
          <button type="button" className="button-primary" onClick={startEdit}>
            {venues.length === 0 ? t('addVenue') : t('editVenues')}
          </button>
        ) : null}
      </div>

      {!editing ? (
        venues.length === 0 ? (
          <p className="text-sm text-[var(--muted)]">{t('eventVenuesEmpty')}</p>
        ) : (
          <DataTable
            columns={[
              { key: 'name', header: t('name') },
              { key: 'address', header: t('address') },
              { key: 'start', header: t('startAt') },
              { key: 'end', header: t('endAt') },
            ]}
            rows={tableRows}
            getRowKey={(row) => String(row.id)}
          />
        )
      ) : (
        <form className="space-y-4" onSubmit={(eventForm) => void save(eventForm)}>
          {errors.venues ? (
            <p role="alert" className="rounded-lg border border-[var(--danger)]/30 bg-[color-mix(in_srgb,var(--danger)_10%,transparent)] px-3 py-2 text-sm text-[var(--danger)]">
              {errors.venues}
            </p>
          ) : null}
          <VenueRepeater venues={rows} countries={countries} onChange={setRows} errors={errors} />
          <div className="flex flex-wrap gap-2">
            <SubmitButtonWithLoader label={t('save')} loading={saving} />
            <button
              type="button"
              className="button-secondary"
              disabled={saving}
              onClick={() => {
                setEditing(false)
                setErrors({})
              }}
            >
              {t('cancel')}
            </button>
          </div>
        </form>
      )}
    </section>
  )
}
