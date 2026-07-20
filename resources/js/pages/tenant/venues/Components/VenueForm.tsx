import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import CheckboxInput from '@/components/forms/CheckboxInput'
import { useLocale } from '@/hooks/useLocale'
import type { LocalizedText, VenueDetail } from '@/types/phase6'

export type VenueFormValues = {
  name: LocalizedText
  description: LocalizedText
  address: LocalizedText
  country_code: string
  city_code: string
  timezone: string
  business_contact_name: string
  business_contact_email: string
  business_contact_phone: string
  publish_contact: boolean
}

type Props = {
  venue?: VenueDetail | null
  values: VenueFormValues
  onChange: (values: VenueFormValues) => void
  errors?: Record<string, string>
  readOnly?: boolean
}

export default function VenueForm({ values, onChange, errors = {}, readOnly = false }: Props) {
  const { t } = useLocale()

  function patch(partial: Partial<VenueFormValues>) {
    onChange({ ...values, ...partial })
  }

  return (
    <form className="ta-card grid gap-4 md:grid-cols-2" aria-label={t('venueDetails')}>
      <TextInput
        label={`${t('venueName')} (EN)`}
        name="name.en"
        value={values.name.en}
        onChange={(event) => patch({ name: { ...values.name, en: event.target.value } })}
        error={errors['name.en']}
        disabled={readOnly}
        required
      />
      <TextInput
        label={`${t('venueName')} (AR)`}
        name="name.ar"
        value={values.name.ar}
        onChange={(event) => patch({ name: { ...values.name, ar: event.target.value } })}
        error={errors['name.ar']}
        disabled={readOnly}
        required
      />
      <div className="md:col-span-2">
        <TextareaInput
          label={`${t('venueDetails')} (EN)`}
          name="description.en"
          value={values.description.en}
          onChange={(event) => patch({ description: { ...values.description, en: event.target.value } })}
          error={errors['description.en']}
          disabled={readOnly}
        />
      </div>
      <div className="md:col-span-2">
        <TextareaInput
          label={`${t('venueDetails')} (AR)`}
          name="description.ar"
          value={values.description.ar}
          onChange={(event) => patch({ description: { ...values.description, ar: event.target.value } })}
          error={errors['description.ar']}
          disabled={readOnly}
        />
      </div>
      <TextInput
        label={`${t('venueLocation')} (EN)`}
        name="address.en"
        value={values.address.en}
        onChange={(event) => patch({ address: { ...values.address, en: event.target.value } })}
        error={errors['address.en']}
        disabled={readOnly}
        required
      />
      <TextInput
        label={`${t('venueLocation')} (AR)`}
        name="address.ar"
        value={values.address.ar}
        onChange={(event) => patch({ address: { ...values.address, ar: event.target.value } })}
        error={errors['address.ar']}
        disabled={readOnly}
        required
      />
      <TextInput
        label={t('filterCountry')}
        name="country_code"
        value={values.country_code}
        onChange={(event) => patch({ country_code: event.target.value.toUpperCase() })}
        error={errors.country_code}
        disabled={readOnly}
        required
      />
      <TextInput
        label={t('filterCity')}
        name="city_code"
        value={values.city_code}
        onChange={(event) => patch({ city_code: event.target.value })}
        error={errors.city_code}
        disabled={readOnly}
        required
      />
      <TextInput
        label={t('venueTimezone')}
        name="timezone"
        value={values.timezone}
        onChange={(event) => patch({ timezone: event.target.value })}
        error={errors.timezone}
        disabled={readOnly}
        required
      />
      <TextInput
        label={t('venueContactName')}
        name="business_contact_name"
        value={values.business_contact_name}
        onChange={(event) => patch({ business_contact_name: event.target.value })}
        error={errors.business_contact_name}
        disabled={readOnly}
      />
      <TextInput
        label={t('venueContactEmail')}
        name="business_contact_email"
        type="email"
        value={values.business_contact_email}
        onChange={(event) => patch({ business_contact_email: event.target.value })}
        error={errors.business_contact_email}
        disabled={readOnly}
      />
      <TextInput
        label={t('venueContactPhone')}
        name="business_contact_phone"
        value={values.business_contact_phone}
        onChange={(event) => patch({ business_contact_phone: event.target.value })}
        error={errors.business_contact_phone}
        disabled={readOnly}
      />
      <CheckboxInput
        label={t('venuePublishContact')}
        name="publish_contact"
        checked={values.publish_contact}
        onChange={(event) => patch({ publish_contact: event.target.checked })}
        disabled={readOnly}
      />
    </form>
  )
}

export function emptyVenueFormValues(): VenueFormValues {
  return {
    name: { en: '', ar: '' },
    description: { en: '', ar: '' },
    address: { en: '', ar: '' },
    country_code: '',
    city_code: '',
    timezone: 'Africa/Cairo',
    business_contact_name: '',
    business_contact_email: '',
    business_contact_phone: '',
    publish_contact: false,
  }
}
