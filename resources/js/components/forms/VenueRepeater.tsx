import { lazy, Suspense, useMemo } from 'react'
import DateTimeInput from '@/components/forms/DateTimeInput'
import SearchableSelect, { type SearchableOption } from '@/components/forms/SearchableSelect'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
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
  onChange: (venues: VenueFormRow[]) => void
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
  const { locale } = useLocale()

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
    onChange(venues.map((venue, rowIndex) => (rowIndex === index ? { ...venue, ...patch } : venue)))
  }

  function removeVenue(index: number) {
    onChange(venues.filter((_, rowIndex) => rowIndex !== index))
  }

  return (
    <section className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold">
            {locale === 'ar' ? 'أماكن الفعالية' : 'Event venues'}
          </h2>
          <p className="text-sm text-slate-600">
            {locale === 'ar'
              ? 'أضف مواقع متعددة مع تواريخ تسجيل مستقلة لكل موقع.'
              : 'Add multiple venues with independent registration windows.'}
          </p>
        </div>
        <button
          type="button"
          className="button-primary inline-flex cursor-pointer items-center gap-2"
          onClick={() => onChange([...venues, emptyVenueRow()])}
        >
          {locale === 'ar' ? 'إضافة موقع' : 'Add venue'}
        </button>
      </div>

      {venues.length === 0 && (
        <p className="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-600">
          {locale === 'ar' ? 'لا توجد مواقع بعد.' : 'No venues added yet.'}
        </p>
      )}

      <div className="grid gap-4 xl:grid-cols-3">
        {venues.map((venue, index) => (
          <article key={venue.key} className="state-panel space-y-3">
            <div className="flex items-center justify-between gap-2">
              <h3 className="font-medium">
                {locale === 'ar' ? `موقع ${index + 1}` : `Venue ${index + 1}`}
              </h3>
              <button
                type="button"
                className="text-sm text-red-700"
                onClick={() => removeVenue(index)}
              >
                {locale === 'ar' ? 'حذف' : 'Remove'}
              </button>
            </div>
            <TextInput
              label={locale === 'ar' ? 'الاسم (EN)' : 'Name (EN)'}
              name={`venue_${index}_name_en`}
              value={venue.name_en}
              onChange={(event) => updateVenue(index, { name_en: event.target.value })}
              error={errors[`venues.${index}.name.en`]}
            />
            <TextInput
              label={locale === 'ar' ? 'الاسم (AR)' : 'Name (AR)'}
              name={`venue_${index}_name_ar`}
              value={venue.name_ar}
              onChange={(event) => updateVenue(index, { name_ar: event.target.value })}
              error={errors[`venues.${index}.name.ar`]}
            />
            <SearchableSelect
              label={locale === 'ar' ? 'الدولة' : 'Country'}
              value={venue.country_id}
              onChange={(countryId) => updateVenue(index, { country_id: countryId, city_id: '' })}
              options={countryOptions}
              placeholder={locale === 'ar' ? 'ابحث عن دولة' : 'Search country'}
              error={errors[`venues.${index}.country_id`]}
            />
            <SearchableSelect
              label={locale === 'ar' ? 'المدينة' : 'City'}
              value={venue.city_id}
              onChange={(cityId) => updateVenue(index, { city_id: cityId })}
              options={citiesForCountry(venue.country_id)}
              placeholder={locale === 'ar' ? 'ابحث عن مدينة' : 'Search city'}
              disabled={!venue.country_id}
              error={errors[`venues.${index}.city_id`]}
            />
            <TextareaInput
              label={locale === 'ar' ? 'العنوان' : 'Address'}
              name={`venue_${index}_address`}
              value={venue.location_address}
              onChange={(event) => updateVenue(index, { location_address: event.target.value })}
              error={errors[`venues.${index}.location_address`]}
            />
            <Suspense fallback={<div className="h-56 animate-pulse rounded-lg bg-slate-200 dark:bg-slate-700" />}>
              <MapPicker
                label={locale === 'ar' ? 'الموقع على الخريطة' : 'Map location'}
                latitude={venue.latitude}
                longitude={venue.longitude}
                onLatitudeChange={(latitude) => updateVenue(index, { latitude })}
                onLongitudeChange={(longitude) => updateVenue(index, { longitude })}
              />
            </Suspense>
            <DateTimeInput
              label={locale === 'ar' ? 'بداية الفعالية' : 'Event starts'}
              name={`venue_${index}_start`}
              value={venue.start_at}
              onChange={(event) => updateVenue(index, { start_at: event.target.value })}
              required
              error={errors[`venues.${index}.start_at`]}
            />
            <DateTimeInput
              label={locale === 'ar' ? 'نهاية الفعالية' : 'Event ends'}
              name={`venue_${index}_end`}
              value={venue.end_at}
              onChange={(event) => updateVenue(index, { end_at: event.target.value })}
              required
              error={errors[`venues.${index}.end_at`]}
            />
            <DateTimeInput
              label={locale === 'ar' ? 'فتح التسجيل' : 'Registration opens'}
              name={`venue_${index}_reg_open`}
              value={venue.registration_opens_at}
              onChange={(event) => updateVenue(index, { registration_opens_at: event.target.value })}
              required
              error={errors[`venues.${index}.registration_opens_at`]}
            />
            <DateTimeInput
              label={locale === 'ar' ? 'إغلاق التسجيل' : 'Registration closes'}
              name={`venue_${index}_reg_close`}
              value={venue.registration_closes_at}
              onChange={(event) => updateVenue(index, { registration_closes_at: event.target.value })}
              required
              error={errors[`venues.${index}.registration_closes_at`]}
            />
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
