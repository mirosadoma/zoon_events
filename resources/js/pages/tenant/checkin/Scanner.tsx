import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useRef, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { ScanResultCard, type ScanResultView } from '@/components/checkin/ScanResultCard'
import QrCameraScanner from '@/components/checkin/QrCameraScanner'
import TextareaInput from '@/components/forms/TextareaInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import { normalizeScanPayload } from '@/lib/normalizeScanPayload'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type Props = {
  event: EventRow
  tenantId: string
}

export default function CheckInScanner({ event, tenantId }: Props) {
  const { locale, t } = useLocale()
  const [payload, setPayload] = useState('')
  const [result, setResult] = useState<ScanResultView | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const idempotencyKey = useRef<string | null>(null)

  async function submitPayload(rawPayload: string) {
    const trimmed = normalizeScanPayload(rawPayload)

    if (submitting || trimmed === '') {
      return
    }

    setSubmitting(true)
    setError(null)
    setResult(null)
    idempotencyKey.current ??= crypto.randomUUID()

    try {
      const data = await apiFetch<ScanResultView>(`/api/v1/tenant/events/${event.id}/scans`, {
        method: 'POST',
        tenantId,
        headers: {
          'Idempotency-Key': idempotencyKey.current,
        },
        body: {
          qr_payload: trimmed,
          scanner_type: 'staff_phone',
        },
      })

      setResult({
        result: data.result,
        reason_code: data.reason_code,
        attendee_display_name: data.attendee_display_name ?? null,
        ticket_type_label: data.ticket_type_label ?? null,
      })
      idempotencyKey.current = null
      setPayload(trimmed)
    } catch (caught) {
      if (caught instanceof ApiFetchError) {
        setError(caught.code ?? caught.message)
      } else {
        setError('scan_failed')
      }
    } finally {
      setSubmitting(false)
    }
  }

  async function submitScan(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    await submitPayload(payload)
  }

  function handleCameraScan(value: string) {
    const normalized = normalizeScanPayload(value)
    setPayload(normalized)
    void submitPayload(normalized)
  }

  function scanErrorMessage(code: string): string {
    const messages: Record<string, string> = {
      csrf_token_mismatch: t('scanErrorSessionExpired'),
      service_unavailable: t('scanErrorSessionExpired'),
      credential_invalid: t('scanErrorInvalidPayload'),
      credential_expired: t('scanErrorExpired'),
      credential_revoked: t('scanErrorRevoked'),
      scan_failed: t('scanErrorFailed'),
    }

    return messages[code] ?? code
  }

  return (
    <DashboardLayout title={t('scannerPageTitle')}>
      <PageHeader
        title={t('scannerPageTitle')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('scannerPageScanner') },
        ]}
        actions={<LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/check-in-dashboard`}>{t('checkInDashboard')}</LocalizedLink>}
      />
      <PageContent>
        <div className="state-panel scanner-workspace space-y-4">
          <p className="text-sm text-[var(--muted)]">{t('scanPayloadHelp')}</p>

          <div className="scanner-workspace-grid">
            <QrCameraScanner
              active
              onScan={handleCameraScan}
              unavailableLabel={t('scanCameraUnavailable')}
              startingLabel={t('scanCameraStarting')}
              restartLabel={t('scanCameraRestart')}
            />

            <form className="scanner-entry-form" onSubmit={submitScan}>
              <TextareaInput
                label={t('qrPayload')}
                name="qr_payload"
                value={payload}
                required
                onChange={(changeEvent) => setPayload(changeEvent.target.value)}
              />

              <ScanResultCard result={result} />

              <div className="scanner-entry-form__actions">
                <SubmitButtonWithLoader
                  label={t('submitScan')}
                  loading={submitting}
                  disabled={payload.trim() === ''}
                />
              </div>
            </form>
          </div>
        </div>

        {error ? (
          <p role="alert" className="mt-4 text-xs font-medium text-red-700 dark:text-red-300">
            {scanErrorMessage(error)}
          </p>
        ) : null}
      </PageContent>
    </DashboardLayout>
  )
}
