import { lazy, Suspense, useMemo, type Dispatch, type SetStateAction } from 'react'
import DateTimeInput from '@/components/forms/DateTimeInput'
import SearchableSelect, { type SearchableOption } from '@/components/forms/SearchableSelect'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import { formFieldProps } from '@/lib/formatValidationErrors'
import { useLocale } from '@/hooks/useLocale'

const MapPicker = lazy(() => import('@/components/forms/MapPicker'))

export type VenueFormRow = {
  key: string
  id?: string
  name_en: string
  name_ar: string
  country_id: string
  city_id: string
  location_address: string
  latitude: string
  longitude: string
  start_at: string
  end_at: string
  registration_opens_at: string
  registration_closes_at: string
}

type CountryOption = {
  id: string
  code: string
  name_en: string
  name_ar: string
  cities: Array<{ id: string; name_en: string; name_ar: string }>
}

type VenueRepeaterProps = {
  venues: VenueFormRow[]
  countries: CountryOption[]
  onChange: Dispatch<SetStateAction<VenueFormRow[]>>
  errors: Record<string, string>
}

function toLocalDateTime(value: string | null | undefined): string {
  if (!value) return ''
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return ''
  const pad = (n: number) => n.toString().padStart(2, '0')

  return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}T${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`
}

export function emptyVenueRow(): VenueFormRow {
  return {
    key: crypto.randomUUID(),
    name_en: '',
    name_ar: '',
    country_id: '',
    city_id: '',
    location_address: '',
    latitude: '',
    longitude: '',
    start_at: '',
    end_at: '',
    registration_opens_at: '',
    registration_closes_at: '',
  }
}

export default function VenueRepeater({ venues, countries, onChange, errors }: VenueRepeaterProps) {
  const { locale, t } = useLocale()

  const countryOptions: SearchableOption[] = useMemo(
    () => countries.map((country) => ({
      value: country.id,
      label: locale === 'ar' ? country.name_ar : country.name_en,
      hint: country.code,
      searchText: `${country.name_en} ${country.name_ar} ${country.code}`,
    })),
    [countries, locale],
  )

  function citiesForCountry(countryId: string): SearchableOption[] {
    const country = countries.find((row) => row.id === countryId)
    if (!country) return []

    return country.cities.map((city) => ({
      value: city.id,
      label: locale === 'ar' ? city.name_ar : city.name_en,
      searchText: `${city.name_en} ${city.name_ar}`,
    }))
  }

  function updateVenue(index: number, patch: Partial<VenueFormRow>) {
    onChange((current) => current.map((venue, rowIndex) => (rowIndex === index ? { ...venue, ...patch } : venue)))
  }

  function removeVenue(index: number) {
    onChange((current) => current.filter((_, rowIndex) => rowIndex !== index))
  }

  return (
    <section className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-lg font-semibold">
            {t('venueRepeaterTitle')}
          </h2>
          <p className="text-sm text-slate-600">
            {t('venueRepeaterDescription')}
          </p>
        </div>
        <button
          type="button"
          className="button-primary inline-flex w-full cursor-pointer items-center justify-center gap-2 sm:w-auto"
          onClick={() => onChange((current) => [...current, emptyVenueRow()])}
        >
          {t('venueRepeaterAddVenue')}
        </button>
      </div>

      {venues.length === 0 && (
        <p className="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-600">
          {t('venueRepeaterNoVenues')}
        </p>
      )}

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {venues.map((venue, index) => (
          <article key={venue.key} className="@container state-panel p-4 sm:p-5">
            <div className="mb-3 flex items-center justify-between gap-2">
              <h3 className="font-medium">
                {locale === 'ar' ? `موقع ${index + 1}` : `Venue ${index + 1}`}
              </h3>
              <button
                type="button"
                className="text-sm text-red-700"
                onClick={() => removeVenue(index)}
              >
                {t('venueRepeaterRemove')}
              </button>
            </div>
            <div className="grid grid-cols-1 gap-3 @md:grid-cols-2">
              <TextInput
                label={t('venueRepeaterNameEn')}
                name={`venue_${index}_name_en`}
                value={venue.name_en}
                onChange={(event) => updateVenue(index, { name_en: event.target.value })}
                error={errors[`venues.${index}.name.en`]}
                {...formFieldProps(`venues.${index}.name.en`)}
              />
              <TextInput
                label={t('venueRepeaterNameAr')}
                name={`venue_${index}_name_ar`}
                value={venue.name_ar}
                onChange={(event) => updateVenue(index, { name_ar: event.target.value })}
                error={errors[`venues.${index}.name.ar`]}
                {...formFieldProps(`venues.${index}.name.ar`)}
              />
              <SearchableSelect
                label={t('venueRepeaterCountry')}
                value={venue.country_id}
                onChange={(countryId) => updateVenue(index, { country_id: countryId, city_id: '' })}
                options={countryOptions}
                placeholder={t('venueRepeaterSearchCountry')}
                error={errors[`venues.${index}.country_id`]}
                {...formFieldProps(`venues.${index}.country_id`)}
              />
              <SearchableSelect
                label={t('venueRepeaterCity')}
                value={venue.city_id}
                onChange={(cityId) => updateVenue(index, { city_id: cityId })}
                options={citiesForCountry(venue.country_id)}
                placeholder={t('venueRepeaterSearchCity')}
                disabled={!venue.country_id}
                error={errors[`venues.${index}.city_id`]}
                {...formFieldProps(`venues.${index}.city_id`)}
              />
              <div className="@md:col-span-2">
                <TextareaInput
                  label={t('venueRepeaterAddress')}
                  name={`venue_${index}_address`}
                  value={venue.location_address}
                  onChange={(event) => updateVenue(index, { location_address: event.target.value })}
                  error={errors[`venues.${index}.location_address`]}
                  {...formFieldProps(`venues.${index}.location_address`)}
                />
              </div>
              <div className="@md:col-span-2">
                <Suspense fallback={<div className="h-56 animate-pulse rounded-lg bg-slate-200 dark:bg-slate-700" />}>
                  <MapPicker
                    label={t('venueRepeaterMapLocation')}
                    latitude={venue.latitude}
                    longitude={venue.longitude}
                    onLatitudeChange={(latitude) => updateVenue(index, { latitude })}
                    onLongitudeChange={(longitude) => updateVenue(index, { longitude })}
                    onCoordinatesChange={(latitude, longitude) => updateVenue(index, { latitude, longitude })}
                    latitudeError={errors[`venues.${index}.latitude`]}
                    longitudeError={errors[`venues.${index}.longitude`]}
                    data-form-field-latitude={`venues.${index}.latitude`}
                    data-form-field-longitude={`venues.${index}.longitude`}
                  />
                </Suspense>
              </div>
              <DateTimeInput
                label={t('venueRepeaterEventStarts')}
                name={`venue_${index}_start`}
                value={venue.start_at}
                onChange={(event) => updateVenue(index, { start_at: event.target.value })}
                required
                error={errors[`venues.${index}.start_at`]}
                {...formFieldProps(`venues.${index}.start_at`)}
              />
              <DateTimeInput
                label={t('venueRepeaterEventEnds')}
                name={`venue_${index}_end`}
                value={venue.end_at}
                onChange={(event) => updateVenue(index, { end_at: event.target.value })}
                required
                error={errors[`venues.${index}.end_at`]}
                {...formFieldProps(`venues.${index}.end_at`)}
              />
              <DateTimeInput
                label={t('venueRepeaterRegistrationOpens')}
                name={`venue_${index}_reg_open`}
                value={venue.registration_opens_at}
                onChange={(event) => updateVenue(index, { registration_opens_at: event.target.value })}
                required
                error={errors[`venues.${index}.registration_opens_at`]}
                {...formFieldProps(`venues.${index}.registration_opens_at`)}
              />
              <DateTimeInput
                label={t('venueRepeaterRegistrationCloses')}
                name={`venue_${index}_reg_close`}
                value={venue.registration_closes_at}
                onChange={(event) => updateVenue(index, { registration_closes_at: event.target.value })}
                required
                error={errors[`venues.${index}.registration_closes_at`]}
                {...formFieldProps(`venues.${index}.registration_closes_at`)}
              />
            </div>
          </article>
        ))}
      </div>
    </section>
  )
}

export function venueRowsFromEvent(
  venues: Array<{
    id?: string
    country_id: string
    city_id: string
    name: { en: string; ar: string }
    location_address: string
    latitude: string
    longitude: string
    start_at: string | null
    end_at: string | null
    registration_opens_at: string | null
    registration_closes_at: string | null
  }>,
): VenueFormRow[] {
  if (venues.length === 0) return []

  return venues.map((venue) => ({
    key: venue.id ?? crypto.randomUUID(),
    id: venue.id,
    name_en: venue.name.en,
    name_ar: venue.name.ar,
    country_id: venue.country_id,
    city_id: venue.city_id,
    location_address: venue.location_address,
    latitude: venue.latitude,
    longitude: venue.longitude,
    start_at: toLocalDateTime(venue.start_at),
    end_at: toLocalDateTime(venue.end_at),
    registration_opens_at: toLocalDateTime(venue.registration_opens_at),
    registration_closes_at: toLocalDateTime(venue.registration_closes_at),
  }))
}
