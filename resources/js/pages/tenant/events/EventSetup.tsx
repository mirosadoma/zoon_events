import { FormEvent, useMemo, useState } from 'react'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import DashboardLayout from '@/layouts/DashboardLayout'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import SearchableSelect from '@/components/forms/SearchableSelect'
import VenueRepeater, { emptyVenueRow, venueRowsFromEvent, type VenueFormRow } from '@/components/forms/VenueRepeater'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'

type TimezoneOption = {
  identifier: string
  name_en: string
  name_ar: string
  region_en: string
  country_en: string
  country_ar: string
  utc_offset: string
}

type CountryOption = {
  id: string
  code: string
  name_en: string
  name_ar: string
  cities: Array<{ id: string; name_en: string; name_ar: string }>
}

type EventVenuePayload = {
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
}

type EventSetupProps = {
  tenantId: string
  timezones?: TimezoneOption[]
  countries?: CountryOption[]
  event: {
    id: string | null
    slug: string
    name: { en: string; ar: string }
    description: { en: string; ar: string }
    status: string
    tier: string
    timezone: string
    start_at: string | null
    end_at: string | null
    registration_opens_at: string | null
    registration_closes_at: string | null
    capacity: number | null
    location_name?: { en: string; ar: string }
    location_address?: { en: string; ar: string }
    brand_reference: string | null
    domain_reference: string | null
    venues?: EventVenuePayload[]
    readiness: string[]
  }
  can: {
    manage: boolean
    publish: boolean
  }
}

type EventSetupErrors = Record<string, string>

type EventFormState = {
  slug: string
  name_en: string
  name_ar: string
  description_en: string
  description_ar: string
  timezone: string
  capacity: string
  brand_reference: string
  domain_reference: string
}

function buildVenuePayload(venues: VenueFormRow[]) {
  return venues
    .filter((venue) => venue.name_en.trim() !== '' && venue.name_ar.trim() !== '')
    .map((venue) => ({
      id: venue.id ? Number(venue.id) : undefined,
      country_id: venue.country_id ? Number(venue.country_id) : null,
      city_id: venue.city_id ? Number(venue.city_id) : null,
      name: { en: venue.name_en, ar: venue.name_ar },
      location_address: venue.location_address || null,
      latitude: venue.latitude === '' ? null : Number(venue.latitude),
      longitude: venue.longitude === '' ? null : Number(venue.longitude),
      start_at: venue.start_at || null,
      end_at: venue.end_at || null,
      registration_opens_at: venue.registration_opens_at || null,
      registration_closes_at: venue.registration_closes_at || null,
    }))
}

