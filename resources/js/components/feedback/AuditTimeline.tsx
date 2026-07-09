import Timeline from './Timeline'
import { useLocale } from '@/hooks/useLocale'
import { auditActionLabel, auditOutcomeLabel } from '@/lib/permissionCatalog'

type AuditEvent = {
  id: string
  actor: string
  action: string
  outcome: string
  occurred_at: string
}

type AuditTimelineProps = {
  events: AuditEvent[]
}

export default function AuditTimeline({ events }: AuditTimelineProps) {
  const { locale } = useLocale()

  return (
    <Timeline
      items={events.map((event) => ({
        id: event.id,
        title: `${event.actor} — ${auditActionLabel(event.action, locale)}`,
        detail: auditOutcomeLabel(event.outcome, locale),
        occurredAt: event.occurred_at,
      }))}
    />
  )
}
