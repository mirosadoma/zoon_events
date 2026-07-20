import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import { formatMoney } from '@/lib/formatMoney'

type EventRow = {
  id: string
  name: { en: string; ar: string }
  timezone?: string
  status?: string
}

type Metric = {
  value: number | string | null
  available: boolean
  label?: string
}

type NamedBreakdown = {
  id: string | null
  name: string
  name_ar?: string
  attendees: number
  checked_in: number
}

type OrderStatusRow = {
  status: string
  count: number
  revenue_minor: number
}

type DayRow = {
  date: string
  accepted_scans: number
  unique_attendees: number
}

type RejectReason = {
  reason: string
  count: number
}

type Report = {
  summary: Record<string, Metric | string>
  orders_by_status: OrderStatusRow[]
  categories: NamedBreakdown[]
  ticket_types: NamedBreakdown[]
  checkins_by_day: DayRow[]
  badge_jobs: {
    by_status: { queued: number; printed: number; failed: number }
    reprints: number
  }
  top_reject_reasons: RejectReason[]
  kiosks: {
    total: number
    online: number
    offline: number
    degraded: number
    retired: number
    registered: number
  }
  // legacy flat keys kept for compatibility
  [key: string]: unknown
}

type Props = {
  event: EventRow
  tenantId: string
  report: Report
}

function metricOf(summary: Report['summary'], key: string): Metric {
  const value = summary[key]
  if (value && typeof value === 'object' && 'available' in value) {
    return value as Metric
  }

  return { value: null, available: false }
}

function MetricCard({ title, metric, suffix = '' }: { title: string; metric: Metric; suffix?: string }) {
  const { t } = useLocale()

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
      <p className="text-sm text-slate-500">{title}</p>
      {metric.available ? (
        <p className="mt-2 text-2xl font-semibold tabular-nums">
          {metric.value ?? '—'}
          {suffix}
        </p>
      ) : (
        <p className="mt-2 text-sm text-amber-700 dark:text-amber-300">
          {metric.label ?? t('reportMetricUnavailable')}
        </p>
      )}
    </div>
  )
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="state-panel mt-6">
      <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{title}</h2>
      <div className="mt-4">{children}</div>
    </section>
  )
}

function DayBars({ days }: { days: DayRow[] }) {
  const max = Math.max(1, ...days.map((day) => Math.max(day.accepted_scans, day.unique_attendees)))

  return (
    <div className="flex gap-1.5 overflow-x-auto pb-1">
      {days.map((day) => {
        const scanHeight = Math.round((day.accepted_scans / max) * 100)
        const attendeeHeight = Math.round((day.unique_attendees / max) * 100)

        return (
          <div key={day.date} className="flex w-10 shrink-0 flex-col items-center gap-1" title={`${day.date}: ${day.accepted_scans} scans / ${day.unique_attendees} attendees`}>
            <div className="flex h-28 w-full items-end justify-center gap-0.5">
              <div className="w-2 rounded-t bg-sky-500/80" style={{ height: `${scanHeight}%`, minHeight: day.accepted_scans > 0 ? 4 : 0 }} />
              <div className="w-2 rounded-t bg-emerald-500/80" style={{ height: `${attendeeHeight}%`, minHeight: day.unique_attendees > 0 ? 4 : 0 }} />
            </div>
            <span className="text-[10px] text-slate-500">{day.date.slice(5)}</span>
          </div>
        )
      })}
    </div>
  )
}