export default function EventSetup({ tenantId, event, timezones = [], countries = [], can }: EventSetupProps) {
  const { locale } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const { toast } = useToast()
  const title = event.id ? event.name[locale] : (locale === 'ar' ? 'فعالية جديدة' : 'New event')
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<EventSetupErrors>({})
  const [venues, setVenues] = useState<VenueFormRow[]>(
    () => venueRowsFromEvent(event.venues ?? []).length > 0 ? venueRowsFromEvent(event.venues ?? []) : [emptyVenueRow()],
  )
  const [form, setForm] = useState<EventFormState>({
    slug: event.slug,
    name_en: event.name.en,
    name_ar: event.name.ar,
    description_en: event.description.en,
    description_ar: event.description.ar,
    timezone: event.timezone,
    capacity: event.capacity === null ? '' : String(event.capacity),
    brand_reference: event.brand_reference ?? '',
    domain_reference: event.domain_reference ?? '',
  })

  const timezoneOptions = useMemo(
    () => timezones.map((timezone) => {
      const country = locale === 'ar' ? timezone.country_ar : timezone.country_en

      return {
        value: timezone.identifier,
        label: locale === 'ar' ? timezone.name_ar : timezone.name_en,
        hint: [timezone.region_en, country, `UTC${timezone.utc_offset}`].filter(Boolean).join(' · '),
        searchText: `${timezone.name_en} ${timezone.name_ar} ${timezone.identifier} ${timezone.region_en} ${timezone.country_en} ${timezone.country_ar}`,
      }
    }),
    [locale, timezones],
  )

  function fieldError(path: string): string | undefined {
    return errors[path]
  }

  async function handleSubmit(submitEvent: FormEvent<HTMLFormElement>) {
    submitEvent.preventDefault()
    if (!can.manage || submitting) return

    setSubmitting(true)
    setErrors({})

    const payload = {
      slug: form.slug,
      name: { en: form.name_en, ar: form.name_ar },
      description: { en: form.description_en || null, ar: form.description_ar || null },
      timezone: form.timezone,
      capacity: form.capacity === '' ? null : Number(form.capacity),
      brand_reference: form.brand_reference || null,
      domain_reference: form.domain_reference || null,
      venues: buildVenuePayload(venues),
    }

    const isCreate = event.id === null
    const url = isCreate ? '/api/v1/tenant/events' : `/api/v1/tenant/events/${event.id}`
    const method = isCreate ? 'POST' : 'PATCH'

    try {
      const body = await apiFetch<{ id?: string; data?: { id?: string } }>(url, {
        method,
        tenantId,
        idempotency: true,
        body: JSON.stringify(payload),
      })

      const createdId = String(body.data?.id ?? body.id ?? event.id ?? '')
      toast(locale === 'ar' ? 'تم حفظ الفعالية.' : 'Event saved.', 'success')
      if (createdId) {
        localizedRouter.visit(`/tenant/events/${createdId}`)
      } else {
        localizedRouter.visit('/tenant/events')
      }
    } catch (error) {
      if (error instanceof ApiFetchError) {
        setErrors(error.errors)
        toast(error.message || (locale === 'ar' ? 'تعذر حفظ الفعالية.' : 'Failed to save event.'), 'error')
      } else {
        toast(locale === 'ar' ? 'تعذر حفظ الفعالية.' : 'Failed to save event.', 'error')
      }
      setSubmitting(false)
    }
  }

  return (
    <DashboardLayout title={title}>
      <PageHeader
        title={title}
        description={locale === 'ar' ? 'إعداد بيانات الفعالية الأساسية.' : 'Configure core event details.'}
        breadcrumbs={[
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: title },
        ]}
      />
      <PageContent>
        <form className="state-panel space-y-6" onSubmit={handleSubmit}>
          <div className="flex flex-wrap items-center gap-3">
            <StatusBadge status={event.status} />
          </div>
          <div className="grid gap-4 md:grid-cols-2">
            <TextInput
              label="Slug"
              name="slug"
              value={form.slug}
              onChange={(e) => setForm((current) => ({ ...current, slug: e.target.value }))}
              required
              error={fieldError('slug')}
            />
            <SearchableSelect
              label={locale === 'ar' ? 'المنطقة الزمنية' : 'Timezone'}
              value={form.timezone}
              onChange={(timezone) => setForm((current) => ({ ...current, timezone }))}
              options={timezoneOptions}
              placeholder={locale === 'ar' ? 'ابحث عن منطقة زمنية' : 'Search timezone'}
              error={fieldError('timezone')}
            />
            <TextInput
              label={locale === 'ar' ? 'الاسم بالإنجليزية' : 'English name'}
              name="name_en"
              value={form.name_en}
              onChange={(e) => setForm((current) => ({ ...current, name_en: e.target.value }))}
              required
              error={fieldError('name.en')}
            />
            <TextInput
              label={locale === 'ar' ? 'الاسم بالعربية' : 'Arabic name'}
              name="name_ar"
              value={form.name_ar}
              onChange={(e) => setForm((current) => ({ ...current, name_ar: e.target.value }))}
              required
              error={fieldError('name.ar')}
            />
            <TextInput
              label={locale === 'ar' ? 'السعة' : 'Capacity'}
              name="capacity"
              type="number"
              min={1}
              value={form.capacity}
              onChange={(e) => setForm((current) => ({ ...current, capacity: e.target.value }))}
              required
              error={fieldError('capacity')}
            />
            <TextInput
              label={locale === 'ar' ? 'مرجع العلامة التجارية' : 'Brand reference'}
              name="brand_reference"
              value={form.brand_reference}
              onChange={(e) => setForm((current) => ({ ...current, brand_reference: e.target.value }))}
              error={fieldError('brand_reference')}
            />
            <TextInput
              label={locale === 'ar' ? 'نطاق الفعالية' : 'Domain reference'}
              name="domain_reference"
              value={form.domain_reference}
              onChange={(e) => setForm((current) => ({ ...current, domain_reference: e.target.value }))}
              error={fieldError('domain_reference')}
            />
            <TextareaInput
              label={locale === 'ar' ? 'الوصف بالإنجليزية' : 'Description (EN)'}
              name="description_en"
              value={form.description_en}
              onChange={(e) => setForm((current) => ({ ...current, description_en: e.target.value }))}
              error={fieldError('description.en')}
            />
            <TextareaInput
              label={locale === 'ar' ? 'الوصف بالعربية' : 'Description (AR)'}
              name="description_ar"
              value={form.description_ar}
              onChange={(e) => setForm((current) => ({ ...current, description_ar: e.target.value }))}
              error={fieldError('description.ar')}
            />
          </div>

          <VenueRepeater
            venues={venues}
            countries={countries}
            onChange={setVenues}
            errors={errors}
          />

          {event.readiness.length > 0 && (
            <section aria-labelledby="readiness-heading">
              <h2 id="readiness-heading" className="text-lg font-semibold">
                {locale === 'ar' ? 'جاهزية النشر' : 'Publication readiness'}
              </h2>
              <ul className="mt-2 list-disc ps-5 text-sm text-slate-600">
                {event.readiness.map((item) => <li key={item}>{item}</li>)}
              </ul>
            </section>
          )}
          <div className="flex flex-wrap gap-3">
            {can.manage && (
              <SubmitButtonWithLoader
                label={locale === 'ar' ? 'حفظ التغييرات' : 'Save changes'}
                loading={submitting}
              />
            )}
            <PermissionGate permission="event.publish">
              <SubmitButtonWithLoader
                label={locale === 'ar' ? 'نشر' : 'Publish'}
                type="button"
                disabled={event.readiness.length > 0}
              />
            </PermissionGate>
          </div>
        </form>
      </PageContent>
    </DashboardLayout>
  )
}
