import { useEffect, useState } from 'react'
import type { Kiosk } from '@/types/phase3'

interface HealthTableProps {
  eventId: string
  tenantId: string
  pollIntervalMs?: number
}

const STATUS_LABELS: Record<string, string> = {
  online: 'Online',
  offline: 'Offline',
  degraded: 'Degraded',
  retired: 'Retired',
  pending: 'Pending',
}

export function HealthTable({ eventId, tenantId, pollIntervalMs = 15000 }: HealthTableProps) {
  const [kiosks, setKiosks] = useState<Kiosk[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false

    const poll = async () => {
      try {
        const res = await fetch(`/api/v1/tenant/events/${eventId}/kiosks`, {
          credentials: 'include',
          headers: { Accept: 'application/json', 'X-Tenant-ID': tenantId },
        })
        if (!res.ok) throw new Error('Failed to fetch kiosk health')
        const data = await res.json()
        if (cancelled) return
        setKiosks(data.data ?? [])
        setError(null)
      } catch (e) {
        if (cancelled) return
        setError(e instanceof Error ? e.message : 'Unknown error')
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    void poll()
    const interval = setInterval(() => {
      void poll()
    }, pollIntervalMs)

    return () => {
      cancelled = true
      clearInterval(interval)
    }
  }, [eventId, tenantId, pollIntervalMs])

  if (loading) return <p>Loading kiosk health…</p>
  if (error) return <p role="alert">{error}</p>
  if (kiosks.length === 0) return <p>No kiosks registered for this event.</p>

  return (
    <table>
      <thead>
        <tr>
          <th>Device</th>
          <th>Status</th>
          <th>Printer</th>
          <th>Last Heartbeat</th>
        </tr>
      </thead>
      <tbody>
        {kiosks.map(kiosk => (
          <tr key={kiosk.id}>
            <td>{kiosk.device_name}</td>
            <td>{STATUS_LABELS[kiosk.status] ?? kiosk.status}</td>
            <td>{kiosk.printer_status ?? '—'}</td>
            <td>{kiosk.last_heartbeat_at ? new Date(kiosk.last_heartbeat_at).toLocaleTimeString() : '—'}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
