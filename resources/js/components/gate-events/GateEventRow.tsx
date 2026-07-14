import StatusBadge from '@/components/status/StatusBadge'
import type { AccessEvent } from '@/types/phase4'
import { ACS_REASON_CODES } from '@/types/phase4'

interface GateEventRowProps {
  event: AccessEvent
}

export function GateEventRow({ event }: GateEventRowProps) {
  const reasonLabel = ACS_REASON_CODES.includes(event.reason_code as (typeof ACS_REASON_CODES)[number])
    ? event.reason_code
    : event.reason_code

  return (
    <tr>
      <td className="whitespace-nowrap text-[var(--muted)]">{event.occurred_at}</td>
      <td>{event.event_type}</td>
      <td>{event.direction}</td>
      <td>
        {event.decision ? (
          <StatusBadge status={event.decision} />
        ) : (
          <span className="text-[var(--muted)]">—</span>
        )}
      </td>
      <td>
        <span className="font-mono text-xs">{reasonLabel}</span>
      </td>
    </tr>
  )
}
