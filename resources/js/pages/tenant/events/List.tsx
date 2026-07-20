import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import PublishReadinessBadge from '@/components/events/PublishReadinessBadge'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import { DataTable } from '@/components/tables'
import { useLocale } from '@/hooks/useLocale'
import { labelForEventTier, requiresTicketing } from '@/lib/eventOptions'
import type { PublishReadinessContext } from '@/lib/publishReadinessCatalog'

type EventRow = {
  id: string
  name: { en: string; ar: string }
  status: string
  tier: string
  event_type?: string
  registration_mode?: string
  timezone: string
  start_at?: string | null
  registration_url?: string | null
  readiness?: string[]
}

type Props = {
  events: EventRow[]
}

function readinessContextFor(event: EventRow): PublishReadinessContext {
  return {
    status: event.status,
    requiresTicketing: requiresTicketing(event.tier, event.registration_mode ?? 'free_registration'),
  }
}

export default function EventList({ events }: Props) {
  const { locale, t } = useLocale()

  return (
    <DashboardLayout title={t('events')}>
      <PageHeader
        title={t('events')}
        description={t('eventListDescription')}
        breadcrumbs={[{ label: t('overview'), href: '/dashboard' }, { label: t('events') }]}
        actions={
          <LocalizedLink className="button-primary" href="/tenant/events/create">
            {t('eventListNewEvent')}
          </LocalizedLink>
        }
      />
      <PageContent>
        {events.length === 0 ? (
          <EmptyState title={t('eventListNoEvents')} detail={t('eventListNoEventsDetail')} />
        ) : (
          <DataTable
            rows={events as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              {
                key: 'name',
                header: t('eventListName'),
                render: (row) => {
                  const event = row as unknown as EventRow

                  return <LocalizedLink href={`/tenant/events/${event.id}`} className="font-medium text-sky-700 hover:underline">{event.name[locale]}</LocalizedLink>
                },
              },
              {
                key: 'tier',
                header: t('eventListTier'),
                render: (row) => labelForEventTier(String((row as unknown as EventRow).tier), locale),
              },
              { key: 'event_type', header: t('eventListType') },
              { key: 'registration_mode', header: t('eventListRegistration') },
              {
                key: 'status',
                header: t('status'),
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              {
                key: 'publish_readiness',
                header: t('eventListPublishReadiness'),
                render: (row) => {
                  const event = row as unknown as EventRow

                  return (
                    <PublishReadinessBadge
                      readiness={event.readiness ?? []}
                      context={readinessContextFor(event)}
                    />
                  )
                },
              },
              { key: 'timezone', header: t('eventListTimezone') },
              {
                key: 'actions',
                header: t('actions'),
                render: (row) => {
                  const event = row as unknown as EventRow

                  return (
                    <div className="ta-table-actions">
                      <LocalizedLink href={`/tenant/events/${event.id}`} className="ta-table-action">
                        {t('view')}
                      </LocalizedLink>
                      <LocalizedLink href={`/tenant/events/${event.id}/edit`} className="ta-table-action">
                        {t('edit')}
                      </LocalizedLink>
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
