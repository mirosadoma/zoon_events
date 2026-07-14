import LocalizedLink from '@/components/routing/LocalizedLink'
import { useEffect, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmergencyControls } from '@/components/acs/EmergencyControls'
import { LaneHealthCard } from '@/components/acs-health/LaneHealthCard'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { ACS_HEALTH_POLL_INTERVAL_MS } from '@/lib/acs-polling'

type EventRow = { id: string; name: { en: string; ar: string } }

type HealthSummary = {
  integration_status: 'online' | 'degraded' | 'offline'
  active_emergency: boolean
  lanes: Array<{ lane_id: string; health_status: 'online' | 'degraded' | 'offline'; last_seen_at: string | null }>
}

type Props = {
  event: EventRow
  tenantId: string
  health: HealthSummary
}

export default function AcsGateHealth({ event, tenantId, health: initialHealth }: Props) {
  const { locale, t } = useLocale()
  const [health, setHealth] = useState(initialHealth)
  const ar = locale === 'ar'

  useEffect(() => {
    let active = true

    async function poll() {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/acs/health`, {
        credentials: 'include',
        headers: { Accept: 'application/json', 'X-Tenant-ID': tenantId },
      })
      const body = await response.json()
      if (active && response.ok) {
        setHealth(body.data as HealthSummary)
      }
    }

    void poll()
    const timer = window.setInterval(() => void poll(), ACS_HEALTH_POLL_INTERVAL_MS)
    return () => {
      active = false
      window.clearInterval(timer)
    }
  }, [event.id, tenantId])

  return (
    <DashboardLayout title={ar ? 'صحة البوابة' : 'Gate health'}>
      <PageHeader
        title={ar ? 'صحة البوابة' : 'Gate health'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: ar ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS', href: `/tenant/events/${event.id}/acs` },
          { label: ar ? 'صحة البوابة' : 'Gate health' },
        ]}
        actions={(
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/access-logs`}>
            {ar ? 'سجلات الوصول' : 'Access logs'}
          </LocalizedLink>
        )}
      />
      <PageContent>
        <div className="ta-card flex flex-wrap items-center justify-between gap-4">
          <div>
            <p className="text-sm text-[var(--muted)]">{ar ? 'حالة التكامل' : 'Integration status'}</p>
            <div className="mt-2">
              <StatusBadge status={health.integration_status} size="md" />
            </div>
          </div>
          <EmergencyControls
            eventId={event.id}
            tenantId={tenantId}
            activeEmergency={health.active_emergency}
            onChanged={() => {
              void fetch(`/api/v1/tenant/events/${event.id}/acs/health`, {
                credentials: 'include',
                headers: { Accept: 'application/json', 'X-Tenant-ID': tenantId },
              }).then(async (response) => {
                const body = await response.json()
                if (response.ok) {
                  setHealth(body.data as HealthSummary)
                }
              })
            }}
          />
        </div>

        {health.active_emergency && (
          <p className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200" role="alert">
            {ar ? 'خروج الطوارئ نشط — البوابات في وضع الإخلاء.' : 'Emergency egress is active — gates are in fail-open egress mode.'}
          </p>
        )}

        <section className="mt-8 space-y-3">
          <div>
            <h2 className="text-lg font-semibold text-[var(--ink)]">{ar ? 'صحة المسارات' : 'Lane health'}</h2>
            <p className="text-sm text-[var(--muted)]">
              {ar ? 'يتم التحديث تلقائياً كل بضع ثوانٍ.' : 'Updates automatically every few seconds.'}
            </p>
          </div>
          {health.lanes.length === 0 ? (
            <EmptyState
              title={ar ? 'لا توجد مسارات للمراقبة' : 'No lanes to monitor'}
              detail={ar ? 'أضف مسارات ACS لعرض حالة الاتصال.' : 'Add ACS lanes to see connectivity status.'}
            />
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {health.lanes.map((lane) => (
                <LaneHealthCard
                  key={lane.lane_id}
                  laneId={lane.lane_id}
                  healthStatus={lane.health_status}
                  lastSeenAt={lane.last_seen_at}
                  activeEmergency={health.active_emergency}
                />
              ))}
            </div>
          )}
        </section>
      </PageContent>
    </DashboardLayout>
  )
}
