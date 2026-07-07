import { useEffect, useState } from 'react'
import type { AcsLane, AcsRule, AcsZone } from '../../../types/phase4'
import { ZoneLaneEditor } from '../../../components/acs/ZoneLaneEditor'
import { RuleEditor } from '../../../components/acs/RuleEditor'

interface AcsIndexProps {
  eventId: string
  tenantId: string
}

export default function AcsIndex({ eventId, tenantId }: AcsIndexProps) {
  const [zones, setZones] = useState<AcsZone[]>([])
  const [lanes, setLanes] = useState<AcsLane[]>([])
  const [rules, setRules] = useState<AcsRule[]>([])
  const [registeredSecret, setRegisteredSecret] = useState<string | null>(null)

  const headers = {
    Accept: 'application/json',
    'X-Tenant-ID': tenantId,
  }

  useEffect(() => {
    const base = `/api/v1/tenant/events/${eventId}/acs`
    const requestHeaders = {
      Accept: 'application/json',
      'X-Tenant-ID': tenantId,
    }

    Promise.all([
      fetch(`${base}/zones`, { credentials: 'include', headers: requestHeaders }).then(r => r.json()),
      fetch(`${base}/lanes`, { credentials: 'include', headers: requestHeaders }).then(r => r.json()),
      fetch(`${base}/rules`, { credentials: 'include', headers: requestHeaders }).then(r => r.json()),
    ]).then(([zonesBody, lanesBody, rulesBody]) => {
      setZones(zonesBody.data ?? [])
      setLanes(lanesBody.data ?? [])
      setRules(rulesBody.data ?? [])
    })
  }, [eventId, tenantId])

  async function registerCredential() {
    const response = await fetch(`/api/v1/tenant/events/${eventId}/acs/integration-credentials`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        ...headers,
        'Content-Type': 'application/json',
        'Idempotency-Key': crypto.randomUUID(),
      },
      body: JSON.stringify({
        name: 'ACS Integration',
        capabilities: ['authorize', 'event.ingest', 'emergency.ingest'],
      }),
    })
    const body = await response.json()
    if (body.data?.secret) {
      setRegisteredSecret(body.data.secret)
    }
  }

  return (
    <div>
      <h1>ACS Configuration</h1>
      <ZoneLaneEditor zones={zones} lanes={lanes} />
      <RuleEditor rules={rules} />
      <section>
        <h2>Integration Credential</h2>
        <button type="button" onClick={registerCredential}>Register credential</button>
        {registeredSecret && (
          <p>
            Secret (shown once): <code>{registeredSecret}</code>
          </p>
        )}
      </section>
    </div>
  )
}
