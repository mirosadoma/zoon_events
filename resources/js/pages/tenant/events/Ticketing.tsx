import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useMemo, useState } from 'react'
import { InventoryStatus } from '@/components/ticketing/InventoryStatus'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import DateTimeInput from '@/components/forms/DateTimeInput'
import SelectInput from '@/components/forms/SelectInput'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { formatMoney } from '@/lib/formatMoney'
import { localizedPath } from '@/lib/localePath'
import { ATTENDEE_TYPES, CURRENCIES, currencyLabel } from '@/lib/ticketingOptions'

type Ticket = {
  id: string
  code: string
  name: { en: string; ar: string }
  description: { en: string; ar: string }
  attendee_type: string
  price_minor: number
  currency: string
  capacity: number
  remaining_quantity: number
  sale_starts_at?: string | null
  sale_ends_at?: string | null
  status: string
  state: 'available' | 'sold_out' | 'paused' | 'conflict'
}

type Props = {
  tenantId: string
  event: {
    id: string
    name: { en: string; ar: string }
    start_at?: string | null
    end_at?: string | null
  }
  tickets: Ticket[]
}

type TicketFormState = {
  code: string
  name_en: string
  name_ar: string
  description_en: string
  description_ar: string
  attendee_type: string
  capacity: string
  price: string
  currency: string
  sale_starts_at: string
  sale_ends_at: string
}

type FormErrors = Record<string, string>

