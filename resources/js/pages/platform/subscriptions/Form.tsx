import { FormEvent, useState } from 'react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import TextInput from '@/components/forms/TextInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { PageContent, PageHeader } from '@/components/layout'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { localizedPath } from '@/lib/localePath'
import ar from '@/locales/ar'
import en from '@/locales/en'

type Plan = {
  id: string
  name: string
  name_ar: string | null
  description: string | null
  description_ar: string | null
  is_trial: boolean
  is_active: boolean
  duration_days: number
  price: string
  currency: string
  max_events: number | null
  max_attendees: number | null
  max_devices: number | null
}

type Props = {
  plan: Plan | null
  canManage: boolean
}

function csrfHeaders(): Record<string, string> {
  const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
    ?? document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]?.replace(/%3D/g, '=') ?? ''

  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': csrfToken,
    'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
    'X-Requested-With': 'XMLHttpRequest',
    'Idempotency-Key': crypto.randomUUID(),
  }
}

export default function SubscriptionPlanForm({ plan }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [loading, setLoading] = useState(false)
  const isEdit = plan !== null

  const [form, setForm] = useState({
    name: plan?.name ?? '',
    name_ar: plan?.name_ar ?? '',
    description: plan?.description ?? '',
    description_ar: plan?.description_ar ?? '',
    is_trial: plan?.is_trial ?? false,
    is_active: plan?.is_active ?? true,
    duration_days: plan ? String(plan.duration_days) : '30',
    price: plan ? String(plan.price) : '0',
    currency: plan?.currency ?? 'SAR',
    max_events: plan?.max_events != null ? String(plan.max_events) : '',
    max_attendees: plan?.max_attendees != null ? String(plan.max_attendees) : '',
    max_devices: plan?.max_devices != null ? String(plan.max_devices) : '',
  })

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setLoading(true)

    const payload: Record<string, unknown> = {
      name: form.name.trim(),
      name_ar: form.name_ar.trim() || null,
      description: form.description.trim() || null,
      description_ar: form.description_ar.trim() || null,
      is_trial: form.is_trial,
      duration_days: Number(form.duration_days),
      price: form.is_trial ? 0 : Number(form.price),
      currency: form.currency.trim().toUpperCase() || 'SAR',
      max_events: form.max_events.trim() === '' ? null : Number(form.max_events),
      max_attendees: form.max_attendees.trim() === '' ? null : Number(form.max_attendees),
      max_devices: form.max_devices.trim() === '' ? null : Number(form.max_devices),
    }

    if (isEdit) {
      payload.is_active = form.is_active
    }

    try {
      const response = await fetch(
        isEdit ? `/api/v1/platform/subscription-plans/${plan.id}` : '/api/v1/platform/subscription-plans',
        {
          method: isEdit ? 'PATCH' : 'POST',
          credentials: 'include',
          headers: csrfHeaders(),
          body: JSON.stringify(payload),
        },
      )

      const body = await response.json().catch(() => ({}))

      if (!response.ok) {
        toast(String((body as Record<string, unknown>).detail ?? (body as Record<string, unknown>).message ?? t('errorState')), 'error')
        return
      }

      toast(t('subscriptionPlanSaved'), 'success')
      window.location.assign(localizedPath(locale, '/platform/subscriptions'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <DashboardLayout title={isEdit ? t('subscriptionPlanEdit') : t('subscriptionPlanAdd')}>
      <PageHeader
        title={isEdit ? t('subscriptionPlanEdit') : t('subscriptionPlanAddNew')}
        actions={
          <LocalizedLink href={localizedPath(locale, '/platform/subscriptions')} className="button-secondary">
            {t('back')}
          </LocalizedLink>
        }
      />

      <PageContent>
        <form className="ta-card space-y-4" onSubmit={handleSubmit}>
          <div className="grid gap-3 sm:grid-cols-2">
            <TextInput
              label={t('subscriptionPlanNameEn')}
              name="name"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              required
            />
            <TextInput
              label={t('subscriptionPlanNameAr')}
              name="name_ar"
              value={form.name_ar}
              onChange={(e) => setForm({ ...form, name_ar: e.target.value })}
            />
            <TextInput
              label={t('subscriptionPlanDescriptionEn')}
              name="description"
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
            />
            <TextInput
              label={t('subscriptionPlanDescriptionAr')}
              name="description_ar"
              value={form.description_ar}
              onChange={(e) => setForm({ ...form, description_ar: e.target.value })}
            />
            <TextInput
              label={t('subscriptionPlanDuration')}
              name="duration_days"
              type="number"
              min={1}
              value={form.duration_days}
              onChange={(e) => setForm({ ...form, duration_days: e.target.value })}
              required
            />
            <TextInput
              label={t('subscriptionPlanPrice')}
              name="price"
              type="number"
              min={0}
              step="0.01"
              value={form.price}
              onChange={(e) => setForm({ ...form, price: e.target.value })}
              disabled={form.is_trial}
              required={!form.is_trial}
            />
            <TextInput
              label={t('subscriptionPlanCurrency')}
              name="currency"
              value={form.currency}
              onChange={(e) => setForm({ ...form, currency: e.target.value })}
              maxLength={3}
            />
            <TextInput
              label={t('subscriptionPlanMaxEvents')}
              name="max_events"
              type="number"
              min={0}
              value={form.max_events}
              onChange={(e) => setForm({ ...form, max_events: e.target.value })}
              placeholder={t('subscriptionPlanUnlimited')}
            />
            <TextInput
              label={t('subscriptionPlanMaxAttendees')}
              name="max_attendees"
              type="number"
              min={0}
              value={form.max_attendees}
              onChange={(e) => setForm({ ...form, max_attendees: e.target.value })}
              placeholder={t('subscriptionPlanUnlimited')}
            />
            <TextInput
              label={t('subscriptionPlanMaxDevices')}
              name="max_devices"
              type="number"
              min={0}
              value={form.max_devices}
              onChange={(e) => setForm({ ...form, max_devices: e.target.value })}
              placeholder={t('subscriptionPlanUnlimited')}
            />
          </div>

          <div className="flex flex-wrap gap-4">
            <label className="inline-flex items-center gap-2 text-sm text-[var(--ink)]">
              <input
                type="checkbox"
                checked={form.is_trial}
                onChange={(e) => setForm({
                  ...form,
                  is_trial: e.target.checked,
                  price: e.target.checked ? '0' : form.price,
                })}
              />
              {t('subscriptionPlanTrial')}
            </label>
            {isEdit && (
              <label className="inline-flex items-center gap-2 text-sm text-[var(--ink)]">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                />
                {t('subscriptionPlanActive')}
              </label>
            )}
          </div>

          <div className="flex gap-3">
            <SubmitButtonWithLoader
              label={isEdit ? t('saveChanges') : t('subscriptionPlanCreate')}
              loading={loading}
            />
            <LocalizedLink href={localizedPath(locale, '/platform/subscriptions')} className="button-secondary">
              {t('cancel')}
            </LocalizedLink>
          </div>
        </form>
      </PageContent>
    </DashboardLayout>
  )
}
