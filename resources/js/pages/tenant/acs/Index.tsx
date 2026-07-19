import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmergencyControls } from '@/components/acs/EmergencyControls'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import type { AccessEvent } from '@/types/phase4'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type Overview = {
  zones_total: number
  lanes_total: number
  rules_total: number
  integration_status: string
  active_emergency: boolean
  gates_offline: number
  latest_gate_events: AccessEvent[]
}

type Props = {
  event: EventRow
  tenantId: string
  overview: Overview
}

export default function AcsOverview({ event, tenantId, overview }: Props) {
  const { locale, t } = useLocale()

  const stats = [
    {
      label: t('acsPageZones'),
      value: overview.zones_total,
      href: `/tenant/events/${event.id}/acs/zones`,
      tone: 'ta-stat-card-sky',
    },
    {
      label: t('acsPageLanes'),
      value: overview.lanes_total,
      href: `/tenant/events/${event.id}/acs/lanes`,
      tone: 'ta-stat-card-violet',
    },
    {
      label: t('acsPageRules'),
      value: overview.rules_total,
      href: `/tenant/events/${event.id}/acs/rules`,
      tone: 'ta-stat-card-emerald',
    },
  ]

  return (
    <DashboardLayout title={t('acsPageTitle')}>
      <PageHeader
        title={t('acsPageTitle')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS' },
        ]}
        actions={(
          <div className="flex flex-wrap gap-2">
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/access-logs`}>
              {t('acsPageAccessLogs')}
            </LocalizedLink>
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/gate-health`}>
              {t('acsPageGateHealth')}
            </LocalizedLink>
          </div>
        )}
      />
      <PageContent>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {stats.map((stat) => (
            <div key={stat.label} className={`ta-card ta-stat-card ${stat.tone}`}>
              <p className="ta-stat-label">{stat.label}</p>
              <p className="ta-stat-value">{stat.value}</p>
              <LocalizedLink className="mt-3 inline-block text-sm font-medium text-[var(--brand)] hover:underline" href={stat.href}>
                {t('acsPageManage')}
              </LocalizedLink>
            </div>
          ))}
          <div className="ta-card ta-stat-card ta-stat-card-amber">
            <p className="ta-stat-label">{t('acsPageIntegration')}</p>
            <div className="mt-2">
              <StatusBadge status={overview.integration_status} size="md" />
            </div>
            <p className="mt-3 text-sm text-[var(--muted)]">
              {t('acsPageOfflineGates')}:{' '}
              <StatusBadge status={overview.gates_offline > 0 ? 'offline' : 'healthy'} label={String(overview.gates_offline)} />
            </p>
          </div>
        </div>

        {overview.active_emergency && (
          <p className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200" role="alert">
            {t('acsPageEmergencyActive')}
          </p>
        )}

        <section className="ta-card mt-6">
          <h2 className="text-lg font-semibold text-[var(--ink)]">{t('acsPageEmergency')}</h2>
          <p className="mt-1 text-sm text-[var(--muted)]">
            {t('acsPageEmergencyDescription')}
          </p>
          <div className="mt-4">
            <EmergencyControls eventId={event.id} tenantId={tenantId} activeEmergency={overview.active_emergency} />
          </div>
        </section>

        <section className="mt-8">
          {overview.latest_gate_events.length === 0 ? (
            <EmptyState title={t('acsPageNoEvents')} />
          ) : (
            <DataTable
              title={t('acsPageLatestEvents')}
              rows={overview.latest_gate_events as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                { key: 'event_type', header: t('acsPageType') },
                { key: 'decision', header: t('acsPageDecision') },
                { key: 'reason_code', header: t('reason') },
                { key: 'occurred_at', header: t('time') },
              ]}
            />
          )}
        </section>
      </PageContent>
    </DashboardLayout>
  )
}
