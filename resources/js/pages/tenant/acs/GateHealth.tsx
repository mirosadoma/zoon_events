import LocalizedLink from '@/components/routing/LocalizedLink'
import { useEffect, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmergencyControls } from '@/components/acs/EmergencyControls'
import { LaneHealthCard } from '@/components/acs-health/LaneHealthCard'
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
  const { locale } = useLocale()
  const [health, setHealth] = useState(initialHealth)

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
    <DashboardLayout title={locale === 'ar' ? 'صحة البوابة' : 'Gate health'}>
      <PageHeader
        title={locale === 'ar' ? 'صحة البوابة' : 'Gate health'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS', href: `/tenant/events/${event.id}/acs` },
          { label: locale === 'ar' ? 'صحة البوابة' : 'Gate health' },
        ]}
        actions={<LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/access-logs`}>{locale === 'ar' ? 'سجلات الوصول' : 'Access logs'}</LocalizedLink>}
      />
      <PageContent>
        <div className="flex flex-wrap items-center gap-3">
          <span>{locale === 'ar' ? 'التكامل' : 'Integration'}:</span>
          <StatusBadge status={health.integration_status} />
        </div>

        {health.active_emergency && (
          <p className="mt-4 rounded-lg bg-amber-100 p-3 text-amber-900" role="alert">
            {locale === 'ar' ? 'خروج الطوارئ نشط' : 'Emergency egress is active'}
          </p>
        )}

        <section className="mt-6">
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
        </section>

        <section className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {health.lanes.map((lane) => (
            <LaneHealthCard
              key={lane.lane_id}
              laneId={lane.lane_id}
              healthStatus={lane.health_status}
              lastSeenAt={lane.last_seen_at}
              activeEmergency={health.active_emergency}
            />
          ))}
        </section>
      </PageContent>
    </DashboardLayout>
  )
}