export default function EventReport({ event, report }: Props) {
  const { locale, t } = useLocale()
  const summary = report.summary ?? {}
  const currency = typeof summary.currency === 'string' ? summary.currency : 'EGP'
  const revenue = metricOf(summary, 'revenue_minor')
  const categories = report.categories ?? []
  const ticketTypes = report.ticket_types ?? []
  const ordersByStatus = report.orders_by_status ?? []
  const checkinsByDay = report.checkins_by_day ?? []
  const rejectReasons = report.top_reject_reasons ?? []
  const badgeJobs = report.badge_jobs ?? { by_status: { queued: 0, printed: 0, failed: 0 }, reprints: 0 }
  const kiosks = report.kiosks ?? { total: 0, online: 0, offline: 0, degraded: 0, retired: 0, registered: 0 }

  const summaryCards: Array<{ key: string; title: string; suffix?: string }> = [
    { key: 'registrations', title: t('reportRegistrations') },
    { key: 'checked_in_attendees', title: t('reportCheckedInAttendees') },
    { key: 'checkin_rate', title: t('reportCheckinRate'), suffix: '%' },
    { key: 'paid_orders', title: t('reportPaidOrders') },
    { key: 'payment_success_rate', title: t('reportPaymentSuccessRate'), suffix: '%' },
    { key: 'credentials_issued', title: t('reportCredentialsIssued') },
    { key: 'credentials_revoked', title: t('reportCredentialsRevoked') },
    { key: 'wallet_adoption', title: t('reportWalletAdoption'), suffix: '%' },
    { key: 'accepted_scans', title: t('reportAcceptedScans') },
    { key: 'rejected_scans', title: t('reportRejectedScans') },
    { key: 'checkin_success_rate', title: t('reportCheckinSuccessRate'), suffix: '%' },
    { key: 'first_scan_success_rate', title: t('reportFirstScanSuccessRate'), suffix: '%' },
    { key: 'badge_prints', title: t('reportBadgePrints') },
    { key: 'badge_reprints', title: t('reportBadgeReprints') },
    { key: 'acs_entries_accepted', title: t('reportAcsAccepted') },
    { key: 'acs_entries_rejected', title: t('reportAcsRejected') },
    { key: 'kiosks_online', title: t('reportKiosksOnline') },
    { key: 'kiosks_total', title: t('reportKiosksTotal') },
  ]

  return (
    <DashboardLayout title={t('reports')}>
      <PageHeader
        title={t('reports')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('reports') },
        ]}
        actions={<LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}`}>{t('eventDetail')}</LocalizedLink>}
      />
      <PageContent>
        <Section title={t('reportSectionSummary')}>
          <div className="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-800 dark:bg-slate-950/40">
            <span className="text-slate-500">{t('reportRevenue')}: </span>
            <span className="font-semibold tabular-nums">
              {revenue.available && typeof revenue.value === 'number'
                ? formatMoney(revenue.value, currency, locale)
                : '—'}
            </span>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {summaryCards.map(({ key, title, suffix }) => (
              <MetricCard key={key} title={title} metric={metricOf(summary, key)} suffix={suffix} />
            ))}
          </div>
        </Section>

        <Section title={t('reportSectionRegistrationOrders')}>
          <div className="grid gap-6 lg:grid-cols-2">
            <div>
              <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{t('reportOrdersByStatus')}</h3>
              {ordersByStatus.length === 0 ? (
                <EmptyState title={t('reportNoOrders')} />
              ) : (
                <DataTable
                  rows={ordersByStatus as unknown as Record<string, unknown>[]}
                  getRowKey={(row) => String(row.status)}
                  columns={[
                    {
                      key: 'status',
                      header: t('status'),
                      render: (row) => <StatusBadge status={String(row.status)} />,
                    },
                    {
                      key: 'count',
                      header: t('reportCount'),
                      render: (row) => String(row.count),
                    },
                    {
                      key: 'revenue_minor',
                      header: t('reportRevenue'),
                      render: (row) => formatMoney(Number(row.revenue_minor ?? 0), currency, locale),
                    },
                  ]}
                />
              )}
            </div>

            <div>
              <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{t('reportByCategory')}</h3>
              {categories.length === 0 ? (
                <EmptyState title={t('reportNoCategories')} />
              ) : (
                <DataTable
                  rows={categories as unknown as Record<string, unknown>[]}
                  getRowKey={(row) => String(row.id ?? row.name ?? 'unassigned')}
                  columns={[
                    {
                      key: 'name',
                      header: t('reportCategory'),
                      render: (row) => (locale === 'ar' && row.name_ar ? String(row.name_ar) : String(row.name)),
                    },
                    {
                      key: 'attendees',
                      header: t('reportRegistrations'),
                      render: (row) => String(row.attendees),
                    },
                    {
                      key: 'checked_in',
                      header: t('reportCheckedInAttendees'),
                      render: (row) => String(row.checked_in),
                    },
                  ]}
                />
              )}
            </div>
          </div>

          <div className="mt-6">
            <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{t('reportByTicketType')}</h3>
            {ticketTypes.length === 0 ? (
              <EmptyState title={t('reportNoTicketTypes')} />
            ) : (
              <DataTable
                rows={ticketTypes as unknown as Record<string, unknown>[]}
                getRowKey={(row) => String(row.id ?? row.name ?? 'unassigned-ticket')}
                columns={[
                  {
                    key: 'name',
                    header: t('ticketTypes'),
                    render: (row) => (locale === 'ar' && row.name_ar ? String(row.name_ar) : String(row.name)),
                  },
                  {
                    key: 'attendees',
                    header: t('reportRegistrations'),
                    render: (row) => String(row.attendees),
                  },
                  {
                    key: 'checked_in',
                    header: t('reportCheckedInAttendees'),
                    render: (row) => String(row.checked_in),
                  },
                ]}
              />
            )}
          </div>
        </Section>

        <Section title={t('reportSectionCheckIn')}>
          <div className="mb-3 flex flex-wrap items-center gap-4 text-xs text-slate-500">
            <span className="inline-flex items-center gap-1.5">
              <span className="inline-block h-2.5 w-2.5 rounded-sm bg-sky-500/80" />
              {t('reportAcceptedScans')}
            </span>
            <span className="inline-flex items-center gap-1.5">
              <span className="inline-block h-2.5 w-2.5 rounded-sm bg-emerald-500/80" />
              {t('reportCheckedInAttendees')}
            </span>
          </div>
          {checkinsByDay.every((day) => day.accepted_scans === 0 && day.unique_attendees === 0) ? (
            <EmptyState title={t('reportNoCheckInActivity')} />
          ) : (
            <DayBars days={checkinsByDay} />
          )}

          <div className="mt-6">
            <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{t('reportTopRejectReasons')}</h3>
            {rejectReasons.length === 0 ? (
              <EmptyState title={t('reportNoRejectReasons')} />
            ) : (
              <DataTable
                rows={rejectReasons as unknown as Record<string, unknown>[]}
                getRowKey={(row) => String(row.reason)}
                columns={[
                  {
                    key: 'reason',
                    header: t('reportReason'),
                    render: (row) => String(row.reason),
                  },
                  {
                    key: 'count',
                    header: t('reportCount'),
                    render: (row) => String(row.count),
                  },
                ]}
              />
            )}
          </div>
        </Section>

        <Section title={t('reportSectionOnsite')}>
          <div className="grid gap-6 lg:grid-cols-2">
            <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
              <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{t('reportBadgeJobs')}</h3>
              <dl className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt className="text-slate-500">{t('reportBadgeQueued')}</dt>
                  <dd className="mt-1 text-lg font-semibold tabular-nums">{badgeJobs.by_status.queued}</dd>
                </div>
                <div>
                  <dt className="text-slate-500">{t('reportBadgePrints')}</dt>
                  <dd className="mt-1 text-lg font-semibold tabular-nums">{badgeJobs.by_status.printed}</dd>
                </div>
                <div>
                  <dt className="text-slate-500">{t('reportBadgeFailed')}</dt>
                  <dd className="mt-1 text-lg font-semibold tabular-nums">{badgeJobs.by_status.failed}</dd>
                </div>
                <div>
                  <dt className="text-slate-500">{t('reportBadgeReprints')}</dt>
                  <dd className="mt-1 text-lg font-semibold tabular-nums">{badgeJobs.reprints}</dd>
                </div>
              </dl>
            </div>

            <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
              <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{t('reportKioskHealth')}</h3>
              <dl className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt className="text-slate-500">{t('reportKiosksTotal')}</dt>
                  <dd className="mt-1 text-lg font-semibold tabular-nums">{kiosks.total}</dd>
                </div>
                <div>
                  <dt className="text-slate-500">{t('reportKiosksOnline')}</dt>
                  <dd className="mt-1 text-lg font-semibold tabular-nums">{kiosks.online}</dd>
                </div>
                <div>
                  <dt className="text-slate-500">{t('reportKiosksOffline')}</dt>
                  <dd className="mt-1 text-lg font-semibold tabular-nums">{kiosks.offline}</dd>
                </div>
                <div>
                  <dt className="text-slate-500">{t('reportKiosksDegraded')}</dt>
                  <dd className="mt-1 text-lg font-semibold tabular-nums">{kiosks.degraded}</dd>
                </div>
              </dl>
            </div>
          </div>
        </Section>
      </PageContent>
    </DashboardLayout>
  )
}
