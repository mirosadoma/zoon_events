import type { Kiosk } from '../../types/phase3'

interface HeartbeatIndicatorProps {
  kiosk: Kiosk
}

export function HeartbeatIndicator({ kiosk }: HeartbeatIndicatorProps) {
  return (
    <span title={`Last heartbeat: ${kiosk.last_heartbeat_at ?? 'never'}`}>
      {kiosk.status}
    </span>
  )
}
