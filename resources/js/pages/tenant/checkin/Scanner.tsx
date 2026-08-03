import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useMemo, useRef, useState } from 'react'
import { router } from '@inertiajs/react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { ScanResultCard, type ScanResultView } from '@/components/checkin/ScanResultCard'
import QrCameraScanner from '@/components/checkin/QrCameraScanner'
import TextInput from '@/components/forms/TextInput'
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

type ZoneRow = {
  id: string
  name: { en: string; ar: string }
  scanner_code: string | null
  capacity: number | null
}

type Props = {
  event: EventRow
  tenantId: string
  zones: ZoneRow[]
  selectedZone: ZoneRow | null
  codeError: string | null
}

export default function CheckInScanner({
  event,
  tenantId,
  zones,
  selectedZone: initialZone,
  codeError,
}: Props) {
  const { locale, t, localizedPath } = useLocale()
  const [payload, setPayload] = useState('')
  const [result, setResult] = useState<ScanResultView | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [codeInput, setCodeInput] = useState('')
  const [selectedZone, setSelectedZone] = useState<ZoneRow | null>(initialZone)
  const idempotencyKey = useRef<string | null>(null)

  const zoneLabel = useMemo(() => {
    if (!selectedZone) {
      return null
    }
    const name = selectedZone.name[locale] || selectedZone.name.en
    return selectedZone.scanner_code
      ? `${name} · ${selectedZone.scanner_code}`
      : name
  }, [locale, selectedZone])

  function bindZoneByCode(rawCode: string) {
    const code = rawCode.replace(/\D/g, '').slice(0, 8)
    setCodeInput(code)
    if (code.length !== 8) {
      return
    }
    router.get(
      localizedPath(`/tenant/events/${event.id}/scanner`),
      { code },
      { preserveState: false, preserveScroll: false },
    )
  }

  function clearZone() {
    setSelectedZone(null)
    router.get(localizedPath(`/tenant/events/${event.id}/scanner`), {}, {
      preserveState: false,
      preserveScroll: false,
    })
  }

  async function submitPayload(rawPayload: string) {
    const trimmed = normalizeScanPayload(rawPayload)

    if (submitting || trimmed === '' || !selectedZone) {
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
          zone_id: Number(selectedZone.id),
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
      invalid_scanner_code: t('scannerPageInvalidCode'),
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
        {!selectedZone ? (
          <div className="ta-card mx-auto max-w-md space-y-4">
            <div>
              <h2 className="text-lg font-semibold text-[var(--ink)]">{t('scannerPageEnterCodeTitle')}</h2>
              <p className="mt-1 text-sm text-[var(--muted)]">{t('scannerPageEnterCodeHelp')}</p>
            </div>
            <TextInput
              label={t('scannerPageZoneCode')}
              name="scanner_code"
              inputMode="numeric"
              maxLength={8}
              value={codeInput}
              onChange={(e) => bindZoneByCode(e.target.value)}
              required
            />
            {codeError ? (
              <p role="alert" className="text-sm text-red-700 dark:text-red-300">
                {scanErrorMessage(codeError)}
              </p>
            ) : null}
            {zones.length > 0 ? (
              <div className="space-y-2 border-t border-[var(--line)] pt-4">
                <p className="text-sm font-medium text-[var(--ink)]">{t('scannerPageOrPickZone')}</p>
                <ul className="space-y-2">
                  {zones.map((zone) => (
                    <li key={zone.id}>
                      <LocalizedLink
                        className="button-secondary inline-flex w-full justify-between"
                        href={`/tenant/events/${event.id}/scanner?code=${zone.scanner_code ?? ''}`}
                      >
                        <span>{zone.name[locale] || zone.name.en}</span>
                        <span className="font-mono text-xs">{zone.scanner_code ?? '—'}</span>
                      </LocalizedLink>
                    </li>
                  ))}
                </ul>
              </div>
            ) : (
              <p className="text-sm text-[var(--muted)]">{t('scannerPageNoZones')}</p>
            )}
          </div>
        ) : (
          <div className="state-panel scanner-workspace space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] px-4 py-3">
              <div>
                <p className="text-xs uppercase tracking-wide text-[var(--muted)]">{t('scannerPageBoundZone')}</p>
                <p className="text-base font-semibold text-[var(--ink)]">{zoneLabel}</p>
              </div>
              <button type="button" className="button-secondary" onClick={clearZone}>
                {t('scannerPageChangeZone')}
              </button>
            </div>

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
        )}

        {error ? (
          <p role="alert" className="mt-4 text-xs font-medium text-red-700 dark:text-red-300">
            {scanErrorMessage(error)}
          </p>
        ) : null}
      </PageContent>
    </DashboardLayout>
  )
}
