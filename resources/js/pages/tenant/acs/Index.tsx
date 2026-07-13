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

  return (
    <DashboardLayout title={locale === 'ar' ? 'نظام التحكم بالوصول' : 'ACS overview'}>
      <PageHeader
        title={locale === 'ar' ? 'نظام التحكم بالوصول' : 'ACS overview'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS' },
        ]}
      />
      <PageContent>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <p className="text-sm text-slate-500">{locale === 'ar' ? 'المناطق' : 'Zones'}</p>
            <p className="text-2xl font-semibold">{overview.zones_total}</p>
            <LocalizedLink className="text-sm hover:underline" href={`/tenant/events/${event.id}/acs/zones`}>{locale === 'ar' ? 'إدارة' : 'Manage'}</LocalizedLink>
          </div>
          <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <p className="text-sm text-slate-500">{locale === 'ar' ? 'المسارات' : 'Lanes'}</p>
            <p className="text-2xl font-semibold">{overview.lanes_total}</p>
            <LocalizedLink className="text-sm hover:underline" href={`/tenant/events/${event.id}/acs/lanes`}>{locale === 'ar' ? 'إدارة' : 'Manage'}</LocalizedLink>
          </div>
          <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <p className="text-sm text-slate-500">{locale === 'ar' ? 'القواعد' : 'Rules'}</p>
            <p className="text-2xl font-semibold">{overview.rules_total}</p>
            <LocalizedLink className="text-sm hover:underline" href={`/tenant/events/${event.id}/acs/rules`}>{locale === 'ar' ? 'إدارة' : 'Manage'}</LocalizedLink>
          </div>
          <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <p className="text-sm text-slate-500">{locale === 'ar' ? 'التكامل' : 'Integration'}</p>
            <StatusBadge status={overview.integration_status} />
            <p className="mt-2 text-sm">
              {locale === 'ar' ? 'بوابات غير متصلة' : 'Offline gates'}: <StatusBadge status={overview.gates_offline > 0 ? 'offline' : 'healthy'} label={String(overview.gates_offline)} />
            </p>
          </div>
        </div>

        {overview.active_emergency && (
          <p className="mt-4 rounded-lg bg-amber-100 p-3 text-amber-900" role="alert">
            {locale === 'ar' ? 'خروج الطوارئ نشط' : 'Emergency egress is active'}
          </p>
        )}

        <section className="mt-6">
          <EmergencyControls eventId={event.id} tenantId={tenantId} activeEmergency={overview.active_emergency} />
        </section>

        <section className="mt-8 flex flex-wrap gap-3">
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/access-logs`}>{locale === 'ar' ? 'سجلات الوصول' : 'Access logs'}</LocalizedLink>
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/gate-health`}>{locale === 'ar' ? 'صحة البوابة' : 'Gate health'}</LocalizedLink>
        </section>

        <section className="mt-8">
          <h2 className="mb-4 text-lg font-semibold">{locale === 'ar' ? 'أحدث أحداث البوابة' : 'Latest gate events'}</h2>
          {overview.latest_gate_events.length === 0 ? (
            <EmptyState title={locale === 'ar' ? 'لا توجد أحداث بعد' : 'No gate events yet'} />
          ) : (
            <DataTable
              rows={overview.latest_gate_events as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                { key: 'event_type', header: locale === 'ar' ? 'النوع' : 'Type' },
                { key: 'decision', header: locale === 'ar' ? 'القرار' : 'Decision' },
                { key: 'reason_code', header: locale === 'ar' ? 'السبب' : 'Reason' },
                { key: 'occurred_at', header: locale === 'ar' ? 'الوقت' : 'Time' },
              ]}
            />
          )}
        </section>
      </PageContent>
    </DashboardLayout>
  )
}
