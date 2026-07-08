import { FormEvent, useRef, useState } from 'react'
import { ErrorState } from '@/components/feedback'
import TextInput from '@/components/forms/TextInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { ScanResultCard, type ScanResultView } from '@/components/checkin/ScanResultCard'
import StatusBadge from '@/components/status/StatusBadge'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type Props = {
  deviceCode: string
  kiosk: { id: string; device_name: string; confirmation_required: boolean }
  event: EventRow
}

const SESSION_KEY = 'kiosk_session_secret'

export default function KioskMode({ deviceCode, kiosk, event }: Props) {
  const [qrPayload, setQrPayload] = useState('')
  const [lookupQuery, setLookupQuery] = useState('')
  const [scanResult, setScanResult] = useState<ScanResultView | null>(null)
  const [printStatus, setPrintStatus] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const idempotencyKey = useRef<string | null>(null)

  function sessionHeaders(): Record<string, string> {
    const secret = localStorage.getItem(SESSION_KEY)
    if (!secret) {
      throw new Error('missing_session')
    }

    return {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: `KioskSession ${secret}`,
    }
  }

  async function submitScan(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    if (submitting) return

    setSubmitting(true)
    setError(null)
    setScanResult(null)
    setPrintStatus(null)
    idempotencyKey.current ??= crypto.randomUUID()

    try {
      const response = await fetch('/api/v1/kiosk/v1/scans', {
        method: 'POST',
        credentials: 'include',
        headers: { ...sessionHeaders(), 'Idempotency-Key': idempotencyKey.current },
        body: JSON.stringify({ qr_payload: qrPayload }),
      })
      const body = await response.json()
      if (!response.ok) {
        setError(body.code ?? 'scan_failed')
        return
      }

      setScanResult(body.data as ScanResultView)
      idempotencyKey.current = null
    } catch {
      setError('kiosk_session_invalid')
    } finally {
      setSubmitting(false)
    }
  }

  async function submitLookup(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    setError(null)

    try {
      const response = await fetch('/api/v1/kiosk/v1/lookups', {
        method: 'POST',
        credentials: 'include',
        headers: sessionHeaders(),
        body: JSON.stringify({ query: lookupQuery }),
      })
      const body = await response.json()
      if (!response.ok) {
        setError(body.code ?? 'lookup_failed')
        return
      }

      const matches = body.data?.matches ?? []
      if (matches.length === 1 && matches[0].credential_id) {
        setQrPayload('')
        setSubmitting(false)
        idempotencyKey.current ??= crypto.randomUUID()
        const scanResponse = await fetch('/api/v1/kiosk/v1/scans', {
          method: 'POST',
          credentials: 'include',
          headers: { ...sessionHeaders(), 'Idempotency-Key': idempotencyKey.current },
          body: JSON.stringify({ credential_id: matches[0].credential_id }),
        })
        const scanBody = await scanResponse.json()
        if (scanResponse.ok) {
          setScanResult(scanBody.data as ScanResultView)
          idempotencyKey.current = null
        } else {
          setError(scanBody.code ?? 'scan_failed')
        }
      }
    } catch {
      setError('kiosk_session_invalid')
    }
  }

  async function requestPrint() {
    if (!scanResult || scanResult.result !== 'accepted') return

    try {
      const response = await fetch('/api/v1/kiosk/v1/badge-print-jobs', {
        method: 'POST',
        credentials: 'include',
        headers: { ...sessionHeaders(), 'Idempotency-Key': crypto.randomUUID() },
        body: JSON.stringify({}),
      })
      const body = await response.json()
      if (!response.ok) {
        setError(body.code ?? 'print_failed')
        return
      }

      setPrintStatus(body.data?.status ?? 'printed')
    } catch {
      setError('kiosk_session_invalid')
    }
  }

  function resetFlow() {
    setQrPayload('')
    setLookupQuery('')
    setScanResult(null)
    setPrintStatus(null)
    setError(null)
  }

  return (
    <div className="min-h-screen bg-slate-950 p-6 text-white">
      <header className="mb-8 text-center">
        <p className="text-sm uppercase tracking-wide text-slate-400">{deviceCode}</p>
        <h1 className="text-3xl font-bold">{event.name.en}</h1>
        <p className="text-slate-300">{kiosk.device_name}</p>
      </header>

      {error && <ErrorState title={error} />}

      {!scanResult && (
        <>
          <form className="mx-auto max-w-lg space-y-4" onSubmit={submitScan}>
            <TextInput
              label="Scan QR code"
              name="qr_payload"
              value={qrPayload}
              onChange={(changeEvent) => setQrPayload(changeEvent.target.value)}
              required
            />
            <SubmitButtonWithLoader loading={submitting} label="Check in" />
          </form>

          <form className="mx-auto mt-8 max-w-lg space-y-4 border-t border-slate-700 pt-8" onSubmit={submitLookup}>
            <TextInput
              label="Lookup fallback"
              name="lookup_query"
              value={lookupQuery}
              onChange={(changeEvent) => setLookupQuery(changeEvent.target.value)}
              placeholder="Name, email, or phone"
            />
            <SubmitButtonWithLoader loading={false} label="Search" />
          </form>
        </>
      )}

      {scanResult && (
        <div className="mx-auto max-w-lg space-y-4">
          <ScanResultCard result={scanResult} />
          {scanResult.result === 'accepted' && !printStatus && (
            <button type="button" className="button-primary w-full" onClick={requestPrint}>
              Print badge
            </button>
          )}
          {printStatus && (
            <p className="flex justify-center">
              <StatusBadge status={printStatus} label={`Badge ${printStatus}`} />
            </p>
          )}
          <button type="button" className="button-secondary w-full" onClick={resetFlow}>
            Start over
          </button>
        </div>
      )}
    </div>
  )
}
