import { ScanResultCard, type ScanResultView } from '@/components/checkin/ScanResultCard'
import { FormEvent, useState } from 'react'

interface ScannerPageProps {
  eventId: string
  tenantId: string
  locale?: 'en' | 'ar'
}

export default function CheckInScanner({ eventId, tenantId, locale = 'en' }: ScannerPageProps) {
  const [payload, setPayload] = useState('')
  const [result, setResult] = useState<ScanResultView | null>(null)
  const [error, setError] = useState<string | null>(null)
  const title = locale === 'ar' ? 'ماسح تسجيل الحضور' : 'Check-in scanner'
  const payloadLabel = locale === 'ar' ? 'حمولة رمز الاستجابة السريعة' : 'QR payload'
  const submitLabel = locale === 'ar' ? 'إرسال المسح' : 'Submit scan'

  async function submitScan(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setResult(null)

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
        qr_payload: payload,
        scanner_type: 'staff_phone',
      }),
    })

    const body = await response.json()
    if (!response.ok) {
      setError(body.code ?? 'scan_failed')
      return
    }

    const data = body.data as ScanResultView
    setResult({
      result: data.result,
      reason_code: data.reason_code,
      attendee_display_name: data.attendee_display_name ?? null,
      ticket_type_label: data.ticket_type_label ?? null,
    })
  }

  return (
    <main lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'}>
      <h1>{title}</h1>
      <form onSubmit={submitScan}>
        <label htmlFor="qr_payload">{payloadLabel}</label>
        <textarea
          id="qr_payload"
          name="qr_payload"
          value={payload}
          onChange={(event) => setPayload(event.target.value)}
          required
        />
        <button type="submit">{submitLabel}</button>
      </form>
      {error ? <p>{error}</p> : null}
      <ScanResultCard result={result} />
    </main>
  )
}
