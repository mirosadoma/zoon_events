import Timeline from './Timeline'

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
  return (
    <Timeline
      items={events.map((event) => ({
        id: event.id,
        title: `${event.actor} — ${event.action}`,
        detail: event.outcome,
        occurredAt: event.occurred_at,
      }))}
    />
  )
}
