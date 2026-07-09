import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import en from '@/locales/en'
import ar from '@/locales/ar'

type EventRow = { id: string; name: { en: string; ar: string } }

type Metric = {
  value: number | string | null
  available: boolean
  label?: string
}

type Report = Record<string, Metric>

type Props = {
  event: EventRow
  tenantId: string
  report: Report
}

function MetricCard({ title, metric, suffix = '' }: { title: string; metric: Metric; suffix?: string }) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
      <p className="text-sm text-slate-500">{title}</p>
      {metric.available ? (
        <p className="mt-2 text-2xl font-semibold">
          {metric.value ?? '—'}
          {suffix}
        </p>
      ) : (
        <p className="mt-2 text-sm text-amber-700 dark:text-amber-300">{metric.label ?? messages.reportMetricUnavailable}</p>
      )}
    </div>
  )
}

export default function EventReport({ event, report }: Props) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en

  const cards: Array<{ key: keyof Report; title: string; suffix?: string }> = [
    { key: 'registrations', title: messages.reportRegistrations },
    { key: 'paid_orders', title: messages.reportPaidOrders },
    { key: 'payment_success_rate', title: messages.reportPaymentSuccessRate, suffix: '%' },
    { key: 'credentials_issued', title: messages.reportCredentialsIssued },
    { key: 'credentials_revoked', title: messages.reportCredentialsRevoked },
    { key: 'wallet_adoption', title: messages.reportWalletAdoption, suffix: '%' },
    { key: 'checkins', title: messages.reportCheckins },
    { key: 'first_scan_success_rate', title: messages.reportFirstScanSuccessRate, suffix: '%' },
    { key: 'checkin_success_rate', title: messages.reportCheckinSuccessRate, suffix: '%' },
    { key: 'badge_prints', title: messages.reportBadgePrints },
    { key: 'acs_entries_accepted', title: messages.reportAcsAccepted },
    { key: 'acs_entries_rejected', title: messages.reportAcsRejected },
  ]

  return (
    <DashboardLayout title={messages.reports}>
      <PageHeader
        title={messages.reports}
        description={event.name[locale]}
        breadcrumbs={[
          { label: messages.overview, href: '/dashboard' },
          { label: messages.events, href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: messages.reports },
        ]}
        actions={<LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}`}>{messages.eventDetail}</LocalizedLink>}
      />
      <PageContent>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {cards.map(({ key, title, suffix }) => (
            <MetricCard key={key} title={title} metric={report[key]} suffix={suffix} />
          ))}
        </div>
      </PageContent>
    </DashboardLayout>
  )
}
