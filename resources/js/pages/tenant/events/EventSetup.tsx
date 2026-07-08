import { FormEvent, useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import DateTimeInput from '@/components/forms/DateTimeInput'
import SelectInput from '@/components/forms/SelectInput'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'

type EventSetupProps = {
  tenantId: string
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
    location_name: { en: string; ar: string }
    location_address: { en: string; ar: string }
    brand_reference: string | null
    domain_reference: string | null
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
  start_at: string
  end_at: string
  registration_opens_at: string
  registration_closes_at: string
  capacity: string
  location_name_en: string
  location_name_ar: string
  location_address_en: string
  location_address_ar: string
  brand_reference: string
  domain_reference: string
}

function toLocalDateTime(value: string | null): string {
  if (!value) return ''
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return ''
  const pad = (n: number) => n.toString().padStart(2, '0')
  return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}T${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`
}

export default function EventSetup({ tenantId, event, can }: EventSetupProps) {
  const { locale } = useLocale()
  const { toast } = useToast()
  const title = event.id ? event.name[locale] : (locale === 'ar' ? 'فعالية جديدة' : 'New event')
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<EventSetupErrors>({})
  const [form, setForm] = useState<EventFormState>({
    slug: event.slug,
    name_en: event.name.en,
    name_ar: event.name.ar,
    description_en: event.description.en,
    description_ar: event.description.ar,
    tier: event.tier,
    timezone: event.timezone,
    start_at: toLocalDateTime(event.start_at),
    end_at: toLocalDateTime(event.end_at),
    registration_opens_at: toLocalDateTime(event.registration_opens_at),
    registration_closes_at: toLocalDateTime(event.registration_closes_at),
    capacity: event.capacity === null ? '' : String(event.capacity),
    location_name_en: event.location_name.en,
    location_name_ar: event.location_name.ar,
    location_address_en: event.location_address.en,
    location_address_ar: event.location_address.ar,
    brand_reference: event.brand_reference ?? '',
    domain_reference: event.domain_reference ?? '',
  })

  const tierOptions = useMemo(
    () => [
      { value: 'public', label: locale === 'ar' ? 'عام' : 'Public' },
      { value: 'corporate', label: locale === 'ar' ? 'مؤسسي' : 'Corporate' },
      { value: 'vip', label: 'VIP' },
      { value: 'vvip', label: 'VVIP' },
    ],
    [locale],
  )

  function fieldError(path: string): string | undefined {
    return errors[path]
  }

  function extractErrors(body: unknown): EventSetupErrors {
    if (typeof body !== 'object' || body === null) return {}
    const maybe = body as { errors?: Record<string, string[] | string> }
    if (!maybe.errors) return {}
    const mapped: EventSetupErrors = {}
    Object.entries(maybe.errors).forEach(([key, value]) => {
      if (Array.isArray(value)) {
        mapped[key] = String(value[0] ?? '')
      } else {
        mapped[key] = String(value)
      }
    })
    return mapped
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
      tier: form.tier,
      timezone: form.timezone,
      start_at: form.start_at || null,
      end_at: form.end_at || null,
      registration_opens_at: form.registration_opens_at || null,
      registration_closes_at: form.registration_closes_at || null,
      capacity: form.capacity === '' ? null : Number(form.capacity),
      location_name: { en: form.location_name_en || null, ar: form.location_name_ar || null },
      location_address: { en: form.location_address_en || null, ar: form.location_address_ar || null },
      brand_reference: form.brand_reference || null,
      domain_reference: form.domain_reference || null,
    }

    const isCreate = event.id === null
    const url = isCreate ? '/api/v1/tenant/events' : `/api/v1/tenant/events/${event.id}`
    const method = isCreate ? 'POST' : 'PATCH'

    try {
      const response = await fetch(url, {
        method,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Tenant-ID': tenantId,
          'Idempotency-Key': crypto.randomUUID(),
        },
        body: JSON.stringify(payload),
      })
      const body = await response.json()

      if (!response.ok) {
        setErrors(extractErrors(body))
        toast(locale === 'ar' ? 'تعذر حفظ الفعالية.' : 'Failed to save event.', 'error')
        setSubmitting(false)
        return
      }

      const createdId = String(body?.data?.id ?? event.id ?? '')
      toast(locale === 'ar' ? 'تم حفظ الفعالية.' : 'Event saved.', 'success')
      if (createdId) {
        router.visit(`/tenant/events/${createdId}`)
      } else {
        router.visit('/tenant/events')
      }
    } catch {
      toast(locale === 'ar' ? 'تعذر حفظ الفعالية.' : 'Failed to save event.', 'error')
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
        <form className="state-panel space-y-4" onSubmit={handleSubmit}>
          <div className="flex flex-wrap items-center gap-3">
            <StatusBadge status={event.status} />
            <span className="text-sm text-slate-600">{event.tier}</span>
          </div>
          <div className="grid gap-4 md:grid-cols-2">
            <TextInput
              label={locale === 'ar' ? 'المعرّف المختصر' : 'Slug'}
              name="slug"
              value={form.slug}
              onChange={(e) => setForm((current) => ({ ...current, slug: e.target.value }))}
              required
              error={fieldError('slug')}
            />
            <SelectInput
              label={locale === 'ar' ? 'الفئة' : 'Tier'}
              name="tier"
              value={form.tier}
              onChange={(e) => setForm((current) => ({ ...current, tier: e.target.value }))}
              options={tierOptions}
              error={fieldError('tier')}
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
              label={locale === 'ar' ? 'المنطقة الزمنية' : 'Timezone'}
              name="timezone"
              value={form.timezone}
              onChange={(e) => setForm((current) => ({ ...current, timezone: e.target.value }))}
              required
              error={fieldError('timezone')}
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
            <DateTimeInput
              label={locale === 'ar' ? 'بداية الفعالية' : 'Event starts'}
              name="start_at"
              value={form.start_at}
              onChange={(e) => setForm((current) => ({ ...current, start_at: e.target.value }))}
              required
              error={fieldError('start_at')}
            />
            <DateTimeInput
              label={locale === 'ar' ? 'نهاية الفعالية' : 'Event ends'}
              name="end_at"
              value={form.end_at}
              onChange={(e) => setForm((current) => ({ ...current, end_at: e.target.value }))}
              required
              error={fieldError('end_at')}
            />
            <DateTimeInput
              label={locale === 'ar' ? 'فتح التسجيل' : 'Registration opens'}
              name="registration_opens_at"
              value={form.registration_opens_at}
              onChange={(e) => setForm((current) => ({ ...current, registration_opens_at: e.target.value }))}
              required
              error={fieldError('registration_opens_at')}
            />
            <DateTimeInput
              label={locale === 'ar' ? 'إغلاق التسجيل' : 'Registration closes'}
              name="registration_closes_at"
              value={form.registration_closes_at}
              onChange={(e) => setForm((current) => ({ ...current, registration_closes_at: e.target.value }))}
              required
              error={fieldError('registration_closes_at')}
            />
            <TextInput
              label={locale === 'ar' ? 'اسم الموقع (EN)' : 'Location name (EN)'}
              name="location_name_en"
              value={form.location_name_en}
              onChange={(e) => setForm((current) => ({ ...current, location_name_en: e.target.value }))}
              error={fieldError('location_name.en')}
            />
            <TextInput
              label={locale === 'ar' ? 'اسم الموقع (AR)' : 'Location name (AR)'}
              name="location_name_ar"
              value={form.location_name_ar}
              onChange={(e) => setForm((current) => ({ ...current, location_name_ar: e.target.value }))}
              error={fieldError('location_name.ar')}
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
            <TextareaInput
              label={locale === 'ar' ? 'عنوان الموقع (EN)' : 'Location address (EN)'}
              name="location_address_en"
              value={form.location_address_en}
              onChange={(e) => setForm((current) => ({ ...current, location_address_en: e.target.value }))}
              error={fieldError('location_address.en')}
            />
            <TextareaInput
              label={locale === 'ar' ? 'عنوان الموقع (AR)' : 'Location address (AR)'}
              name="location_address_ar"
              value={form.location_address_ar}
              onChange={(e) => setForm((current) => ({ ...current, location_address_ar: e.target.value }))}
              error={fieldError('location_address.ar')}
            />
          </div>
          {event.readiness.length > 0 && (
            <section aria-labelledby="readiness-heading">
              <h2 id="readiness-heading" className="text-lg font-semibold">{locale === 'ar' ? 'جاهزية النشر' : 'Publication readiness'}</h2>
              <ul className="mt-2 list-disc ps-5 text-sm text-slate-600">
                {event.readiness.map((item) => <li key={item}>{item}</li>)}
              </ul>
            </section>
          )}
          <div className="flex flex-wrap gap-3">
            {can.manage && <SubmitButtonWithLoader label={locale === 'ar' ? 'حفظ التغييرات' : 'Save changes'} loading={submitting} />}
            <PermissionGate permission="event.publish">
              <SubmitButtonWithLoader label={locale === 'ar' ? 'نشر' : 'Publish'} type="button" disabled={event.readiness.length > 0} />
            </PermissionGate>
          </div>
        </form>
      </PageContent>
    </DashboardLayout>
  )
}
