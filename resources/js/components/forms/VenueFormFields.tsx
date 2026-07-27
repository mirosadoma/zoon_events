import { lazy, Suspense, useMemo } from 'react'
import DateTimeInput from '@/components/forms/DateTimeInput'
import SearchableSelect, { type SearchableOption } from '@/components/forms/SearchableSelect'
import TextInput from '@/components/forms/TextInput'
import { type VenueFormRow } from '@/components/forms/VenueRepeater'
import { formFieldProps } from '@/lib/formatValidationErrors'
import { useLocale } from '@/hooks/useLocale'

const MapPicker = lazy(() => import('@/components/forms/MapPicker'))

type CountryOption = {
  id: string
  code: string
  name_en: string
  name_ar: string
  cities: Array<{ id: string; name_en: string; name_ar: string }>
}

type Props = {
  venue: VenueFormRow
  countries: CountryOption[]
  errors: Record<string, string>
  /** Validation error key prefix, e.g. venues.0 */
  errorPrefix?: string
  onChange: (patch: Partial<VenueFormRow>) => void
}

export default function VenueFormFields({
  venue,
  countries,
  errors,
  errorPrefix = 'venues.0',
  onChange,
}: Props) {
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

  const cityOptions: SearchableOption[] = useMemo(() => {
    const country = countries.find((row) => row.id === venue.country_id)
    if (!country) return []

    return country.cities.map((city) => ({
      value: city.id,
      label: locale === 'ar' ? city.name_ar : city.name_en,
      searchText: `${city.name_en} ${city.name_ar}`,
    }))
  }, [countries, locale, venue.country_id])

  function errorFor(field: string): string | undefined {
    return errors[`${errorPrefix}.${field}`]
  }

  return (
    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
      <TextInput
        label={t('venueRepeaterNameEn')}
        name="venue_name_en"
        value={venue.name_en}
        onChange={(event) => onChange({ name_en: event.target.value })}
        error={errorFor('name.en')}
        {...formFieldProps(`${errorPrefix}.name.en`)}
      />
      <TextInput
        label={t('venueRepeaterNameAr')}
        name="venue_name_ar"
        value={venue.name_ar}
        onChange={(event) => onChange({ name_ar: event.target.value })}
        error={errorFor('name.ar')}
        {...formFieldProps(`${errorPrefix}.name.ar`)}
      />
      <SearchableSelect
        label={t('venueRepeaterCountry')}
        value={venue.country_id}
        onChange={(countryId) => onChange({ country_id: countryId, city_id: '' })}
        options={countryOptions}
        placeholder={t('venueRepeaterSearchCountry')}
        error={errorFor('country_id')}
        {...formFieldProps(`${errorPrefix}.country_id`)}
      />
      <SearchableSelect
        label={t('venueRepeaterCity')}
        value={venue.city_id}
        onChange={(cityId) => onChange({ city_id: cityId })}
        options={cityOptions}
        placeholder={t('venueRepeaterSearchCity')}
        disabled={!venue.country_id}
        error={errorFor('city_id')}
        {...formFieldProps(`${errorPrefix}.city_id`)}
      />
      <div className="md:col-span-2">
        <TextInput
          label={t('venueRepeaterAddress')}
          name="venue_address"
          value={venue.location_address}
          onChange={(event) => onChange({ location_address: event.target.value })}
          error={errorFor('location_address')}
          {...formFieldProps(`${errorPrefix}.location_address`)}
        />
      </div>
      <div className="md:col-span-2">
        <Suspense fallback={<div className="min-h-[30rem] animate-pulse rounded-lg bg-slate-200 dark:bg-slate-700" />}>
          <MapPicker
            label={t('venueRepeaterMapLocation')}
            latitude={venue.latitude}
            longitude={venue.longitude}
            onLatitudeChange={(latitude) => onChange({ latitude })}
            onLongitudeChange={(longitude) => onChange({ longitude })}
            onCoordinatesChange={(latitude, longitude) => onChange({ latitude, longitude })}
            latitudeError={errorFor('latitude')}
            longitudeError={errorFor('longitude')}
            data-form-field-latitude={`${errorPrefix}.latitude`}
            data-form-field-longitude={`${errorPrefix}.longitude`}
          />
        </Suspense>
      </div>
      <DateTimeInput
        label={t('venueRepeaterEventStarts')}
        name="venue_start"
        value={venue.start_at}
        onChange={(event) => onChange({ start_at: event.target.value })}
        required
        error={errorFor('start_at')}
        {...formFieldProps(`${errorPrefix}.start_at`)}
      />
      <DateTimeInput
        label={t('venueRepeaterEventEnds')}
        name="venue_end"
        value={venue.end_at}
        onChange={(event) => onChange({ end_at: event.target.value })}
        required
        error={errorFor('end_at')}
        {...formFieldProps(`${errorPrefix}.end_at`)}
      />
      <DateTimeInput
        label={t('venueRepeaterRegistrationOpens')}
        name="venue_reg_open"
        value={venue.registration_opens_at}
        onChange={(event) => onChange({ registration_opens_at: event.target.value })}
        required
        error={errorFor('registration_opens_at')}
        {...formFieldProps(`${errorPrefix}.registration_opens_at`)}
      />
      <DateTimeInput
        label={t('venueRepeaterRegistrationCloses')}
        name="venue_reg_close"
        value={venue.registration_closes_at}
        onChange={(event) => onChange({ registration_closes_at: event.target.value })}
        required
        error={errorFor('registration_closes_at')}
        {...formFieldProps(`${errorPrefix}.registration_closes_at`)}
      />
    </div>
  )
}
