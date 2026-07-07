interface LaneHealthCardProps {
  laneId: string
  healthStatus: 'online' | 'degraded' | 'offline'
  lastSeenAt: string | null
  activeEmergency?: boolean
}

export function LaneHealthCard({ laneId, healthStatus, lastSeenAt, activeEmergency = false }: LaneHealthCardProps) {
  return (
    <article>
      {activeEmergency && <p role="status">Emergency active</p>}
      <h3>Lane {laneId}</h3>
      <p>Status: {healthStatus}</p>
      <p>Last seen: {lastSeenAt ?? 'never'}</p>
    </article>
  )
}
