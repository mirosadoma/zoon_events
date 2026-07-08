import DashboardLayout from '@/layouts/DashboardLayout'
import { PageContent, PageHeader } from '@/components/layout'
import { AuditTimeline } from '@/components/feedback'
import { PageSkeleton as PageSkeletonLoader } from '@/components/loaders'
import { useLocale } from '@/hooks/useLocale'
import en from '@/locales/en'
import ar from '@/locales/ar'

type Overview = {
  events_total: number
  events_published: number
  attendees_total: number
  orders_total: number
  credentials_issued: number
  checkins_today: number
  kiosks_active: number
  gates_active: number
  scans_failed: number
  recent_audit_events: Array<{
    id: string
    actor: string
    action: string
    outcome: string
    occurred_at: string
  }>
}

type Props = {
  overview?: Overview
  title?: string
}

export default function FoundationDashboard({ overview, title }: Props) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en

  if (!overview) {
    return (
      <DashboardLayout title={title ?? messages.overviewTitle}>
        <PageSkeletonLoader />
      </DashboardLayout>
    )
  }

  const cards = [
    { label: messages.overviewEvents, value: overview.events_total },
    { label: messages.overviewPublished, value: overview.events_published },
    { label: messages.overviewAttendees, value: overview.attendees_total },
    { label: messages.overviewOrders, value: overview.orders_total },
    { label: messages.overviewCredentials, value: overview.credentials_issued },
    { label: messages.overviewCheckinsToday, value: overview.checkins_today },
    { label: messages.overviewKiosksActive, value: overview.kiosks_active },
    { label: messages.overviewScansFailed, value: overview.scans_failed },
  ]

  return (
    <DashboardLayout title={title ?? messages.overviewTitle}>
      <PageHeader
        title={messages.overviewTitle}
        description={messages.overviewDescription}
        breadcrumbs={[{ label: messages.overview }]}
      />
      <PageContent>
        <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          {cards.map((card) => (
            <article key={card.label} className="state-panel">
              <p className="text-sm uppercase tracking-wide text-slate-500">{card.label}</p>
              <p className="mt-2 text-3xl font-semibold">{card.value}</p>
            </article>
          ))}
        </section>

        <section className="state-panel">
          <h2 className="text-lg font-semibold">{messages.overviewRecentAudit}</h2>
          {overview.recent_audit_events.length === 0 ? (
            <p className="mt-3 text-slate-600 dark:text-slate-300">{messages.emptyAudit}</p>
          ) : (
            <div className="mt-4">
              <AuditTimeline events={overview.recent_audit_events} />
            </div>
          )}
        </section>
      </PageContent>
    </DashboardLayout>
  )
}
