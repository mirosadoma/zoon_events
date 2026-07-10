import { useEffect, useState } from 'react'
import type { AccessEvent } from '../../../types/phase4'
import { GateEventRow } from '../../../components/gate-events/GateEventRow'

interface GateEventsIndexProps {
  eventId: string
  tenantId: string
}

export default function GateEventsIndex({ eventId, tenantId }: GateEventsIndexProps) {
  const [events, setEvents] = useState<AccessEvent[]>([])

  useEffect(() => {
    const poll = () => {
      fetch(`/api/v1/tenant/events/${eventId}/acs/gate-events?limit=50`, {
        credentials: 'include',
        headers: { Accept: 'application/json', 'X-Tenant-ID': tenantId },
      })
        .then(r => r.json())
        .then(body => setEvents(body.data ?? []))
    }

    poll()
    const timer = setInterval(poll, 15000)
    return () => clearInterval(timer)
  }, [eventId, tenantId])

  return (
    <div>
      <h1>Gate Events</h1>
      <table>
        <thead>
          <tr>
            <th>Occurred</th>
            <th>Type</th>
            <th>Direction</th>
            <th>Decision</th>
            <th>Reason</th>
          </tr>
        </thead>
        <tbody>
          {events.map(event => (
            <GateEventRow key={event.id} event={event} />
          ))}
        </tbody>
      </table>
    </div>
  )
}
