import { useEffect, useState } from 'react'
import type { AccessEvent } from '../../../types/phase4'
import { GateEventRow } from '../../../components/gate-events/GateEventRow'
import { useLocale } from '@/hooks/useLocale'

interface GateEventsIndexProps {
  eventId: string
  tenantId: string
}

export default function GateEventsIndex({ eventId, tenantId }: GateEventsIndexProps) {
  const { t } = useLocale()
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
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-[var(--ink)]">{t('gateEventsTitle')}</h1>
      <div className="ta-card overflow-hidden p-0">
        <div className="ta-table-wrap">
          <table className="ta-table">
            <thead>
              <tr>
                <th>{t('gateEventsOccurred')}</th>
                <th>{t('gateEventsType')}</th>
                <th>{t('gateEventsDirection')}</th>
                <th>{t('gateEventsDecision')}</th>
                <th>{t('gateEventsReason')}</th>
              </tr>
            </thead>
            <tbody>
              {events.map(event => (
                <GateEventRow key={event.id} event={event} />
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
