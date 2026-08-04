import { useState } from 'react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import SideDetailPane, { SideDetailInfoGrid } from '@/components/layout/SideDetailPane'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { FunnelStrip } from '@/components/dashboard/DashboardCharts'
import EventReportVenuesMap, { type EventVenueMarker } from '@/components/reports/EventReportVenuesMap'
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
  checkin_rate?: number | null
  revenue_minor?: number
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

type HourRow = {
  hour: string
  accepted_scans: number
  unique_attendees: number
}

type RejectReason = {
  reason: string
  count: number
  percent?: number | null
}

type FunnelStep = {
  key: string
  count: number
  conversion_from_previous: number | null
}

type Report = {
  summary: Record<string, Metric | string>
  orders_by_status: OrderStatusRow[]
  categories: NamedBreakdown[]
  ticket_types: NamedBreakdown[]
  checkins_by_day: DayRow[]
  checkins_by_hour?: HourRow[]
  funnel?: FunnelStep[]
  venue_markers?: EventVenueMarker[]
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
  [key: string]: unknown
}

type ReportTab = 'summary' | 'funnel' | 'registration' | 'checkin' | 'venues' | 'onsite'

type ReportSelection =
  | { table: 'orders'; key: string }
  | { table: 'categories'; key: string }
  | { table: 'ticketTypes'; key: string }
  | { table: 'rejectReasons'; key: string }
  | null

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

function DualBars({
  rows,
  labelKey,
  emptyTitle,
}: {
  rows: Array<{ key: string; a: number; b: number; label: string }>
  labelKey: 'day' | 'hour'
  emptyTitle: string
}) {
  const { t } = useLocale()
  const max = Math.max(1, ...rows.map((row) => Math.max(row.a, row.b)))
  if (rows.every((row) => row.a === 0 && row.b === 0)) {
    return <EmptyState title={emptyTitle} />
  }

  return (
    <div>
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
      <div className="flex gap-1.5 overflow-x-auto pb-1">
        {rows.map((row) => {
          const aHeight = Math.round((row.a / max) * 100)
          const bHeight = Math.round((row.b / max) * 100)
          return (
            <div key={row.key} className="flex w-10 shrink-0 flex-col items-center gap-1" title={`${row.label}: ${row.a} / ${row.b}`}>
              <div className="flex h-28 w-full items-end justify-center gap-0.5">
                <div className="w-2 rounded-t bg-sky-500/80" style={{ height: `${aHeight}%`, minHeight: row.a > 0 ? 4 : 0 }} />
                <div className="w-2 rounded-t bg-emerald-500/80" style={{ height: `${bHeight}%`, minHeight: row.b > 0 ? 4 : 0 }} />
              </div>
              <span className="text-[10px] text-slate-500">
                {labelKey === 'day' ? row.label.slice(5) : row.label.slice(11, 16)}
              </span>
            </div>
          )
        })}
      </div>
    </div>
  )
}

