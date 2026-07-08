import { Link } from '@inertiajs/react'
import { FormEvent, useRef, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { ScanResultCard, type ScanResultView } from '@/components/checkin/ScanResultCard'
import TextareaInput from '@/components/forms/TextareaInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type Props = {
  event: EventRow
  tenantId: string
}

export default function CheckInScanner({ event, tenantId }: Props) {
  const { locale } = useLocale()
  const [payload, setPayload] = useState('')
  const [result, setResult] = useState<ScanResultView | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const idempotencyKey = useRef<string | null>(null)

  async function submitScan(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    if (submitting) {
      return
    }

    setSubmitting(true)
    setError(null)
    setResult(null)
    idempotencyKey.current ??= crypto.randomUUID()

    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/scans`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Tenant-ID': tenantId,
          'Idempotency-Key': idempotencyKey.current,
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
      idempotencyKey.current = null
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <DashboardLayout title={locale === 'ar' ? 'ماسح تسجيل الحضور' : 'Check-in scanner'}>
      <PageHeader
        title={locale === 'ar' ? 'ماسح تسجيل الحضور' : 'Check-in scanner'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'الماسح' : 'Scanner' },
        ]}
        actions={<Link className="button-secondary" href={`/tenant/events/${event.id}/check-in-dashboard`}>{locale === 'ar' ? 'لوحة تسجيل الحضور' : 'Check-in dashboard'}</Link>}
      />
      <PageContent>
        <form className="state-panel max-w-xl space-y-4" onSubmit={submitScan}>
          <TextareaInput
            label={locale === 'ar' ? 'حمولة رمز الاستجابة السريعة' : 'QR payload'}
            name="qr_payload"
            value={payload}
            required
            onChange={(changeEvent) => setPayload(changeEvent.target.value)}
          />
          <SubmitButtonWithLoader
            label={locale === 'ar' ? 'إرسال المسح' : 'Submit scan'}
            loading={submitting}
            disabled={payload.trim() === ''}
          />
        </form>
        {error ? <p role="alert">{error}</p> : null}
        <ScanResultCard result={result} />
      </PageContent>
    </DashboardLayout>
  )
}
