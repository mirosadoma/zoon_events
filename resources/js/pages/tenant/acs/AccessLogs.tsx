import LocalizedLink from '@/components/routing/LocalizedLink'
import { useEffect, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import { ACS_HEALTH_POLL_INTERVAL_MS } from '@/lib/acs-polling'
import type { AccessEvent } from '@/types/phase4'

type EventRow = { id: string; name: { en: string; ar: string } }

type Props = {
  event: EventRow
  tenantId: string
  accessEvents: AccessEvent[]
}

export default function AcsAccessLogs({ event, tenantId, accessEvents: initialEvents }: Props) {
  const { locale, t } = useLocale()
  const [events, setEvents] = useState(initialEvents)
  const ar = locale === 'ar'

  useEffect(() => {
    let active = true

    async function poll() {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/acs/gate-events?limit=50`, {
        credentials: 'include',
        headers: { Accept: 'application/json', 'X-Tenant-ID': tenantId },
      })
      const body = await response.json()
      if (active && response.ok) {
        setEvents(body.data ?? [])
      }
    }

    const timer = window.setInterval(() => void poll(), ACS_HEALTH_POLL_INTERVAL_MS)
    return () => {
      active = false
      window.clearInterval(timer)
    }
  }, [event.id, tenantId])

  return (
    <DashboardLayout title={ar ? 'سجلات الوصول' : 'Access logs'}>
      <PageHeader
        title={ar ? 'سجلات الوصول' : 'Access logs'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: ar ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS', href: `/tenant/events/${event.id}/acs` },
          { label: ar ? 'سجلات الوصول' : 'Access logs' },
        ]}
        actions={(
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/gate-health`}>
            {ar ? 'صحة البوابة' : 'Gate health'}
          </LocalizedLink>
        )}
      />
      <PageContent>
        {events.length === 0 ? (
          <EmptyState
            title={ar ? 'لا توجد أحداث وصول' : 'No access events yet'}
            detail={ar ? 'ستظهر قرارات البوابة هنا عند حدوثها.' : 'Gate decisions will appear here as they happen.'}
          />
        ) : (
          <DataTable
            title={ar ? 'أحدث الأحداث' : 'Recent events'}
            rows={events as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              {
                key: 'occurred_at',
                header: ar ? 'الوقت' : 'Occurred',
                render: (row) => <span className="whitespace-nowrap text-[var(--muted)]">{String(row.occurred_at)}</span>,
              },
              {
                key: 'event_type',
                header: ar ? 'النوع' : 'Type',
              },
              {
                key: 'direction',
                header: ar ? 'الاتجاه' : 'Direction',
              },
              {
                key: 'decision',
                header: ar ? 'القرار' : 'Decision',
                render: (row) =>
                  row.decision ? <StatusBadge status={String(row.decision)} /> : <span className="text-[var(--muted)]">—</span>,
              },
              {
                key: 'reason_code',
                header: ar ? 'السبب' : 'Reason',
                render: (row) => <span className="font-mono text-xs">{String(row.reason_code)}</span>,
              },
            ]}
          />
        )}
      </PageContent>
    </DashboardLayout>
  )
}
