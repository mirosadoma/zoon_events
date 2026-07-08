import { Link, router } from '@inertiajs/react'
import { FormEvent, useMemo, useState } from 'react'
import { InventoryStatus } from '@/components/ticketing/InventoryStatus'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import DateTimeInput from '@/components/forms/DateTimeInput'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { formatMoney } from '@/lib/formatMoney'

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
  price_minor: string
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
    price_minor: '',
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
    price_minor: String(ticket.price_minor),
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
    price_minor: Number(form.price_minor),
    currency: form.currency.toUpperCase(),
    sale_starts_at: form.sale_starts_at,
    sale_ends_at: form.sale_ends_at,
  }
  if (status) {
    payload.status = status
  }
  return payload
}

export default function Ticketing({ tenantId, event, tickets }: Props) {
  const { locale } = useLocale()
  const { toast } = useToast()
  const [createForm, setCreateForm] = useState<TicketFormState>(() => emptyForm(event))
  const [editingId, setEditingId] = useState<string | null>(null)
  const [editForm, setEditForm] = useState<TicketFormState>(() => emptyForm(event))
  const [createErrors, setCreateErrors] = useState<FormErrors>({})
  const [editErrors, setEditErrors] = useState<FormErrors>({})
  const [submitting, setSubmitting] = useState<'create' | 'edit' | string | null>(null)

  const apiHeaders = useMemo(
    () => ({
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Tenant-ID': tenantId,
    }),
    [tenantId],
  )

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

  async function saveTicket(mode: 'create' | 'edit', ticketId?: string) {
    const form = mode === 'create' ? createForm : editForm
    setSubmitting(mode === 'create' ? 'create' : 'edit')
    if (mode === 'create') setCreateErrors({})
    else setEditErrors({})

    const url = mode === 'create'
      ? `/api/v1/tenant/events/${event.id}/ticket-types`
      : `/api/v1/tenant/events/${event.id}/ticket-types/${ticketId}`

    try {
      const response = await fetch(url, {
        method: mode === 'create' ? 'POST' : 'PATCH',
        credentials: 'include',
        headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
        body: JSON.stringify(formToPayload(form)),
      })
      const body = await response.json()

      if (!response.ok) {
        const errors = extractErrors(body)
        if (mode === 'create') setCreateErrors(errors)
        else setEditErrors(errors)
        toast(extractError(body, locale === 'ar' ? 'تعذر حفظ نوع التذكرة.' : 'Failed to save ticket type.'), 'error')
        setSubmitting(null)
        return
      }

      toast(
        mode === 'create'
          ? (locale === 'ar' ? 'تم إنشاء نوع التذكرة.' : 'Ticket type created.')
          : (locale === 'ar' ? 'تم تحديث نوع التذكرة.' : 'Ticket type updated.'),
        'success',
      )
      setCreateForm(emptyForm(event))
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
    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/ticket-types/${ticket.id}`, {
        method: 'PATCH',
        credentials: 'include',
        headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
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

  function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    void saveTicket('create')
  }

  function handleEdit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!editingId) return
    void saveTicket('edit', editingId)
  }

  function startEdit(ticket: Ticket) {
    setEditingId(ticket.id)
    setEditForm(ticketToForm(ticket))
    setEditErrors({})
  }

  function renderForm(
    form: TicketFormState,
    setForm: (value: TicketFormState) => void,
    errors: FormErrors,
    onSubmit: (event: FormEvent<HTMLFormElement>) => void,
    loading: boolean,
    submitLabel: string,
  ) {
    return (
      <form className="state-panel grid gap-4 sm:grid-cols-2" onSubmit={onSubmit}>
        <TextInput label={locale === 'ar' ? 'الرمز' : 'Code'} name="code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} required error={errors.code} />
        <TextInput label={locale === 'ar' ? 'نوع الحضور' : 'Attendee type'} name="attendee_type" value={form.attendee_type} onChange={(e) => setForm({ ...form, attendee_type: e.target.value })} />
        <TextInput label={locale === 'ar' ? 'الاسم (إنجليزي)' : 'Name (EN)'} name="name_en" value={form.name_en} onChange={(e) => setForm({ ...form, name_en: e.target.value })} required error={errors['name.en']} />
        <TextInput label={locale === 'ar' ? 'الاسم (عربي)' : 'Name (AR)'} name="name_ar" value={form.name_ar} onChange={(e) => setForm({ ...form, name_ar: e.target.value })} required error={errors['name.ar']} />
        <TextareaInput className="sm:col-span-2" label={locale === 'ar' ? 'الوصف (إنجليزي)' : 'Description (EN)'} name="description_en" value={form.description_en} onChange={(e) => setForm({ ...form, description_en: e.target.value })} />
        <TextareaInput className="sm:col-span-2" label={locale === 'ar' ? 'الوصف (عربي)' : 'Description (AR)'} name="description_ar" value={form.description_ar} onChange={(e) => setForm({ ...form, description_ar: e.target.value })} />
        <TextInput label={locale === 'ar' ? 'السعة' : 'Capacity'} name="capacity" type="number" min={1} value={form.capacity} onChange={(e) => setForm({ ...form, capacity: e.target.value })} required error={errors.capacity} />
        <TextInput label={locale === 'ar' ? 'السعر (هللات)' : 'Price (minor units)'} name="price_minor" type="number" min={0} value={form.price_minor} onChange={(e) => setForm({ ...form, price_minor: e.target.value })} required error={errors.price_minor} />
        <TextInput label={locale === 'ar' ? 'العملة' : 'Currency'} name="currency" value={form.currency} onChange={(e) => setForm({ ...form, currency: e.target.value.toUpperCase() })} required error={errors.currency} />
        <DateTimeInput label={locale === 'ar' ? 'بداية البيع' : 'Sale starts'} name="sale_starts_at" value={form.sale_starts_at} onChange={(e) => setForm({ ...form, sale_starts_at: e.target.value })} required error={errors.sale_starts_at} />
        <DateTimeInput label={locale === 'ar' ? 'نهاية البيع' : 'Sale ends'} name="sale_ends_at" value={form.sale_ends_at} onChange={(e) => setForm({ ...form, sale_ends_at: e.target.value })} required error={errors.sale_ends_at} />
        <div className="sm:col-span-2">
          <SubmitButtonWithLoader loading={loading} label={submitLabel} />
        </div>
      </form>
    )
  }

  return (
    <DashboardLayout title={locale === 'ar' ? 'أنواع التذاكر' : 'Ticket types'}>
      <PageHeader
        title={locale === 'ar' ? 'أنواع التذاكر' : 'Ticket types'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'أنواع التذاكر' : 'Ticket types' },
        ]}
        actions={<Link className="button-secondary" href={`/tenant/events/${event.id}/price-tiers`}>{locale === 'ar' ? 'شرائح الأسعار' : 'Price tiers'}</Link>}
      />
      <PageContent>
        <section className="mb-8">
          <h2 className="mb-4 text-lg font-semibold">{locale === 'ar' ? 'إنشاء نوع تذكرة' : 'Create ticket type'}</h2>
          {renderForm(createForm, setCreateForm, createErrors, handleCreate, submitting === 'create', locale === 'ar' ? 'إنشاء' : 'Create')}
        </section>

        {tickets.length === 0 ? (
          <EmptyState
            title={locale === 'ar' ? 'لا توجد أنواع تذاكر' : 'No ticket types yet'}
            detail={locale === 'ar' ? 'أنشئ نوع تذكرة لبدء التسعير.' : 'Create a ticket type to start pricing.'}
          />
        ) : (
          <ul className="space-y-3">
            {tickets.map((ticket) => (
              <li key={ticket.id} className="state-panel">
                {editingId === ticket.id ? (
                  <div>
                    <h2 className="mb-4 text-lg font-semibold">{locale === 'ar' ? 'تعديل نوع التذكرة' : 'Edit ticket type'}</h2>
                    {renderForm(editForm, setEditForm, editErrors, handleEdit, submitting === 'edit', locale === 'ar' ? 'حفظ' : 'Save')}
                    <button type="button" className="button-secondary mt-4" onClick={() => setEditingId(null)}>
                      {locale === 'ar' ? 'إلغاء' : 'Cancel'}
                    </button>
                  </div>
                ) : (
                  <>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <h2 className="text-lg font-semibold">{ticket.name[locale]}</h2>
                        <p className="text-sm text-slate-600">{ticket.code}</p>
                      </div>
                      <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={ticket.status} />
                        <InventoryStatus state={ticket.state} locale={locale} />
                      </div>
                    </div>
                    <p className="mt-3">{formatMoney(ticket.price_minor, ticket.currency, locale)}</p>
                    <p className="text-sm text-slate-600">
                      {locale === 'ar' ? 'المتبقي' : 'Remaining'}: {ticket.remaining_quantity} / {ticket.capacity}
                    </p>
                    <div className="mt-4 flex flex-wrap gap-2">
                      <button type="button" className="button-secondary" onClick={() => startEdit(ticket)}>
                        {locale === 'ar' ? 'تعديل' : 'Edit'}
                      </button>
                      {ticket.status === 'paused' ? (
                        <button type="button" className="button-secondary" disabled={submitting === ticket.id} onClick={() => void toggleStatus(ticket, 'active')}>
                          {locale === 'ar' ? 'تفعيل' : 'Activate'}
                        </button>
                      ) : (
                        <button type="button" className="button-secondary" disabled={submitting === ticket.id} onClick={() => void toggleStatus(ticket, 'paused')}>
                          {locale === 'ar' ? 'إيقاف' : 'Pause'}
                        </button>
                      )}
                    </div>
                  </>
                )}
              </li>
            ))}
          </ul>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
