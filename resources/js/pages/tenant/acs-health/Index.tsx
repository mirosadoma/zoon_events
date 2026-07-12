import { useEffect, useState } from 'react'
import { LaneHealthCard } from '../../../components/acs-health/LaneHealthCard'
import { useLocale } from '@/hooks/useLocale'

interface AcsHealthIndexProps {
  eventId: string
  tenantId: string
}

interface HealthSummary {
  integration_status: 'online' | 'degraded' | 'offline'
  active_emergency: boolean
  lanes: Array<{ lane_id: string; health_status: 'online' | 'degraded' | 'offline'; last_seen_at: string | null }>
}

export default function AcsHealthIndex({ eventId, tenantId }: AcsHealthIndexProps) {
  const { t } = useLocale()
  const [health, setHealth] = useState<HealthSummary | null>(null)

  useEffect(() => {
    const poll = () => {
      fetch(`/api/v1/tenant/events/${eventId}/acs/health`, {
        credentials: 'include',
        headers: { Accept: 'application/json', 'X-Tenant-ID': tenantId },
      })
        .then(r => r.json())
        .then(body => setHealth(body.data ?? null))
    }

    poll()
    const timer = setInterval(poll, 15000)
    return () => clearInterval(timer)
  }, [eventId, tenantId])

  return (
    <div>
      <h1>{t('acsHealthTitle')}</h1>
      {health && (
        <>
          <p>{t('acsHealthIntegration')}: {health.integration_status}</p>
          {health.active_emergency && <p role="alert">{t('acsHealthEmergencyActive')}</p>}
          <section>
            {health.lanes.map(lane => (
              <LaneHealthCard
                key={lane.lane_id}
                laneId={lane.lane_id}
                healthStatus={lane.health_status}
                lastSeenAt={lane.last_seen_at}
                activeEmergency={health.active_emergency}
              />
            ))}
          </section>
        </>
      )}
    </div>
  )
}