function toLocalDateTime(value: string | null | undefined): string {
  if (!value) return ''
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return ''
  const pad = (n: number) => n.toString().padStart(2, '0')

  return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}T${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`
}

function emptyForm(event: Props['event']): TicketFormState {
  return {
    code: '',
    name_en: '',
    name_ar: '',
    description_en: '',
    description_ar: '',
    attendee_type: 'general',
    capacity: '',
    price: '',
    currency: 'SAR',
    sale_starts_at: toLocalDateTime(event.start_at),
    sale_ends_at: toLocalDateTime(event.end_at),
  }
}

function ticketToForm(ticket: Ticket): TicketFormState {
  return {
    code: ticket.code,
    name_en: ticket.name.en,
    name_ar: ticket.name.ar,
    description_en: ticket.description.en,
    description_ar: ticket.description.ar,
    attendee_type: ticket.attendee_type || 'general',
    capacity: String(ticket.capacity),
    price: String((ticket.price_minor / 100).toFixed(2)),
    currency: ticket.currency,
    sale_starts_at: toLocalDateTime(ticket.sale_starts_at),
    sale_ends_at: toLocalDateTime(ticket.sale_ends_at),
  }
}

function formToPayload(form: TicketFormState, status?: string) {
  const payload: Record<string, unknown> = {
    code: form.code.toUpperCase(),
    name: { en: form.name_en, ar: form.name_ar },
    description: { en: form.description_en || null, ar: form.description_ar || null },
    attendee_type: form.attendee_type || 'general',
    capacity: Number(form.capacity),
    price_minor: Math.round(Number(form.price) * 100),
    currency: form.currency.toUpperCase(),
    sale_starts_at: form.sale_starts_at,
    sale_ends_at: form.sale_ends_at,
  }
  if (status) {
    payload.status = status
  }

  return payload
}

function readCsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)

  return match ? decodeURIComponent(match[1]) : null
}

export default function Ticketing({ tenantId, event, tickets }: Props) {
  const { locale } = useLocale()
  const { toast } = useToast()
  const [form, setForm] = useState<TicketFormState>(() => emptyForm(event))
  const [editingId, setEditingId] = useState<string | null>(null)
  const [errors, setErrors] = useState<FormErrors>({})
  const [submitting, setSubmitting] = useState<'save' | string | null>(null)

  const attendeeOptions = useMemo(
    () => ATTENDEE_TYPES.map((type) => ({
      value: type.value,
      label: locale === 'ar' ? type.label_ar : type.label_en,
    })),
    [locale],
  )

  const currencyOptions = useMemo(
    () => CURRENCIES.map((currency) => ({
      value: currency.code,
      label: locale === 'ar' ? currency.label_ar : currency.label_en,
    })),
    [locale],
  )

  const priceLabel = locale === 'ar'
    ? `السعر (${currencyLabel(form.currency, 'ar')})`
    : `Price (${currencyLabel(form.currency, 'en')})`

  function extractErrors(body: unknown): FormErrors {
    if (typeof body !== 'object' || body === null) return {}
    const maybe = body as { errors?: Record<string, string[] | string> }
    if (!maybe.errors) return {}

    const mapped: FormErrors = {}
    Object.entries(maybe.errors).forEach(([key, value]) => {
      mapped[key] = Array.isArray(value) ? String(value[0] ?? '') : String(value)
    })

    return mapped
  }

  function extractError(body: unknown, fallback: string): string {
    if (typeof body !== 'object' || body === null) return fallback
    const maybe = body as { detail?: string; message?: string; title?: string; code?: string }

    return maybe.detail ?? maybe.message ?? maybe.title ?? maybe.code ?? fallback
  }

  async function saveTicket() {
    setSubmitting('save')
    setErrors({})

    const url = editingId
      ? `/api/v1/tenant/events/${event.id}/ticket-types/${editingId}`
      : `/api/v1/tenant/events/${event.id}/ticket-types`

    const headers: Record<string, string> = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Tenant-ID': tenantId,
      'Idempotency-Key': crypto.randomUUID(),
    }
    const csrfToken = readCsrfToken()
    if (csrfToken) {
      headers['X-XSRF-TOKEN'] = csrfToken
    }

    try {
      const response = await fetch(url, {
        method: editingId ? 'PATCH' : 'POST',
        credentials: 'include',
        headers,
        body: JSON.stringify(formToPayload(form)),
      })
      const body = await response.json()

      if (!response.ok) {
        setErrors(extractErrors(body))
        toast(extractError(body, locale === 'ar' ? 'تعذر حفظ نوع التذكرة.' : 'Failed to save ticket type.'), 'error')
        setSubmitting(null)

        return
      }

      toast(
        editingId
          ? (locale === 'ar' ? 'تم تحديث نوع التذكرة.' : 'Ticket type updated.')
          : (locale === 'ar' ? 'تم إنشاء نوع التذكرة.' : 'Ticket type created.'),
        'success',
      )
      setForm(emptyForm(event))
      setEditingId(null)
      setSubmitting(null)
      router.reload()
    } catch {
      toast(locale === 'ar' ? 'تعذر حفظ نوع التذكرة.' : 'Failed to save ticket type.', 'error')
      setSubmitting(null)
    }
  }

  async function toggleStatus(ticket: Ticket, nextStatus: 'active' | 'paused') {
    setSubmitting(ticket.id)

    const headers: Record<string, string> = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Tenant-ID': tenantId,
      'Idempotency-Key': crypto.randomUUID(),
    }
    const csrfToken = readCsrfToken()
    if (csrfToken) {
      headers['X-XSRF-TOKEN'] = csrfToken
    }

    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/ticket-types/${ticket.id}`, {
        method: 'PATCH',
        credentials: 'include',
        headers,
        body: JSON.stringify(formToPayload(ticketToForm(ticket), nextStatus)),
      })
      const body = await response.json()

      if (!response.ok) {
        toast(extractError(body, locale === 'ar' ? 'تعذر تحديث الحالة.' : 'Failed to update status.'), 'error')
        setSubmitting(null)

        return
      }

      toast(
        nextStatus === 'paused'
          ? (locale === 'ar' ? 'تم إيقاف نوع التذكرة.' : 'Ticket type paused.')
          : (locale === 'ar' ? 'تم تفعيل نوع التذكرة.' : 'Ticket type activated.'),
        'success',
      )
      setSubmitting(null)
      router.reload()
    } catch {
      toast(locale === 'ar' ? 'تعذر تحديث الحالة.' : 'Failed to update status.', 'error')
      setSubmitting(null)
    }
  }

  function handleSubmit(submitEvent: FormEvent<HTMLFormElement>) {
    submitEvent.preventDefault()
    void saveTicket()
  }

  function startEdit(ticket: Ticket) {
    setEditingId(ticket.id)
    setForm(ticketToForm(ticket))
    setErrors({})
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  function cancelEdit() {
    setEditingId(null)
    setForm(emptyForm(event))
    setErrors({})
  }

  return (
    <DashboardLayout title={locale === 'ar' ? 'أنواع التذاكر' : 'Ticket types'}>
      <PageHeader
        title={locale === 'ar' ? 'أنواع التذاكر' : 'Ticket types'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: localizedPath(locale, '/tenant/events') },
          { label: event.name[locale], href: localizedPath(locale, `/tenant/events/${event.id}`) },
          { label: locale === 'ar' ? 'أنواع التذاكر' : 'Ticket types' },
        ]}
        actions={(
          <LocalizedLink className="button-secondary" href={localizedPath(locale, `/tenant/events/${event.id}/price-tiers`)}>
            {locale === 'ar' ? 'شرائح الأسعار' : 'Price tiers'}
          </LocalizedLink>
        )}
      />
      <PageContent>
        <section className="mb-8">
          <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-lg font-semibold">
              {editingId
                ? (locale === 'ar' ? 'تعديل نوع التذكرة' : 'Edit ticket type')
                : (locale === 'ar' ? 'إضافة نوع تذكرة' : 'Add ticket type')}
            </h2>
            {editingId && (
              <button type="button" className="button-secondary cursor-pointer" onClick={cancelEdit}>
                {locale === 'ar' ? 'إلغاء التعديل' : 'Cancel edit'}
              </button>
            )}
          </div>
          <form className="state-panel grid gap-4 sm:grid-cols-2" onSubmit={handleSubmit}>
            <TextInput label={locale === 'ar' ? 'الرمز' : 'Code'} name="code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} required error={errors.code} />
            <SelectInput label={locale === 'ar' ? 'نوع الحضور' : 'Attendee type'} name="attendee_type" value={form.attendee_type} onChange={(e) => setForm({ ...form, attendee_type: e.target.value })} options={attendeeOptions} />
            <TextInput label={locale === 'ar' ? 'الاسم (إنجليزي)' : 'Name (EN)'} name="name_en" value={form.name_en} onChange={(e) => setForm({ ...form, name_en: e.target.value })} required error={errors['name.en']} />
            <TextInput label={locale === 'ar' ? 'الاسم (عربي)' : 'Name (AR)'} name="name_ar" value={form.name_ar} onChange={(e) => setForm({ ...form, name_ar: e.target.value })} required error={errors['name.ar']} />
            <TextareaInput wrapperClassName="sm:col-span-1" label={locale === 'ar' ? 'الوصف (إنجليزي)' : 'Description (EN)'} name="description_en" value={form.description_en} onChange={(e) => setForm({ ...form, description_en: e.target.value })} />
            <TextareaInput wrapperClassName="sm:col-span-1" label={locale === 'ar' ? 'الوصف (عربي)' : 'Description (AR)'} name="description_ar" value={form.description_ar} onChange={(e) => setForm({ ...form, description_ar: e.target.value })} />
            <TextInput label={locale === 'ar' ? 'السعة' : 'Capacity'} name="capacity" type="number" min={1} value={form.capacity} onChange={(e) => setForm({ ...form, capacity: e.target.value })} required error={errors.capacity} />
            <SelectInput label={locale === 'ar' ? 'العملة' : 'Currency'} name="currency" value={form.currency} onChange={(e) => setForm({ ...form, currency: e.target.value })} options={currencyOptions} required error={errors.currency} />
            <TextInput label={priceLabel} name="price" type="number" min={0} step="0.01" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} required error={errors.price_minor} />
            <DateTimeInput label={locale === 'ar' ? 'بداية البيع' : 'Sale starts'} name="sale_starts_at" value={form.sale_starts_at} onChange={(e) => setForm({ ...form, sale_starts_at: e.target.value })} required error={errors.sale_starts_at} />
            <DateTimeInput label={locale === 'ar' ? 'نهاية البيع' : 'Sale ends'} name="sale_ends_at" value={form.sale_ends_at} onChange={(e) => setForm({ ...form, sale_ends_at: e.target.value })} required error={errors.sale_ends_at} />
            <div className="sm:col-span-2">
              <SubmitButtonWithLoader
                loading={submitting === 'save'}
                label={editingId ? (locale === 'ar' ? 'تحديث' : 'Update') : (locale === 'ar' ? 'إضافة' : 'Add')}
              />
            </div>
          </form>
        </section>

        {tickets.length === 0 ? (
          <EmptyState
            title={locale === 'ar' ? 'لا توجد أنواع تذاكر' : 'No ticket types yet'}
            detail={locale === 'ar' ? 'أنشئ نوع تذكرة لبدء التسعير.' : 'Create a ticket type to start pricing.'}
          />
        ) : (
          <div className="ta-table-wrap rounded-[var(--radius-card)] border border-[var(--border)] bg-[var(--surface-elevated)]">
            <table className="ta-table">
              <thead>
                <tr>
                  <th>{locale === 'ar' ? 'الاسم' : 'Name'}</th>
                  <th>{locale === 'ar' ? 'الرمز' : 'Code'}</th>
                  <th>{locale === 'ar' ? 'نوع الحضور' : 'Attendee type'}</th>
                  <th>{locale === 'ar' ? 'السعر' : 'Price'}</th>
                  <th>{locale === 'ar' ? 'المخزون' : 'Inventory'}</th>
                  <th>{locale === 'ar' ? 'الحالة' : 'Status'}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {tickets.map((ticket) => (
                  <tr key={ticket.id}>
                    <td className="font-medium">{ticket.name[locale]}</td>
                    <td>{ticket.code}</td>
                    <td>{attendeeOptions.find((option) => option.value === ticket.attendee_type)?.label ?? ticket.attendee_type}</td>
                    <td>{formatMoney(ticket.price_minor, ticket.currency, locale)}</td>
                    <td>{ticket.remaining_quantity} / {ticket.capacity}</td>
                    <td>
                      <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={ticket.status} />
                        <InventoryStatus state={ticket.state} locale={locale} />
                      </div>
                    </td>
                    <td className="ta-table-actions">
                      <button type="button" className="ta-table-action cursor-pointer" onClick={() => startEdit(ticket)}>
                        {locale === 'ar' ? 'تعديل' : 'Edit'}
                      </button>
                      {ticket.status === 'paused' ? (
                        <button type="button" className="ta-table-action cursor-pointer" disabled={submitting === ticket.id} onClick={() => void toggleStatus(ticket, 'active')}>
                          {locale === 'ar' ? 'تفعيل' : 'Activate'}
                        </button>
                      ) : (
                        <button type="button" className="ta-table-action cursor-pointer" disabled={submitting === ticket.id} onClick={() => void toggleStatus(ticket, 'paused')}>
                          {locale === 'ar' ? 'إيقاف' : 'Pause'}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
