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
        breadcrumbs={[{ label: messages.overview }]}
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