export default function EventReport({ event, report }: Props) {
  const { locale, t, localizedPath } = useLocale()
  const [tab, setTab] = useState<ReportTab>('summary')
  const [selection, setSelection] = useState<ReportSelection>(null)
  const summary = report.summary ?? {}
  const currency = typeof summary.currency === 'string' ? summary.currency : 'EGP'
  const revenue = metricOf(summary, 'revenue_minor')
  const categories = report.categories ?? []
  const ticketTypes = report.ticket_types ?? []
  const ordersByStatus = report.orders_by_status ?? []
  const checkinsByDay = report.checkins_by_day ?? []
  const checkinsByHour = report.checkins_by_hour ?? []
  const rejectReasons = report.top_reject_reasons ?? []
  const funnel = report.funnel ?? []
  const venueMarkers = report.venue_markers ?? []
  const badgeJobs = report.badge_jobs ?? { by_status: { queued: 0, printed: 0, failed: 0 }, reprints: 0 }
  const kiosks = report.kiosks ?? { total: 0, online: 0, offline: 0, degraded: 0, retired: 0, registered: 0 }
  const peakHour = metricOf(summary, 'peak_hour')
  const peakHourScans = metricOf(summary, 'peak_hour_scans')

  const selectedOrder = selection?.table === 'orders'
    ? ordersByStatus.find((row) => row.status === selection.key) ?? null
    : null
  const selectedCategory = selection?.table === 'categories'
    ? categories.find((row) => String(row.id ?? row.name ?? 'unassigned') === selection.key) ?? null
    : null
  const selectedTicketType = selection?.table === 'ticketTypes'
    ? ticketTypes.find((row) => String(row.id ?? row.name ?? 'unassigned-ticket') === selection.key) ?? null
    : null
  const selectedRejectReason = selection?.table === 'rejectReasons'
    ? rejectReasons.find((row) => row.reason === selection.key) ?? null
    : null

  const paneOpen = selectedOrder !== null || selectedCategory !== null || selectedTicketType !== null || selectedRejectReason !== null
  const paneTitle = selectedOrder
    ? String(selectedOrder.status)
    : selectedCategory
      ? (locale === 'ar' && selectedCategory.name_ar ? selectedCategory.name_ar : selectedCategory.name)
      : selectedTicketType
        ? (locale === 'ar' && selectedTicketType.name_ar ? selectedTicketType.name_ar : selectedTicketType.name)
        : selectedRejectReason
          ? selectedRejectReason.reason
          : ''

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

  const tabs: Array<{ id: ReportTab; label: string }> = [
    { id: 'summary', label: t('reportTabSummary') },
    { id: 'funnel', label: t('reportTabFunnel') },
    { id: 'registration', label: t('reportTabRegistration') },
    { id: 'checkin', label: t('reportTabCheckIn') },
    { id: 'venues', label: t('reportTabVenues') },
    { id: 'onsite', label: t('reportTabOnsite') },
  ]

  const funnelLabel = (key: string): string => {
    switch (key) {
      case 'invited': return t('reportFunnelInvited')
      case 'registered': return t('reportFunnelRegistered')
      case 'paid': return t('reportFunnelPaid')
      case 'credentialed': return t('reportFunnelCredentialed')
      case 'checked_in': return t('reportFunnelCheckedIn')
      default: return key
    }
  }

  const exportHref = localizedPath(`/tenant/events/${event.id}/reports/export`)

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
        actions={(
          <div className="flex flex-wrap gap-2">
            <a className="button-secondary" href={exportHref}>{t('reportExportCsv')}</a>
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}`}>{t('eventDetail')}</LocalizedLink>
          </div>
        )}
      />
      <PageContent>
        <div
          className="mb-6 inline-flex max-w-full flex-wrap rounded-lg border border-[var(--border)] bg-[var(--surface)] p-1"
          role="tablist"
          aria-label={t('reportTabsLabel')}
        >
          {tabs.map((item) => (
            <button
              key={item.id}
              type="button"
              role="tab"
              aria-selected={tab === item.id}
              onClick={() => {
                setTab(item.id)
                setSelection(null)
              }}
              className={`rounded-md px-3 py-1.5 text-sm font-medium transition ${
                tab === item.id
                  ? 'bg-[var(--brand)] text-white'
                  : 'text-[var(--muted)] hover:text-[var(--ink)]'
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>

        {tab === 'summary' ? (
          <section className="state-panel">
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{t('reportSectionSummary')}</h2>
            <div className="mt-4 mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-800 dark:bg-slate-950/40">
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
          </section>
        ) : null}

        {tab === 'funnel' ? (
          <section className="state-panel">
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{t('reportFunnelTitle')}</h2>
            <div className="mt-4">
              {funnel.length === 0 ? (
                <EmptyState title={t('reportMetricUnavailable')} />
              ) : (
                <FunnelStrip
                  steps={funnel.map((step) => ({
                    key: step.key,
                    label: funnelLabel(step.key),
                    count: step.count,
                    conversion_from_previous: step.conversion_from_previous,
                  }))}
                />
              )}
            </div>
          </section>
        ) : null}

        {tab === 'registration' ? (
          <section className="state-panel space-y-6">
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{t('reportSectionRegistrationOrders')}</h2>
            <div className="grid gap-6 lg:grid-cols-2">
              <div>
                <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{t('reportOrdersByStatus')}</h3>
                {ordersByStatus.length === 0 ? (
                  <EmptyState title={t('reportNoOrders')} />
                ) : (
                  <DataTable
                    rows={ordersByStatus as unknown as Record<string, unknown>[]}
                    getRowKey={(row) => String(row.status)}
                    selectedRowKey={selection?.table === 'orders' ? selection.key : null}
                    onRowClick={(row) => setSelection({ table: 'orders', key: String(row.status) })}
                    columns={[
                      { key: 'status', header: t('status'), render: (row) => <StatusBadge status={String(row.status)} /> },
                      { key: 'count', header: t('reportCount'), render: (row) => String(row.count) },
                      { key: 'revenue_minor', header: t('reportRevenue'), render: (row) => formatMoney(Number(row.revenue_minor ?? 0), currency, locale) },
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
                    selectedRowKey={selection?.table === 'categories' ? selection.key : null}
                    onRowClick={(row) => setSelection({ table: 'categories', key: String(row.id ?? row.name ?? 'unassigned') })}
                    columns={[
                      { key: 'name', header: t('reportCategory'), render: (row) => (locale === 'ar' && row.name_ar ? String(row.name_ar) : String(row.name)) },
                      { key: 'attendees', header: t('reportRegistrations'), render: (row) => String(row.attendees) },
                      { key: 'checked_in', header: t('reportCheckedInAttendees'), render: (row) => String(row.checked_in) },
                      { key: 'checkin_rate', header: t('reportCheckinRate'), render: (row) => (row.checkin_rate == null ? '—' : `${row.checkin_rate}%`) },
                      { key: 'revenue_minor', header: t('reportRevenue'), render: (row) => formatMoney(Number(row.revenue_minor ?? 0), currency, locale) },
                    ]}
                  />
                )}
              </div>
            </div>
            <div>
              <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{t('reportByTicketType')}</h3>
              {ticketTypes.length === 0 ? (
                <EmptyState title={t('reportNoTicketTypes')} />
              ) : (
                <DataTable
                  rows={ticketTypes as unknown as Record<string, unknown>[]}
                  getRowKey={(row) => String(row.id ?? row.name ?? 'unassigned-ticket')}
                  selectedRowKey={selection?.table === 'ticketTypes' ? selection.key : null}
                  onRowClick={(row) => setSelection({ table: 'ticketTypes', key: String(row.id ?? row.name ?? 'unassigned-ticket') })}
                  columns={[
                    { key: 'name', header: t('ticketTypes'), render: (row) => (locale === 'ar' && row.name_ar ? String(row.name_ar) : String(row.name)) },
                    { key: 'attendees', header: t('reportRegistrations'), render: (row) => String(row.attendees) },
                    { key: 'checked_in', header: t('reportCheckedInAttendees'), render: (row) => String(row.checked_in) },
                    { key: 'checkin_rate', header: t('reportCheckinRate'), render: (row) => (row.checkin_rate == null ? '—' : `${row.checkin_rate}%`) },
                    { key: 'revenue_minor', header: t('reportRevenue'), render: (row) => formatMoney(Number(row.revenue_minor ?? 0), currency, locale) },
                  ]}
                />
              )}
            </div>
          </section>
        ) : null}

        {tab === 'checkin' ? (
          <section className="state-panel space-y-8">
            <div>
              <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{t('reportSectionCheckIn')}</h2>
              <div className="mt-4">
                <DualBars
                  labelKey="day"
                  emptyTitle={t('reportNoCheckInActivity')}
                  rows={checkinsByDay.map((day) => ({
                    key: day.date,
                    label: day.date,
                    a: day.accepted_scans,
                    b: day.unique_attendees,
                  }))}
                />
              </div>
            </div>
            <div>
              <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
                <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-200">{t('reportHourlyTitle')}</h3>
                {peakHour.available ? (
                  <p className="text-sm text-slate-500">
                    {t('reportPeakHour')}: <span className="font-medium text-slate-800 dark:text-slate-100">{String(peakHour.value)}</span>
                    {peakHourScans.available ? ` (${peakHourScans.value})` : ''}
                  </p>
                ) : null}
              </div>
              <DualBars
                labelKey="hour"
                emptyTitle={t('reportNoCheckInActivity')}
                rows={checkinsByHour.map((hour) => ({
                  key: hour.hour,
                  label: hour.hour,
                  a: hour.accepted_scans,
                  b: hour.unique_attendees,
                }))}
              />
            </div>
            <div>
              <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{t('reportTopRejectReasons')}</h3>
              {rejectReasons.length === 0 ? (
                <EmptyState title={t('reportNoRejectReasons')} />
              ) : (
                <DataTable
                  rows={rejectReasons as unknown as Record<string, unknown>[]}
                  getRowKey={(row) => String(row.reason)}
                  selectedRowKey={selection?.table === 'rejectReasons' ? selection.key : null}
                  onRowClick={(row) => setSelection({ table: 'rejectReasons', key: String(row.reason) })}
                  columns={[
                    { key: 'reason', header: t('reportReason'), render: (row) => String(row.reason) },
                    { key: 'count', header: t('reportCount'), render: (row) => String(row.count) },
                    { key: 'percent', header: t('reportShareOfRejects'), render: (row) => (row.percent == null ? '—' : `${row.percent}%`) },
                  ]}
                />
              )}
            </div>
          </section>
        ) : null}

        {tab === 'venues' ? (
          <section className="state-panel">
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{t('reportVenuesMapTitle')}</h2>
            <p className="mt-1 text-sm text-slate-500">{t('reportVenuesMapHint')}</p>
            <div className="mt-4">
              <EventReportVenuesMap
                markers={venueMarkers}
                emptyLabel={t('reportVenuesMapEmpty')}
                missingApiKeyLabel={t('mapPickerMissingApiKey')}
              />
            </div>
          </section>
        ) : null}

        {tab === 'onsite' ? (
          <section className="state-panel">
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{t('reportSectionOnsite')}</h2>
            <div className="mt-4 grid gap-6 lg:grid-cols-2">
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
          </section>
        ) : null}
      </PageContent>

      <SideDetailPane open={paneOpen} title={paneTitle} onClose={() => setSelection(null)}>
        {selectedOrder ? (
          <SideDetailInfoGrid
            items={[
              { label: t('status'), value: <StatusBadge status={selectedOrder.status} /> },
              { label: t('reportCount'), value: String(selectedOrder.count) },
              { label: t('reportRevenue'), value: formatMoney(selectedOrder.revenue_minor, currency, locale) },
            ]}
          />
        ) : null}
        {selectedCategory ? (
          <SideDetailInfoGrid
            items={[
              { label: t('reportCategory'), value: locale === 'ar' && selectedCategory.name_ar ? selectedCategory.name_ar : selectedCategory.name },
              { label: t('reportRegistrations'), value: String(selectedCategory.attendees) },
              { label: t('reportCheckedInAttendees'), value: String(selectedCategory.checked_in) },
              { label: t('reportCheckinRate'), value: selectedCategory.checkin_rate == null ? '—' : `${selectedCategory.checkin_rate}%` },
              { label: t('reportRevenue'), value: formatMoney(Number(selectedCategory.revenue_minor ?? 0), currency, locale) },
            ]}
          />
        ) : null}
        {selectedTicketType ? (
          <SideDetailInfoGrid
            items={[
              { label: t('ticketTypes'), value: locale === 'ar' && selectedTicketType.name_ar ? selectedTicketType.name_ar : selectedTicketType.name },
              { label: t('reportRegistrations'), value: String(selectedTicketType.attendees) },
              { label: t('reportCheckedInAttendees'), value: String(selectedTicketType.checked_in) },
              { label: t('reportCheckinRate'), value: selectedTicketType.checkin_rate == null ? '—' : `${selectedTicketType.checkin_rate}%` },
              { label: t('reportRevenue'), value: formatMoney(Number(selectedTicketType.revenue_minor ?? 0), currency, locale) },
            ]}
          />
        ) : null}
        {selectedRejectReason ? (
          <SideDetailInfoGrid
            items={[
              { label: t('reportReason'), value: selectedRejectReason.reason },
              { label: t('reportCount'), value: String(selectedRejectReason.count) },
              { label: t('reportShareOfRejects'), value: selectedRejectReason.percent == null ? '—' : `${selectedRejectReason.percent}%` },
            ]}
          />
        ) : null}
      </SideDetailPane>
    </DashboardLayout>
  )
}
