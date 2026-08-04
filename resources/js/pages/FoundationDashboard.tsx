import { usePage } from '@inertiajs/react'
import {
  Activity,
  CalendarDays,
  CreditCard,
  DoorOpen,
  ScanLine,
  ShieldCheck,
  Sparkles,
  Ticket,
  Users,
} from 'lucide-react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { StatCard } from '@/components/cards'
import { PageContent, PageHeader } from '@/components/layout'
import { AuditTimeline, EmptyState } from '@/components/feedback'
import { PageSkeleton as PageSkeletonLoader } from '@/components/loaders'
import LocalizedLink from '@/components/routing/LocalizedLink'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import {
  DashboardSection,
  DaySeriesBars,
  FunnelStrip,
} from '@/components/dashboard/DashboardCharts'
import PublishedVenuesMap, {
  type PublishedVenueMarker,
} from '@/components/dashboard/PublishedVenuesMap'
import { useLocale } from '@/hooks/useLocale'
import { formatMoney } from '@/lib/formatMoney'
import en from '@/locales/en'
import ar from '@/locales/ar'

type DayPoint = { date: string; count: number }

type EventComparisonRow = {
  id: string
  name: { en: string; ar: string }
  status: string
  start_at?: string | null
  end_at?: string | null
  attendees: number
  checked_in: number
  checkin_rate: number | null
  revenue_minor: number
  currency: string
}

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
  registrations_by_day?: DayPoint[]
  checkins_by_day?: DayPoint[]
  funnel?: {
    registered: number
    paid: number
    credentialed: number
    checked_in: number
  }
  events_comparison?: EventComparisonRow[]
  published_venue_markers?: PublishedVenueMarker[]
}

type Props = {
  overview?: Overview
  title?: string
}

type AuthUser = { name?: string | null }

