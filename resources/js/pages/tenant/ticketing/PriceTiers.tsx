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
import { toDateTimeLocalValue } from '@/lib/dateTimeLocal'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
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
  return toDateTimeLocalValue(value)
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
  const { locale, t } = useLocale()
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
      await apiFetch(url, {
        method: editingTier ? 'PATCH' : 'POST',
        tenantId,
        idempotency: true,
        body: formToPayload(tierForm),
      })

      toast(editingTier ? t('priceTierUpdated') : t('priceTierCreated'), 'success')
      resetForm()
      router.reload()
    } catch (error) {
      if (error instanceof ApiFetchError) {
        setFormErrors(error.errors)
        toast(error.message || t('priceTierFailedToSave'), 'error')
      } else {
        toast(t('priceTierFailedToSave'), 'error')
      }
    } finally {
      setSubmitting(null)
    }
  }

  async function retireTier(tier: PriceTierRow) {
    setSubmitting(tier.id)

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/ticket-types/${tier.ticket_type_id}/price-tiers/${tier.id}`, {
        method: 'PATCH',
        tenantId,
        idempotency: true,
        body: { status: 'retired' },
      })

      toast(t('priceTierDisabled'), 'success')
      router.reload()
    } catch (error) {
      toast(error instanceof ApiFetchError ? error.message : t('priceTierFailedToDisable'), 'error')
    } finally {
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
            label={t('priceTierTicketType')}
            name="ticket_type_id"
            value={tierForm.ticket_type_id}
            onChange={(e) => onTicketTypeChange(e.target.value, tierForm, setTierForm)}
            options={ticketTypeOptions}
            required
          />
        ) : (
          <TextInput label={t('priceTierTicketType')} name="ticket_type_id" value={tierForm.ticket_type_id} readOnly />
        )}
        <TextInput label={t('priceTierName')} name="name" value={tierForm.name} onChange={(e) => setTierForm({ ...tierForm, name: e.target.value })} required error={formErrors.name} />
        <TextInput label={t('priceTierPrice')} name="price_minor" type="number" min={0} value={tierForm.price_minor} onChange={(e) => setTierForm({ ...tierForm, price_minor: e.target.value })} required error={formErrors.price_minor} />
        <SelectInput
          label={t('ticketTypeCurrency')}
          name="currency"
          value={tierForm.currency}
          onChange={(e) => setTierForm({ ...tierForm, currency: e.target.value })}
          options={currencyOptions}
          required
          error={formErrors.currency}
        />
        <TextInput label={t('priceTierPriority')} name="priority" type="number" min={1} value={tierForm.priority} onChange={(e) => setTierForm({ ...tierForm, priority: e.target.value })} required error={formErrors.priority} />
        <DateTimeInput label={t('priceTierStartsAt')} name="starts_at" value={tierForm.starts_at} onChange={(e) => setTierForm({ ...tierForm, starts_at: e.target.value })} error={formErrors.starts_at} />
        <DateTimeInput label={t('priceTierEndsAt')} name="ends_at" value={tierForm.ends_at} onChange={(e) => setTierForm({ ...tierForm, ends_at: e.target.value })} error={formErrors.ends_at} />
        <TextInput label={t('priceTierRemainingAtMost')} name="remaining_at_most" type="number" min={1} value={tierForm.remaining_at_most} onChange={(e) => setTierForm({ ...tierForm, remaining_at_most: e.target.value })} error={formErrors.remaining_at_most} />
        <p className="text-sm text-slate-600 sm:col-span-2">
          {t('priceTierRequiredNote')}
        </p>
        <div className="flex flex-wrap gap-2 sm:col-span-2">
          <SubmitButtonWithLoader
            loading={submitting === 'save'}
            label={editingTier ? t('update') : t('add')}
          />
          {editingTier ? (
            <button type="button" className="button-secondary" onClick={resetForm}>
              {t('ticketTypeCancelEdit')}
            </button>
          ) : null}
        </div>
      </form>
    )
  }

  return (
    <DashboardLayout title={t('priceTiers')}>
      <PageHeader
        title={t('priceTiers')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('priceTiers') },
        ]}
      />
      <PageContent>
        {ticketTypes.length === 0 ? (
          <EmptyState
            title={t('ticketTypeNoTickets')}
            detail={t('priceTierNoTicketTypesDetail')}
          />
        ) : (
          <section className="mb-8">
            <h2 className="mb-4 text-lg font-semibold">
              {editingTier ? t('priceTierEdit') : t('priceTierAdd')}
            </h2>
            {renderForm()}
          </section>
        )}

        {priceTiers.length === 0 ? (
          <EmptyState
            title={t('priceTierNoTiers')}
            detail={t('priceTierNoTiersDetail')}
          />
        ) : (
          <DataTable
            rows={priceTiers as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              { key: 'name', header: t('priceTierName') },
              { key: 'ticket_type_id', header: t('priceTierTicketType') },
              {
                key: 'price_minor',
                header: t('priceTierPrice'),
                render: (row) => formatMoney(Number(row.price_minor), String(row.currency), locale),
              },
              {
                key: 'status',
                header: t('status'),
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              {
                key: 'is_active_now',
                header: t('priceTierActiveNow'),
                render: (row) => <StatusBadge status={row.is_active_now ? 'active' : 'inactive'} label={row.is_active_now ? t('yes') : t('no')} />,
              },
              { key: 'priority', header: t('priceTierPriority') },
              {
                key: 'actions',
                header: t('actions'),
                render: (row) => {
                  const tier = row as unknown as PriceTierRow
                  return (
                    <div className="flex flex-wrap gap-2">
                      <button
                        type="button"
                        className="button-secondary"
                        onClick={() => startEdit(tier)}
                      >
                        {t('edit')}
                      </button>
                      {tier.status !== 'retired' && (
                        <button type="button" className="button-secondary" disabled={submitting === tier.id} onClick={() => void retireTier(tier)}>
                          {t('disable')}
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
