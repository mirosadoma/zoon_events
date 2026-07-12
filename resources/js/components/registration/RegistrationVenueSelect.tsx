import type { RegistrationHeroVenue } from '@/components/registration/RegistrationEventHero'
import { ValidationError } from '@/components/forms/TextInput'
import { FORM_FIELD_INVALID_CLASS } from '@/lib/formFieldStyles'
import { formatVenueSelectLabel } from '@/lib/venueLabels'

type Props = {
  locale: 'en' | 'ar'
  venues: RegistrationHeroVenue[]
  value: string
  onChange: (venueId: string) => void
  disabled?: boolean
  error?: string
}

export default function RegistrationVenueSelect({ locale, venues, value, onChange, disabled = false, error }: Props) {
  const rtl = locale === 'ar'
  const invalidClass = error ? FORM_FIELD_INVALID_CLASS : ''

  if (venues.length === 0) {
    return null
  }

  return (
    <div className="registration-venue-select">
      <label className="registration-venue-select-label" htmlFor="event_venue_id">
        {rtl ? 'الموقع - التاريخ' : 'Location - Date'}
      </label>
      <select
        id="event_venue_id"
        name="event_venue_id"
        className={`registration-select-control ${invalidClass}`}
        value={value}
        onChange={(changeEvent) => onChange(changeEvent.target.value)}
        required={!disabled}
        disabled={disabled}
        data-form-field="event_venue_id"
        aria-invalid={error ? 'true' : undefined}
      >
        <option value="">
          {rtl ? 'اختر تاريخ الفعالية' : 'Select Event Date'}
        </option>
        {venues.map((venue) => (
          <option key={venue.id} value={venue.id}>
            {formatVenueSelectLabel(venue, locale)}
          </option>
        ))}
      </select>
      {error ? <ValidationError message={error} /> : null}
    </div>
  )
}