export default function FoundationDashboard({ overview, title }: Props) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const { props } = usePage<{ auth?: { user?: AuthUser | null } }>()
  const userName = props.auth?.user?.name?.trim()

  if (!overview) {
    return (
      <DashboardLayout title={title ?? messages.overviewTitle}>
        <PageSkeletonLoader />
      </DashboardLayout>
    )
  }

  const publishRate = overview.events_total > 0
    ? Math.round((overview.events_published / overview.events_total) * 100)
    : 0

  const registrationsByDay = overview.registrations_by_day ?? []
  const checkinsByDay = overview.checkins_by_day ?? []
  const funnel = overview.funnel ?? { registered: 0, paid: 0, credentialed: 0, checked_in: 0 }
  const eventsComparison = overview.events_comparison ?? []
  const publishedMarkers = overview.published_venue_markers ?? []

  const primaryCards = [
    {
      label: messages.overviewEvents,
      value: overview.events_total,
      icon: CalendarDays,
      accent: 'sky' as const,
      description: `${overview.events_published} ${messages.overviewPublished.toLowerCase()}`,
    },
    {
      label: messages.overviewAttendees,
      value: overview.attendees_total,
      icon: Users,
      accent: 'violet' as const,
      description: messages.overviewAttendeesHint,
    },
    {
      label: messages.overviewOrders,
      value: overview.orders_total,
      icon: CreditCard,
      accent: 'emerald' as const,
      description: messages.overviewOrdersHint,
    },
    {
      label: messages.overviewCheckinsToday,
      value: overview.checkins_today,
      icon: ScanLine,
      accent: 'amber' as const,
      description: messages.overviewCheckinsHint,
    },
  ]

  const secondaryCards = [
    {
      label: messages.overviewPublished,
      value: overview.events_published,
      icon: Ticket,
      status: 'published',
      accent: 'brand' as const,
    },
    {
      label: messages.overviewCredentials,
      value: overview.credentials_issued,
      icon: ShieldCheck,
      accent: 'emerald' as const,
    },
    {
      label: messages.overviewKiosksActive,
      value: overview.kiosks_active,
      icon: Activity,
      accent: 'sky' as const,
    },
    {
      label: messages.overviewGatesActive,
      value: overview.gates_active,
      icon: DoorOpen,
      accent: 'violet' as const,
    },
    {
      label: messages.overviewScansFailed,
      value: overview.scans_failed,
      icon: ScanLine,
      status: overview.scans_failed > 0 ? 'failed' : 'healthy',
      accent: overview.scans_failed > 0 ? ('rose' as const) : ('emerald' as const),
    },
  ]

  return (
    <DashboardLayout title={title ?? messages.overviewTitle}>
      <PageHeader
        title={messages.overviewTitle}
        description={messages.overviewDescription}
      />
      <PageContent>
        <section className="ta-dashboard-hero" aria-label={messages.overviewWelcome}>
          <div className="ta-dashboard-hero-content">
            <p className="ta-dashboard-hero-kicker">
              <Sparkles className="h-4 w-4" aria-hidden />
              {messages.overviewWelcome}
            </p>
            <h2 className="ta-dashboard-hero-title">
              {userName
                ? messages.overviewGreeting.replace(':name', userName)
                : messages.overviewTitle}
            </h2>
            <p className="ta-dashboard-hero-subtitle">{messages.overviewHeroSubtitle}</p>
          </div>
          <div className="ta-dashboard-hero-metrics">
            <div className="ta-dashboard-hero-pill">
              <span className="ta-dashboard-hero-pill-label">{messages.overviewPublishRate}</span>
              <strong>{publishRate}%</strong>
            </div>
            <div className="ta-dashboard-hero-pill">
              <span className="ta-dashboard-hero-pill-label">{messages.overviewOpsHealth}</span>
              <strong>{overview.scans_failed > 0 ? messages.overviewNeedsAttention : messages.overviewHealthy}</strong>
            </div>
          </div>
        </section>

        <section className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {primaryCards.map((card) => (
            <StatCard
              key={card.label}
              label={card.label}
              value={card.value}
              icon={card.icon}
              description={card.description}
              accent={card.accent}
              featured
            />
          ))}
        </section>

        <section className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
          {secondaryCards.map((card) => (
            <StatCard
              key={card.label}
              label={card.label}
              value={card.value}
              icon={card.icon}
              status={card.status}
              accent={card.accent}
            />
          ))}
        </section>

        <DashboardSection title={messages.overviewMapTitle} description={messages.overviewMapHint}>
          <PublishedVenuesMap
            markers={publishedMarkers}
            emptyLabel={messages.overviewMapEmpty}
            missingApiKeyLabel={messages.mapPickerMissingApiKey}
          />
        </DashboardSection>

        <DashboardSection title={messages.overviewChartsTitle} description={messages.overviewChartsHint}>
          <div className="grid gap-6 lg:grid-cols-2">
            <div>
              <h3 className="mb-3 text-sm font-semibold text-[var(--ink)]">{messages.overviewRegistrationsTrend}</h3>
              <DaySeriesBars days={registrationsByDay} emptyLabel={messages.overviewNoChartData} barClassName="bg-violet-500/80" />
            </div>
            <div>
              <h3 className="mb-3 text-sm font-semibold text-[var(--ink)]">{messages.overviewCheckinsTrend}</h3>
              <DaySeriesBars days={checkinsByDay} emptyLabel={messages.overviewNoChartData} barClassName="bg-amber-500/80" />
            </div>
          </div>
        </DashboardSection>

        <DashboardSection title={messages.overviewFunnelTitle} description={messages.overviewFunnelHint}>
          <FunnelStrip
            steps={[
              { key: 'registered', label: messages.overviewFunnelRegistered, count: funnel.registered },
              { key: 'paid', label: messages.overviewFunnelPaid, count: funnel.paid },
              { key: 'credentialed', label: messages.overviewFunnelCredentialed, count: funnel.credentialed },
              { key: 'checked_in', label: messages.overviewFunnelCheckedIn, count: funnel.checked_in },
            ]}
          />
        </DashboardSection>

        <DashboardSection title={messages.overviewComparisonTitle} description={messages.overviewComparisonHint}>
          {eventsComparison.length === 0 ? (
            <EmptyState title={messages.overviewComparisonEmpty} />
          ) : (
            <DataTable
              rows={eventsComparison as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'name',
                  header: messages.overviewEvents,
                  render: (row) => {
                    const name = row.name as { en: string; ar: string }
                    const label = locale === 'ar' ? (name.ar || name.en) : (name.en || name.ar)
                    return (
                      <LocalizedLink className="font-medium text-[var(--brand)] hover:underline" href={`/tenant/events/${row.id}`}>
                        {label}
                      </LocalizedLink>
                    )
                  },
                },
                {
                  key: 'status',
                  header: messages.status,
                  render: (row) => <StatusBadge status={String(row.status)} />,
                },
                {
                  key: 'attendees',
                  header: messages.overviewAttendees,
                  render: (row) => String(row.attendees),
                },
                {
                  key: 'checked_in',
                  header: messages.overviewCheckedIn,
                  render: (row) => String(row.checked_in),
                },
                {
                  key: 'checkin_rate',
                  header: messages.overviewCheckinRate,
                  render: (row) => (row.checkin_rate === null || row.checkin_rate === undefined ? '—' : `${row.checkin_rate}%`),
                },
                {
                  key: 'revenue_minor',
                  header: messages.reportRevenue,
                  render: (row) => formatMoney(Number(row.revenue_minor ?? 0), String(row.currency ?? 'EGP'), locale),
                },
              ]}
            />
          )}
        </DashboardSection>

        <section className="ta-card ta-dashboard-audit mt-6">
          <div className="flex flex-wrap items-start justify-between gap-3 border-b border-[var(--border)] pb-4">
            <div>
              <h2 className="text-lg font-semibold text-[var(--ink)]">{messages.overviewRecentAudit}</h2>
              <p className="mt-1 text-sm text-[var(--muted)]">{messages.overviewAuditHint}</p>
            </div>
            <span className="ta-dashboard-audit-badge">{overview.recent_audit_events.length}</span>
          </div>
          {overview.recent_audit_events.length === 0 ? (
            <p className="mt-4 text-[var(--muted)]">{messages.emptyAudit}</p>
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
