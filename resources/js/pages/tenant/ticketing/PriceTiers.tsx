import { FormEvent, useMemo, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import SelectInput from '@/components/forms/SelectInput'
import DateTimeInput from '@/components/forms/DateTimeInput'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { formatMoney } from '@/lib/formatMoney'
import { CURRENCIES, currencyLabel } from '@/lib/ticketingOptions'
import { router } from '@inertiajs/react'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type TicketTypeRow = {
  id: string
  code: string
  name: { en: string; ar: string }
  currency: string
}

type PriceTierRow = {
  id: string
  name: string
  ticket_type_id: string
  price_minor: number
  currency: string
  starts_at?: string | null
  ends_at?: string | null
  remaining_at_most?: number | null
  priority: number
  status: string
  is_active_now: boolean
}

type Props = {
  tenantId: string
  event: EventRow
  ticketTypes: TicketTypeRow[]
  priceTiers: PriceTierRow[]
}

type TierFormState = {
  ticket_type_id: string
  name: string
  price_minor: string
  currency: string
  priority: string
  starts_at: string
  ends_at: string
  remaining_at_most: string
}

type FormErrors = Record<string, string>

function toLocalDateTime(value: string | null | undefined): string {
  if (!value) return ''
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return ''
  const pad = (n: number) => n.toString().padStart(2, '0')
  return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}T${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`
}

function emptyForm(ticketTypes: TicketTypeRow[]): TierFormState {
  const first = ticketTypes[0]
  return {
    ticket_type_id: first?.id ?? '',
    name: '',
    price_minor: '',
    currency: first?.currency ?? 'SAR',
    priority: '1',
    starts_at: '',
    ends_at: '',
    remaining_at_most: '',
  }
}

function tierToForm(tier: PriceTierRow): TierFormState {
  return {
    ticket_type_id: tier.ticket_type_id,
    name: tier.name,
    price_minor: String(tier.price_minor),
    currency: tier.currency,
    priority: String(tier.priority),
    starts_at: toLocalDateTime(tier.starts_at),
    ends_at: toLocalDateTime(tier.ends_at),
    remaining_at_most: tier.remaining_at_most === null || tier.remaining_at_most === undefined ? '' : String(tier.remaining_at_most),
  }
}

function formToPayload(form: TierFormState, status?: string) {
  const payload: Record<string, unknown> = {
    name: form.name,
    price_minor: Number(form.price_minor),
    currency: form.currency.toUpperCase(),
    priority: Number(form.priority),
    starts_at: form.starts_at || null,
    ends_at: form.ends_at || null,
    remaining_at_most: form.remaining_at_most === '' ? null : Number(form.remaining_at_most),
  }
  if (status) {
    payload.status = status
  }
  return payload
}

export default function PriceTiers({ tenantId, event, ticketTypes, priceTiers }: Props) {
  const { locale } = useLocale()
  const { toast } = useToast()
  const [tierForm, setTierForm] = useState<TierFormState>(() => emptyForm(ticketTypes))
  const [editingTier, setEditingTier] = useState<PriceTierRow | null>(null)
  const [formErrors, setFormErrors] = useState<FormErrors>({})
  const [submitting, setSubmitting] = useState<'save' | string | null>(null)

  const currencyOptions = useMemo(
    () => CURRENCIES.map((currency) => ({
      value: currency.code,
      label: currencyLabel(currency.code, locale),
    })),
    [locale],
  )

  const ticketTypeOptions = useMemo(
    () => ticketTypes.map((ticket) => ({
      value: ticket.id,
      label: `${ticket.code} — ${ticket.name[locale]}`,
    })),
    [ticketTypes, locale],
  )

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

  function onTicketTypeChange(ticketTypeId: string, form: TierFormState, setForm: (value: TierFormState) => void) {
    const ticket = ticketTypes.find((row) => row.id === ticketTypeId)
    setForm({ ...form, ticket_type_id: ticketTypeId, currency: ticket?.currency ?? form.currency })
  }

  function resetForm() {
    setTierForm(emptyForm(ticketTypes))
    setEditingTier(null)
    setFormErrors({})
  }

  async function saveTier() {
    const ticketTypeId = editingTier?.ticket_type_id ?? tierForm.ticket_type_id
    if (!ticketTypeId) return

    setSubmitting('save')
    setFormErrors({})

    const url = editingTier
      ? `/api/v1/tenant/events/${event.id}/ticket-types/${ticketTypeId}/price-tiers/${editingTier.id}`
      : `/api/v1/tenant/events/${event.id}/ticket-types/${ticketTypeId}/price-tiers`

    try {
      const response = await fetch(url, {
        method: editingTier ? 'PATCH' : 'POST',
        credentials: 'include',
        headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
        body: JSON.stringify(formToPayload(tierForm)),
      })
      const body = await response.json()

      if (!response.ok) {
        const errors = extractErrors(body)
        setFormErrors(errors)
        toast(extractError(body, locale === 'ar' ? 'تعذر حفظ شريحة السعر.' : 'Failed to save price tier.'), 'error')
        setSubmitting(null)
        return
      }

      toast(
        editingTier
          ? (locale === 'ar' ? 'تم تحديث شريحة السعر.' : 'Price tier updated.')
          : (locale === 'ar' ? 'تم إنشاء شريحة السعر.' : 'Price tier created.'),
        'success',
      )
      resetForm()
      setSubmitting(null)
      router.reload()
    } catch {
      toast(locale === 'ar' ? 'تعذر حفظ شريحة السعر.' : 'Failed to save price tier.', 'error')
      setSubmitting(null)
    }
  }

  async function retireTier(tier: PriceTierRow) {
    setSubmitting(tier.id)
    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/ticket-types/${tier.ticket_type_id}/price-tiers/${tier.id}`, {
        method: 'PATCH',
        credentials: 'include',
        headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
        body: JSON.stringify({ status: 'retired' }),
      })
      const body = await response.json()

      if (!response.ok) {
        toast(extractError(body, locale === 'ar' ? 'تعذر تعطيل شريحة السعر.' : 'Failed to disable price tier.'), 'error')
        setSubmitting(null)
        return
      }

      toast(locale === 'ar' ? 'تم تعطيل شريحة السعر.' : 'Price tier disabled.', 'success')
      setSubmitting(null)
      router.reload()
    } catch {
      toast(locale === 'ar' ? 'تعذر تعطيل شريحة السعر.' : 'Failed to disable price tier.', 'error')
      setSubmitting(null)
    }
  }

  function handleSubmit(submitEvent: FormEvent<HTMLFormElement>) {
    submitEvent.preventDefault()
    void saveTier()
  }

  function startEdit(tier: PriceTierRow) {
    setEditingTier(tier)
    setTierForm(tierToForm(tier))
    setFormErrors({})
  }

  function renderForm() {
    const allowTicketTypeSelect = editingTier === null

    return (
      <form className="state-panel grid gap-4 sm:grid-cols-2" onSubmit={handleSubmit}>
        {allowTicketTypeSelect ? (
          <SelectInput
            label={locale === 'ar' ? 'نوع التذكرة' : 'Ticket type'}
            name="ticket_type_id"
            value={tierForm.ticket_type_id}
            onChange={(e) => onTicketTypeChange(e.target.value, tierForm, setTierForm)}
            options={ticketTypeOptions}
            required
          />
        ) : (
          <TextInput label={locale === 'ar' ? 'نوع التذكرة' : 'Ticket type'} name="ticket_type_id" value={tierForm.ticket_type_id} readOnly />
        )}
        <TextInput label={locale === 'ar' ? 'الاسم' : 'Name'} name="name" value={tierForm.name} onChange={(e) => setTierForm({ ...tierForm, name: e.target.value })} required error={formErrors.name} />
        <TextInput label={locale === 'ar' ? 'السعر' : 'Price'} name="price_minor" type="number" min={0} value={tierForm.price_minor} onChange={(e) => setTierForm({ ...tierForm, price_minor: e.target.value })} required error={formErrors.price_minor} />
        <SelectInput
          label={locale === 'ar' ? 'العملة' : 'Currency'}
          name="currency"
          value={tierForm.currency}
          onChange={(e) => setTierForm({ ...tierForm, currency: e.target.value })}
          options={currencyOptions}
          required
          error={formErrors.currency}
        />
        <TextInput label={locale === 'ar' ? 'الأولوية' : 'Priority'} name="priority" type="number" min={1} value={tierForm.priority} onChange={(e) => setTierForm({ ...tierForm, priority: e.target.value })} required error={formErrors.priority} />
        <DateTimeInput label={locale === 'ar' ? 'يبدأ في' : 'Starts at'} name="starts_at" value={tierForm.starts_at} onChange={(e) => setTierForm({ ...tierForm, starts_at: e.target.value })} error={formErrors.starts_at} />
        <DateTimeInput label={locale === 'ar' ? 'ينتهي في' : 'Ends at'} name="ends_at" value={tierForm.ends_at} onChange={(e) => setTierForm({ ...tierForm, ends_at: e.target.value })} error={formErrors.ends_at} />
        <TextInput label={locale === 'ar' ? 'الحد الأقصى للمتبقي' : 'Remaining at most'} name="remaining_at_most" type="number" min={1} value={tierForm.remaining_at_most} onChange={(e) => setTierForm({ ...tierForm, remaining_at_most: e.target.value })} error={formErrors.remaining_at_most} />
        <p className="text-sm text-slate-600 sm:col-span-2">
          {locale === 'ar' ? 'يجب تحديد وقت البداية أو النهاية أو حد السعة على الأقل.' : 'At least one of start time, end time, or capacity threshold is required.'}
        </p>
        <div className="flex flex-wrap gap-2 sm:col-span-2">
          <SubmitButtonWithLoader
            loading={submitting === 'save'}
            label={editingTier ? (locale === 'ar' ? 'تحديث' : 'Update') : (locale === 'ar' ? 'إضافة' : 'Add')}
          />
          {editingTier ? (
            <button type="button" className="button-secondary" onClick={resetForm}>
              {locale === 'ar' ? 'إلغاء التعديل' : 'Cancel edit'}
            </button>
          ) : null}
        </div>
      </form>
    )
  }

  return (
    <DashboardLayout title={locale === 'ar' ? 'شرائح الأسعار' : 'Price tiers'}>
      <PageHeader
        title={locale === 'ar' ? 'شرائح الأسعار' : 'Price tiers'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'شرائح الأسعار' : 'Price tiers' },
        ]}
      />
      <PageContent>
        {ticketTypes.length === 0 ? (
          <EmptyState
            title={locale === 'ar' ? 'لا توجد أنواع تذاكر' : 'No ticket types yet'}
            detail={locale === 'ar' ? 'أنشئ نوع تذكرة قبل إضافة شرائح الأسعار.' : 'Create a ticket type before adding price tiers.'}
          />
        ) : (
          <section className="mb-8">
            <h2 className="mb-4 text-lg font-semibold">
              {editingTier
                ? (locale === 'ar' ? 'تعديل شريحة السعر' : 'Edit price tier')
                : (locale === 'ar' ? 'إضافة شريحة سعر' : 'Add price tier')}
            </h2>
            {renderForm()}
          </section>
        )}

        {priceTiers.length === 0 ? (
          <EmptyState
            title={locale === 'ar' ? 'لا توجد شرائح أسعار' : 'No price tiers yet'}
            detail={locale === 'ar' ? 'أضف شرائح الأسعار بعد إنشاء أنواع التذاكر.' : 'Add price tiers after creating ticket types.'}
          />
        ) : (
          <DataTable
            rows={priceTiers as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              { key: 'name', header: locale === 'ar' ? 'الاسم' : 'Name' },
              { key: 'ticket_type_id', header: locale === 'ar' ? 'نوع التذكرة' : 'Ticket type' },
              {
                key: 'price_minor',
                header: locale === 'ar' ? 'السعر' : 'Price',
                render: (row) => formatMoney(Number(row.price_minor), String(row.currency), locale),
              },
              {
                key: 'status',
                header: locale === 'ar' ? 'الحالة' : 'Status',
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              {
                key: 'is_active_now',
                header: locale === 'ar' ? 'نشط الآن' : 'Active now',
                render: (row) => <StatusBadge status={row.is_active_now ? 'active' : 'inactive'} label={row.is_active_now ? (locale === 'ar' ? 'نعم' : 'Yes') : (locale === 'ar' ? 'لا' : 'No')} />,
              },
              { key: 'priority', header: locale === 'ar' ? 'الأولوية' : 'Priority' },
              {
                key: 'actions',
                header: locale === 'ar' ? 'إجراءات' : 'Actions',
                render: (row) => {
                  const tier = row as unknown as PriceTierRow
                  return (
                    <div className="flex flex-wrap gap-2">
                      <button
                        type="button"
                        className="button-secondary"
                        onClick={() => startEdit(tier)}
                      >
                        {locale === 'ar' ? 'تعديل' : 'Edit'}
                      </button>
                      {tier.status !== 'retired' && (
                        <button type="button" className="button-secondary" disabled={submitting === tier.id} onClick={() => void retireTier(tier)}>
                          {locale === 'ar' ? 'تعطيل' : 'Disable'}
                        </button>
                      )}
                    </div>
                  )
                },
              },
            ]}
          />
        )}
      </PageContent>
    </DashboardLayout>
  )
}
