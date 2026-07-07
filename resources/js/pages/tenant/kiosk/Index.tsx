import { useState, useEffect } from 'react'
import type { Kiosk } from '../../../types/phase3'
import { PairingDialog } from '../../../components/kiosk/PairingDialog'
import { HeartbeatIndicator } from '../../../components/kiosk/HeartbeatIndicator'
import { HealthTable } from '../../../components/kiosk/HealthTable'

interface KioskIndexProps {
  eventId: string
  tenantId: string
}

export default function KioskIndex({ eventId, tenantId }: KioskIndexProps) {
  const [kiosks, setKiosks] = useState<Kiosk[]>([])
  const [selectedKiosk, setSelectedKiosk] = useState<Kiosk | null>(null)

  useEffect(() => {
    fetch(`/api/v1/tenant/events/${eventId}/kiosks`, {
      credentials: 'include',
      headers: { Accept: 'application/json', 'X-Tenant-ID': tenantId },
    })
      .then(r => r.json())
      .then(body => setKiosks(body.data ?? []))
  }, [eventId, tenantId])

  async function handlePair(kioskId: string) {
    await fetch(`/api/v1/tenant/events/${eventId}/kiosks/${kioskId}/pair`, {
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json', 'X-Tenant-ID': tenantId, 'Idempotency-Key': crypto.randomUUID() },
    })
    setSelectedKiosk(null)
  }

  async function handleRetire(kioskId: string) {
    await fetch(`/api/v1/tenant/events/${eventId}/kiosks/${kioskId}/retire`, {
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json', 'X-Tenant-ID': tenantId, 'Idempotency-Key': crypto.randomUUID() },
    })
    setKiosks(prev => prev.map(k => k.id === kioskId ? { ...k, status: 'retired' } : k))
  }

  return (
    <div>
      <h1>Kiosk Management</h1>
      <ul>
        {kiosks.map(kiosk => (
          <li key={kiosk.id}>
            {kiosk.device_name} - <HeartbeatIndicator kiosk={kiosk} />
            <button type="button" onClick={() => setSelectedKiosk(kiosk)}>Pair</button>
            <button type="button" onClick={() => handleRetire(kiosk.id)}>Retire</button>
          </li>
        ))}
      </ul>
      {selectedKiosk && (
        <PairingDialog
          kiosk={selectedKiosk}
          onConfirm={handlePair}
          onCancel={() => setSelectedKiosk(null)}
        />
      )}

      <section>
        <h2>Live Health</h2>
        <HealthTable eventId={eventId} pollIntervalMs={15000} />
      </section>
    </div>
  )
}
