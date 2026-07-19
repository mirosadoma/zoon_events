import { FormEvent, useState } from 'react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { localizedPath } from '@/lib/localePath'
import ar from '@/locales/ar'
import en from '@/locales/en'

type PlanRow = {
  id: string
  name: string
  name_ar: string | null
  description: string | null
  is_trial: boolean
  is_active: boolean
  duration_days: number
  price: string
  currency: string
  max_events: number | null
  max_attendees: number | null
  max_devices: number | null
  tenant_count: number
}

type Props = {
  plans: PlanRow[]
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

export default function SubscriptionsIndex({ plans, canManage }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [loading, setLoading] = useState(false)

  async function deletePlan(plan: PlanRow) {
    const msg = t('subscriptionPlanConfirmDelete', { name: plan.name })
    if (!window.confirm(msg)) {
      return
    }

    setLoading(true)
    try {
      const response = await fetch(`/api/v1/platform/subscription-plans/${plan.id}`, {
        method: 'DELETE',
        credentials: 'include',
        headers: csrfHeaders(),
      })

      if (!response.ok) {
        const payload = await response.json().catch(() => ({}))
        toast(String((payload as Record<string, unknown>).detail ?? (payload as Record<string, unknown>).message ?? t('errorState')), 'error')
        return
      }

      toast(t('subscriptionPlanDeleted'), 'success')
      window.location.reload()
    } finally {
      setLoading(false)
    }
  }

  return (
    <DashboardLayout title={t('subscriptionPlans')}>
      <PageHeader
        title={t('subscriptionPlans')}
        description={t('subscriptionPlansDescription')}
        actions={
          canManage ? (
            <LocalizedLink href={localizedPath(locale, '/platform/subscriptions/create')} className="button-primary">
              {t('subscriptionPlanAddButton')}
            </LocalizedLink>
          ) : undefined
        }
      />

      <PageContent>
        {plans.length === 0 ? (
          <EmptyState title={t('subscriptionPlansEmpty')} detail="" />
        ) : (
          <div className="ta-card overflow-hidden p-0">
            <div className="ta-table-wrap">
              <table className="ta-table w-full">
                <thead>
                  <tr>
                    <th>{t('subscriptionPlanColumnPlan')}</th>
                    <th>{t('subscriptionPlanColumnPrice')}</th>
                    <th>{t('subscriptionPlanColumnDuration')}</th>
                    <th>{t('subscriptionPlanColumnLimits')}</th>
                    <th>{t('subscriptionPlanColumnSubscribers')}</th>
                    <th>{t('subscriptionPlanColumnStatus')}</th>
                    {canManage && <th>{t('actions')}</th>}
                  </tr>
                </thead>
                <tbody>
                  {plans.map((plan) => (
                    <tr key={plan.id}>
                      <td className="font-medium text-[var(--ink)]">
                        {locale === 'ar' ? plan.name_ar || plan.name : plan.name}
                        {plan.is_trial && (
                          <span className="ms-2 rounded bg-[var(--brand-soft)] px-1.5 py-0.5 text-xs text-[var(--brand)]">Trial</span>
                        )}
                      </td>
                      <td className="text-sm text-[var(--ink)]">
                        {Number(plan.price) === 0 ? t('subscriptionPlanFree') : `${plan.price} ${plan.currency}`}
                      </td>
                      <td className="text-sm text-[var(--muted)]">{plan.duration_days} {t('subscriptionPlanDays')}</td>
                      <td className="text-xs text-[var(--muted)]">
                        {plan.max_events ?? '∞'} events · {plan.max_attendees ?? '∞'} attendees · {plan.max_devices ?? '∞'} devices
                      </td>
                      <td>
                        <div className="flex items-center gap-2">
                          <span className="text-sm font-medium text-[var(--ink)]">{plan.tenant_count}</span>
                          <LocalizedLink
                            href={localizedPath(locale, `/platform/subscriptions/${plan.id}`)}
                            className="ta-table-action"
                          >
                            {t('view')}
                          </LocalizedLink>
                        </div>
                      </td>
                      <td><StatusBadge status={plan.is_active ? 'active' : 'inactive'} /></td>
                      {canManage && (
                        <td>
                          <div className="ta-table-actions">
                            <LocalizedLink
                              href={localizedPath(locale, `/platform/subscriptions/${plan.id}/edit`)}
                              className="ta-table-action"
                            >
                              {t('edit')}
                            </LocalizedLink>
                            <button
                              type="button"
                              disabled={loading}
                              className="ta-table-action !border-[var(--danger)]/30 !text-[var(--danger)] hover:!bg-[var(--danger-soft)]"
                              onClick={() => void deletePlan(plan)}
                            >
                              {t('delete')}
                            </button>
                          </div>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
