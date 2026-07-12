import { FormEvent, useMemo, useState } from 'react'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import DashboardLayout from '@/layouts/DashboardLayout'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import SearchableSelect from '@/components/forms/SearchableSelect'
import SelectInput from '@/components/forms/SelectInput'
import FileInput from '@/components/forms/FileInput'
import VenueRepeater, { emptyVenueRow, venueRowsFromEvent, type VenueFormRow } from '@/components/forms/VenueRepeater'
import ValidationHintPopover from '@/components/feedback/ValidationHintPopover'
import PublishReadinessList from '@/components/events/PublishReadinessList'
import StatusBadge from '@/components/status/StatusBadge'
import { useFormValidation } from '@/hooks/useFormValidation'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { appendToFormData } from '@/lib/appendToFormData'
import { EVENT_SETUP_FIELD_LABELS, formFieldProps } from '@/lib/formatValidationErrors'
import { EVENT_TIERS } from '@/lib/eventOptions'

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

type OrganizerCandidate = {
  id: string
  name: string
  email: string
}

type EventImageRow = {
  id: string
  url: string
  path: string
  sort_order: number
}

type EventSetupProps = {
  tenantId: string
  timezones?: TimezoneOption[]
  countries?: CountryOption[]
  requiresOrganizerSelection?: boolean
  organizerCandidates?: OrganizerCandidate[]
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
    organizer_user_id?: string | null
    organizer?: OrganizerCandidate | null
    main_image?: { id: null; url: string; path: string } | null
    images?: EventImageRow[]
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
  tier: string
  timezone: string
  capacity: string
  brand_reference: string
  domain_reference: string
  organizer_user_id: string
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

export default function EventSetup({
  tenantId,
  event,
  timezones = [],
  countries = [],
  requiresOrganizerSelection = false,
  organizerCandidates = [],
  can,
}: EventSetupProps) {
  const { locale, t } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const { toast } = useToast()
  const validation = useFormValidation({ titleKey: 'couldNotSaveEvent', fieldLabels: EVENT_SETUP_FIELD_LABELS })
  const title = event.id ? event.name[locale] : (locale === 'ar' ? 'فعالية جديدة' : 'New event')
  const isCreate = event.id === null
  const [submitting, setSubmitting] = useState(false)
  const [venues, setVenues] = useState<VenueFormRow[]>(
    () => venueRowsFromEvent(event.venues ?? []).length > 0 ? venueRowsFromEvent(event.venues ?? []) : [emptyVenueRow()],
  )
  const [mainImageFile, setMainImageFile] = useState<File | null>(null)
  const [mainImagePreview, setMainImagePreview] = useState<string | null>(event.main_image?.url ?? null)
  const [existingGallery, setExistingGallery] = useState<EventImageRow[]>(event.images ?? [])
  const [newGalleryFiles, setNewGalleryFiles] = useState<File[]>([])
  const [newGalleryPreviews, setNewGalleryPreviews] = useState<string[]>([])
  const [removedImageIds, setRemovedImageIds] = useState<string[]>([])
  const [form, setForm] = useState<EventFormState>({
    slug: event.slug,
    name_en: event.name.en,
    name_ar: event.name.ar,
    description_en: event.description.en,
    description_ar: event.description.ar,
    tier: event.tier,
    timezone: event.timezone,
    capacity: event.capacity === null ? '' : String(event.capacity),
    brand_reference: event.brand_reference ?? '',
    domain_reference: event.domain_reference ?? '',
    organizer_user_id: event.organizer_user_id ?? organizerCandidates[0]?.id ?? '',
  })

  const tierOptions = useMemo(
    () => EVENT_TIERS.map((tier) => ({
      value: tier.value,
      label: locale === 'ar' ? tier.label_ar : tier.label_en,
    })),
    [locale],
  )

  const organizerOptions = useMemo(() => {
    const base = organizerCandidates.map((candidate) => ({
      value: candidate.id,
      label: `${candidate.name} (${candidate.email})`,
    }))

    if (event.organizer && !base.some((option) => option.value === event.organizer!.id)) {
      base.unshift({
        value: event.organizer.id,
        label: `${event.organizer.name} (${event.organizer.email})`,
      })
    }

    return base
  }, [event.organizer, organizerCandidates])

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
    return validation.fieldError(path)
  }

  function handleMainImageChange(fileList: FileList | null) {
    const file = fileList?.[0] ?? null
    setMainImageFile(file)
    setMainImagePreview(file ? URL.createObjectURL(file) : (event.main_image?.url ?? null))
  }

  function handleGalleryChange(fileList: FileList | null) {
    const files = fileList ? Array.from(fileList) : []
    setNewGalleryFiles((current) => [...current, ...files])
    setNewGalleryPreviews((current) => [...current, ...files.map((file) => URL.createObjectURL(file))])
  }

  function removeExistingGalleryImage(imageId: string) {
    setExistingGallery((current) => current.filter((image) => image.id !== imageId))
    setRemovedImageIds((current) => [...current, imageId])
  }

  function removeNewGalleryImage(index: number) {
    setNewGalleryFiles((current) => current.filter((_, currentIndex) => currentIndex !== index))
    setNewGalleryPreviews((current) => current.filter((_, currentIndex) => currentIndex !== index))
  }

  async function handleSubmit(submitEvent: FormEvent<HTMLFormElement>) {
    submitEvent.preventDefault()
    if (!can.manage || submitting) return

    setSubmitting(true)
    validation.clearValidation()

    const isCreateSubmit = event.id === null
    const hasMainImage = Boolean(mainImageFile || event.main_image?.url)

    if (!hasMainImage) {
      validation.applyErrors({ main_image: t('eventMainImageRequired') })
      setSubmitting(false)
      return
    }

    const payload = {
      slug: form.slug,
      name: { en: form.name_en, ar: form.name_ar },
      description: { en: form.description_en || null, ar: form.description_ar || null },
      tier: form.tier,
      timezone: form.timezone,
      capacity: form.capacity === '' ? null : Number(form.capacity),
      brand_reference: form.brand_reference || null,
      domain_reference: form.domain_reference || null,
      venues: buildVenuePayload(venues),
      ...(requiresOrganizerSelection ? { organizer_user_id: Number(form.organizer_user_id) } : {}),
    }
    const url = isCreateSubmit ? '/api/v1/tenant/events' : `/api/v1/tenant/events/${event.id}`
    const method = isCreateSubmit ? 'POST' : 'PATCH'
    const hasMediaChanges = Boolean(mainImageFile || newGalleryFiles.length > 0 || removedImageIds.length > 0)

    try {
      let body: { id?: string; data?: { id?: string } }

      if (isCreateSubmit || hasMediaChanges) {
        const formData = new FormData()
        appendToFormData(formData, 'slug', payload.slug)
        appendToFormData(formData, 'name', payload.name)
        appendToFormData(formData, 'description', payload.description)
        appendToFormData(formData, 'tier', payload.tier)
        appendToFormData(formData, 'timezone', payload.timezone)
        appendToFormData(formData, 'capacity', payload.capacity)
        appendToFormData(formData, 'brand_reference', payload.brand_reference)
        appendToFormData(formData, 'domain_reference', payload.domain_reference)
        appendToFormData(formData, 'venues', payload.venues)
        if ('organizer_user_id' in payload) {
          appendToFormData(formData, 'organizer_user_id', payload.organizer_user_id)
        }
        if (mainImageFile) {
          formData.append('main_image', mainImageFile)
        }
        newGalleryFiles.forEach((file) => formData.append('images[]', file))
        removedImageIds.forEach((imageId) => formData.append('remove_image_ids[]', imageId))

        body = await apiFetch<{ id?: string; data?: { id?: string } }>(url, {
          method,
          tenantId,
          idempotency: true,
          body: formData,
        })
      } else {
        body = await apiFetch<{ id?: string; data?: { id?: string } }>(url, {
          method,
          tenantId,
          idempotency: true,
          body: payload,
        })
      }

      const createdId = String(body.data?.id ?? body.id ?? event.id ?? '')
      toast(locale === 'ar' ? 'تم حفظ الفعالية.' : 'Event saved.', 'success')
      if (createdId) {
        localizedRouter.visit(`/tenant/events/${createdId}`)
      } else {
        localizedRouter.visit('/tenant/events')
      }
    } catch (error) {
      if (validation.applyApiError(error)) {
        toast(error instanceof ApiFetchError ? error.message : t('couldNotSaveEvent'), 'error')
      } else if (error instanceof ApiFetchError) {
        toast(error.message || t('couldNotSaveEvent'), 'error')
      } else {
        toast(t('couldNotSaveEvent'), 'error')
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
        <form className="state-panel relative space-y-6 p-4 sm:p-6" onSubmit={handleSubmit}>
          <div className="flex flex-wrap items-center gap-3">
            <StatusBadge status={event.status} />
          </div>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <TextInput
              label="Slug"
              name="slug"
              value={form.slug}
              onChange={(e) => setForm((current) => ({ ...current, slug: e.target.value }))}
              required
              error={fieldError('slug')}
              {...formFieldProps('slug')}
            />
            <SearchableSelect
              label={locale === 'ar' ? 'المنطقة الزمنية' : 'Timezone'}
              value={form.timezone}
              onChange={(timezone) => setForm((current) => ({ ...current, timezone }))}
              options={timezoneOptions}
              placeholder={locale === 'ar' ? 'ابحث عن منطقة زمنية' : 'Search timezone'}
              error={fieldError('timezone')}
              data-form-field="timezone"
            />
            <SelectInput
              label={locale === 'ar' ? 'فئة الفعالية' : 'Event tier'}
              name="tier"
              value={form.tier}
              onChange={(e) => setForm((current) => ({ ...current, tier: e.target.value }))}
              options={tierOptions}
              required
              error={fieldError('tier')}
              {...formFieldProps('tier')}
            />
            {requiresOrganizerSelection ? (
              <SelectInput
                label={locale === 'ar' ? 'منظم الفعالية' : 'Event organizer'}
                name="organizer_user_id"
                value={form.organizer_user_id}
                onChange={(e) => setForm((current) => ({ ...current, organizer_user_id: e.target.value }))}
                options={organizerOptions}
                required
                error={fieldError('organizer_user_id')}
                {...formFieldProps('organizer_user_id')}
              />
            ) : event.organizer ? (
              <TextInput
                label={locale === 'ar' ? 'منظم الفعالية' : 'Event organizer'}
                name="organizer_display"
                value={`${event.organizer.name} (${event.organizer.email})`}
                readOnly
              />
            ) : null}
            <TextInput
              label={locale === 'ar' ? 'الاسم بالإنجليزية' : 'English name'}
              name="name_en"
              value={form.name_en}
              onChange={(e) => setForm((current) => ({ ...current, name_en: e.target.value }))}
              required
              error={fieldError('name.en')}
              {...formFieldProps('name.en')}
            />
            <TextInput
              label={locale === 'ar' ? 'الاسم بالعربية' : 'Arabic name'}
              name="name_ar"
              value={form.name_ar}
              onChange={(e) => setForm((current) => ({ ...current, name_ar: e.target.value }))}
              required
              error={fieldError('name.ar')}
              {...formFieldProps('name.ar')}
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
              {...formFieldProps('capacity')}
            />
            <TextInput
              label={locale === 'ar' ? 'مرجع العلامة التجارية' : 'Brand reference'}
              name="brand_reference"
              value={form.brand_reference}
              onChange={(e) => setForm((current) => ({ ...current, brand_reference: e.target.value }))}
              error={fieldError('brand_reference')}
              {...formFieldProps('brand_reference')}
            />
            <TextInput
              label={locale === 'ar' ? 'نطاق الفعالية' : 'Domain reference'}
              name="domain_reference"
              value={form.domain_reference}
              onChange={(e) => setForm((current) => ({ ...current, domain_reference: e.target.value }))}
              error={fieldError('domain_reference')}
              {...formFieldProps('domain_reference')}
            />
            <TextareaInput
              label={locale === 'ar' ? 'الوصف بالإنجليزية' : 'Description (EN)'}
              name="description_en"
              value={form.description_en}
              onChange={(e) => setForm((current) => ({ ...current, description_en: e.target.value }))}
              error={fieldError('description.en')}
              {...formFieldProps('description.en')}
            />
            <TextareaInput
              label={locale === 'ar' ? 'الوصف بالعربية' : 'Description (AR)'}
              name="description_ar"
              value={form.description_ar}
              onChange={(e) => setForm((current) => ({ ...current, description_ar: e.target.value }))}
              error={fieldError('description.ar')}
              {...formFieldProps('description.ar')}
            />
          </div>

          <section className="space-y-4">
            <h2 className="text-lg font-semibold">{locale === 'ar' ? 'صور الفعالية' : 'Event images'}</h2>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div className="space-y-3">
                <FileInput
                  label={locale === 'ar' ? 'الصورة الرئيسية' : 'Main image'}
                  name="main_image"
                  accept="image/png,image/jpeg,image/webp"
                  required={!event.main_image?.url}
                  error={fieldError('main_image')}
                  {...formFieldProps('main_image')}
                  onChange={(changeEvent) => handleMainImageChange(changeEvent.target.files)}
                />
                {mainImagePreview ? (
                  <img
                    src={mainImagePreview}
                    alt=""
                    className="h-40 w-full rounded-xl border border-[var(--border)] object-cover"
                  />
                ) : null}
              </div>
              <div className="space-y-3">
                <FileInput
                  label={locale === 'ar' ? 'صور إضافية' : 'Gallery images'}
                  name="images"
                  accept="image/png,image/jpeg,image/webp"
                  multiple
                  hint={locale === 'ar' ? 'اختياري — PNG أو JPG أو WebP' : 'Optional — PNG, JPG, or WebP'}
                  onChange={(changeEvent) => handleGalleryChange(changeEvent.target.files)}
                />
                {(existingGallery.length > 0 || newGalleryPreviews.length > 0) ? (
                  <div className="flex flex-wrap gap-2">
                    {existingGallery.map((image) => (
                      <div key={image.id} className="relative">
                        <img src={image.url} alt="" className="h-20 w-20 rounded-lg border border-[var(--border)] object-cover" />
                        <button
                          type="button"
                          className="absolute -end-2 -top-2 rounded-full bg-red-600 px-2 py-0.5 text-xs text-white"
                          onClick={() => removeExistingGalleryImage(image.id)}
                        >
                          ×
                        </button>
                      </div>
                    ))}
                    {newGalleryPreviews.map((preview, index) => (
                      <div key={preview} className="relative">
                        <img src={preview} alt="" className="h-20 w-20 rounded-lg border border-[var(--border)] object-cover" />
                        <button
                          type="button"
                          className="absolute -end-2 -top-2 rounded-full bg-red-600 px-2 py-0.5 text-xs text-white"
                          onClick={() => removeNewGalleryImage(index)}
                        >
                          ×
                        </button>
                      </div>
                    ))}
                  </div>
                ) : null}
              </div>
            </div>
          </section>

          <VenueRepeater
            venues={venues}
            countries={countries}
            onChange={setVenues}
            errors={validation.fieldErrors}
          />

          {(event.readiness ?? []).length > 0 && (
            <PublishReadinessList
              className="mt-2"
              items={event.readiness}
              eventId={event.id ?? undefined}
              title={locale === 'ar' ? 'جاهزية النشر' : 'Publication readiness'}
            />
          )}
          <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
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
                disabled={(event.readiness ?? []).length > 0}
              />
            </PermissionGate>
          </div>
        </form>
        <ValidationHintPopover {...validation.hintProps} />
      </PageContent>
    </DashboardLayout>
  )
}
