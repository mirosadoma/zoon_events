import { useState } from 'react'
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

type Plan = {
  id: string
  name: string
  name_ar: string | null
  is_trial: boolean
  is_active: boolean
  duration_days: number
  price: string
  currency: string
}

type SubscriptionRow = {
  id: string
  tenant_name: string
  tenant_id: string
  status: string
  starts_at: string | null
  ends_at: string | null
  amount_paid: string
}

type Props = {
  plan: Plan
  subscriptions: SubscriptionRow[]
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

export default function SubscriptionPlanShow({ plan, subscriptions, canManage }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [loading, setLoading] = useState(false)
  const planName = locale === 'ar' ? plan.name_ar || plan.name : plan.name

  async function renew(subscriptionId: string) {
    setLoading(true)
    try {
      const response = await fetch(`/api/v1/platform/subscriptions/${subscriptionId}/renew`, {
        method: 'POST',
        credentials: 'include',
        headers: csrfHeaders(),
        body: JSON.stringify({}),
      })

      if (!response.ok) {
        const payload = await response.json().catch(() => ({}))
        toast(String((payload as Record<string, unknown>).detail ?? (payload as Record<string, unknown>).message ?? t('errorState')), 'error')
        return
      }

      toast(t('subscriptionPlanRenewed'), 'success')
      window.location.reload()
    } finally {
      setLoading(false)
    }
  }

  return (
    <DashboardLayout title={planName}>
      <PageHeader
        title={t('subscriptionPlanSubscribersTitle', { name: planName })}
        description={t('subscriptionPlanSubscribersCount', { count: subscriptions.length })}
        actions={
          <div className="flex flex-wrap gap-2">
            {canManage && (
              <LocalizedLink
                href={localizedPath(locale, `/platform/subscriptions/${plan.id}/edit`)}
                className="button-secondary"
              >
                {t('subscriptionPlanEdit')}
              </LocalizedLink>
            )}
            <LocalizedLink href={localizedPath(locale, '/platform/subscriptions')} className="button-secondary">
              {t('subscriptionPlanBackToPlans')}
            </LocalizedLink>
          </div>
        }
      />

      <PageContent>
        {subscriptions.length === 0 ? (
          <EmptyState
            title={t('subscriptionPlanNoSubscribers')}
            detail={t('subscriptionPlanNoSubscribersDetail')}
          />
        ) : (
          <div className="ta-card overflow-hidden p-0">
            <div className="ta-table-wrap">
              <table className="ta-table w-full">
                <thead>
                  <tr>
                    <th>{t('subscriptionPlanColumnTenant')}</th>
                    <th>{t('subscriptionPlanColumnStatus')}</th>
                    <th>{t('subscriptionPlanColumnStarts')}</th>
                    <th>{t('subscriptionPlanColumnEnds')}</th>
                    <th>{t('subscriptionPlanColumnPaid')}</th>
                    {canManage && <th>{t('actions')}</th>}
                  </tr>
                </thead>
                <tbody>
                  {subscriptions.map((sub) => (
                    <tr key={sub.id}>
                      <td className="font-medium text-[var(--ink)]">{sub.tenant_name}</td>
                      <td><StatusBadge status={sub.status} /></td>
                      <td className="text-sm text-[var(--muted)]">
                        {sub.starts_at ? new Date(sub.starts_at).toLocaleDateString(locale === 'ar' ? 'ar-EG' : 'en-GB') : '—'}
                      </td>
                      <td className="text-sm text-[var(--muted)]">
                        {sub.ends_at ? new Date(sub.ends_at).toLocaleDateString(locale === 'ar' ? 'ar-EG' : 'en-GB') : '—'}
                      </td>
                      <td className="text-sm text-[var(--muted)]">{sub.amount_paid}</td>
                      {canManage && (
                        <td>
                          <button
                            type="button"
                            disabled={loading}
                            onClick={() => void renew(sub.id)}
                            className="ta-table-action"
                          >
                            {t('subscriptionPlanRenew')}
                          </button>
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
