import { FormEvent, useState } from 'react'
import { AttendeeLookupPanel } from '../../../components/manual-desk/AttendeeLookupPanel'
import { CheckInResultPanel } from '../../../components/manual-desk/CheckInResultPanel'

interface DeskPageProps {
  eventId: string
  tenantId: string
}

interface LookupMatch {
  attendee_id: string | null
  credential_id: string | null
  display_name: string | null
  ticket_type_label: string | null
  checkin_status: string
}

interface ScanResult {
  scan_event_id: string
  result: string
  reason_code: string | null
  attendee_display_name: string | null
  ticket_type_label: string | null
}

export default function ManualDesk({ eventId, tenantId }: DeskPageProps) {
  const [query, setQuery] = useState('')
  const [lookupResult, setLookupResult] = useState<{ too_many: boolean; matches: LookupMatch[] } | null>(null)
  const [scanResult, setScanResult] = useState<ScanResult | null>(null)
  const [loading, setLoading] = useState(false)

  async function handleLookup(e: FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setLoading(true)
    setLookupResult(null)
    setScanResult(null)

    const response = await fetch(`/api/v1/tenant/events/${eventId}/desk/lookups`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Tenant-ID': tenantId,
      },
      body: JSON.stringify({ query }),
    })

    const body = await response.json()
    setLookupResult(body.data ?? null)
    setLoading(false)
  }

  async function handleCheckIn(match: LookupMatch) {
    if (!match.credential_id) return

    const response = await fetch(`/api/v1/tenant/events/${eventId}/scans`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Tenant-ID': tenantId,
        'Idempotency-Key': crypto.randomUUID(),
      },
      body: JSON.stringify({
        scanner_type: 'manual_desk',
        credential_id: match.credential_id,
      }),
    })

    const body = await response.json()
    setScanResult(body.data ?? null)
  }

  return (
    <div>
      <h1>Manual Desk</h1>
      <form onSubmit={handleLookup}>
        <input
          type="text"
          value={query}
          onChange={e => setQuery(e.target.value)}
          placeholder="Search by name, email or phone"
        />
        <button type="submit">Search</button>
      </form>
      <AttendeeLookupPanel result={lookupResult} loading={loading} onSelect={handleCheckIn} />
      <CheckInResultPanel result={scanResult} />
    </div>
  )
}
