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
    <DashboardLayout title={t('acsPageAccessLogs')}>
      <PageHeader
        title={t('acsPageAccessLogs')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS', href: `/tenant/events/${event.id}/acs` },
          { label: t('acsPageAccessLogs') },
        ]}
        actions={(
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/gate-health`}>
            {t('acsPageGateHealth')}
          </LocalizedLink>
        )}
      />
      <PageContent>
        {events.length === 0 ? (
          <EmptyState
            title={t('acsPageNoAccessEvents')}
            detail={t('acsPageAccessEventsDescription')}
          />
        ) : (
          <DataTable
            title={t('acsPageRecentEvents')}
            rows={events as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              {
                key: 'occurred_at',
                header: t('acsPageOccurred'),
                render: (row) => <span className="whitespace-nowrap text-[var(--muted)]">{String(row.occurred_at)}</span>,
              },
              {
                key: 'event_type',
                header: t('acsPageType'),
              },
              {
                key: 'direction',
                header: t('acsPageDirection'),
              },
              {
                key: 'decision',
                header: t('acsPageDecision'),
                render: (row) =>
                  row.decision ? <StatusBadge status={String(row.decision)} /> : <span className="text-[var(--muted)]">—</span>,
              },
              {
                key: 'reason_code',
                header: t('reason'),
                render: (row) => <span className="font-mono text-xs">{String(row.reason_code)}</span>,
              },
            ]}
          />
        )}
      </PageContent>
    </DashboardLayout>
  )
}
