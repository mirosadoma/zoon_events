import { FormEvent, useEffect, useRef, useState } from 'react'
import { KeyRound, Printer, QrCode, RotateCcw, Search, ShieldCheck } from 'lucide-react'
import BadgePrintPreviewModal, { type BadgeFieldOverrides } from '@/components/badges/BadgePrintPreviewModal'
import { ErrorState } from '@/components/feedback'
import { ScanResultCard, type ScanResultView } from '@/components/checkin/ScanResultCard'
import QrCameraScanner from '@/components/checkin/QrCameraScanner'
import TextInput from '@/components/forms/TextInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import { normalizeScanPayload } from '@/lib/normalizeScanPayload'
import { openBlankPrintWindow, writeBadgePrintDocument } from '@/lib/openBadgePrintWindow'

export type KioskStep = 'unlock' | 'confirm' | 'scan' | 'lookup' | 'result'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type Props = {
  deviceCode: string
  kiosk: { id: string; device_name: string; confirmation_required: boolean }
  event: EventRow
  step: KioskStep
}

type LookupMatch = {
  credential_id?: string | null
}

type PrintJobResult = {
  status?: string
  print_html?: string | null
}

type HeartbeatResponse = {
  status: string
  device_code?: string
  kiosk_id?: string
  confirmation_required?: boolean
  confirmed?: boolean
}

const SESSION_KEY = 'kiosk_session_secret'
const RESULT_KEY = 'kiosk_last_result'
const PRINT_KEY = 'kiosk_last_print_status'

function sessionStorageKey(deviceCode: string): string {
  return `${SESSION_KEY}:${deviceCode}`
}

function resultStorageKey(deviceCode: string): string {
  return `${RESULT_KEY}:${deviceCode}`
}

function printStorageKey(deviceCode: string): string {
  return `${PRINT_KEY}:${deviceCode}`
}

function readStoredResult(deviceCode: string): ScanResultView | null {
  try {
    const raw = sessionStorage.getItem(resultStorageKey(deviceCode))
    if (!raw) {
      return null
    }

    return JSON.parse(raw) as ScanResultView
  } catch {
    return null
  }
}

function writeStoredResult(deviceCode: string, result: ScanResultView | null): void {
  try {
    if (result === null) {
      sessionStorage.removeItem(resultStorageKey(deviceCode))
      sessionStorage.removeItem(printStorageKey(deviceCode))
      return
    }

    sessionStorage.setItem(resultStorageKey(deviceCode), JSON.stringify(result))
  } catch {
    // Ignore storage failures on private mode / restricted browsers.
  }
}

export default function KioskMode({ deviceCode, kiosk, event, step }: Props) {
  const { locale, t } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const [sessionSecret, setSessionSecret] = useState('')
  const [confirmationCode, setConfirmationCode] = useState('')
  const [hasSession, setHasSession] = useState(() => {
    if (typeof window === 'undefined') {
      return false
    }

    try {
      return Boolean(window.localStorage.getItem(sessionStorageKey(deviceCode)))
    } catch {
      return false
    }
  })
  const [needsConfirmation, setNeedsConfirmation] = useState(false)
  const [qrPayload, setQrPayload] = useState('')
  const [lookupQuery, setLookupQuery] = useState('')
  const [scanResult, setScanResult] = useState<ScanResultView | null>(() => (
    typeof window === 'undefined' ? null : readStoredResult(deviceCode)
  ))
  const [printStatus, setPrintStatus] = useState<string | null>(() => {
    if (typeof window === 'undefined') {
      return null
    }

    try {
      return sessionStorage.getItem(printStorageKey(deviceCode))
    } catch {
      return null
    }
  })
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [lookingUp, setLookingUp] = useState(false)
  const [unlocking, setUnlocking] = useState(false)
  const [confirming, setConfirming] = useState(false)
  const [sessionChecked, setSessionChecked] = useState(() => !hasSession)
  const [printPreviewOpen, setPrintPreviewOpen] = useState(false)
  const [printing, setPrinting] = useState(false)
  const idempotencyKey = useRef<string | null>(null)

  function goToStep(next: KioskStep, replace = false): void {
    localizedRouter.visit(`/kiosk/${deviceCode}/${next}`, {
      preserveState: true,
      preserveScroll: true,
      replace,
    })
  }

  function sessionAuthHeaders(extra: Record<string, string> = {}): HeadersInit {
    const secret = localStorage.getItem(sessionStorageKey(deviceCode))
    if (!secret) {
      throw new Error('missing_session')
    }

    return {
      Authorization: `KioskSession ${secret}`,
      ...extra,
    }
  }

  function persistResult(result: ScanResultView | null): void {
    setScanResult(result)
    writeStoredResult(deviceCode, result)
    if (result === null) {
      setPrintStatus(null)
    }
  }

  function persistPrintStatus(status: string | null): void {
    setPrintStatus(status)
    try {
      if (status === null) {
        sessionStorage.removeItem(printStorageKey(deviceCode))
      } else {
        sessionStorage.setItem(printStorageKey(deviceCode), status)
      }
    } catch {
      // ignore
    }
  }

  function handleSessionFailure(caught: unknown): void {
    if (!(caught instanceof ApiFetchError) && !(caught instanceof Error)) {
      setError('request_failed')
      return
    }

    if (!(caught instanceof ApiFetchError) && caught.message === 'missing_session') {
      localStorage.removeItem(sessionStorageKey(deviceCode))
      setHasSession(false)
      setNeedsConfirmation(false)
      setError('kiosk_session_invalid')
      goToStep('unlock', true)
      return
    }

    if (!(caught instanceof ApiFetchError)) {
      setError(caught.message || 'request_failed')
      return
    }

    const code = caught.code ?? ''

    if (code === 'kiosk_session_unconfirmed') {
      setNeedsConfirmation(true)
      setError('kiosk_session_unconfirmed')
      goToStep('confirm', true)
      return
    }

    if (code === 'kiosk_confirmation_invalid') {
      setNeedsConfirmation(true)
      setError('kiosk_confirmation_invalid')
      goToStep('confirm', true)
      return
    }

    if (code === 'kiosk_session_invalid' || code === 'missing_session') {
      localStorage.removeItem(sessionStorageKey(deviceCode))
      setHasSession(false)
      setNeedsConfirmation(false)
      setError('kiosk_session_invalid')
      goToStep('unlock', true)
      return
    }

    if (caught.status === 401 && (code === '' || code === 'unauthenticated')) {
      localStorage.removeItem(sessionStorageKey(deviceCode))
      setHasSession(false)
      setNeedsConfirmation(false)
      setError('kiosk_session_invalid')
      goToStep('unlock', true)
      return
    }

    setError(code || caught.message || 'request_failed')
  }

  async function validateHeartbeat(secret: string): Promise<HeartbeatResponse> {
    return apiFetch<HeartbeatResponse>('/api/v1/kiosk/v1/heartbeat', {
      method: 'POST',
      skipAuthRedirect: true,
      headers: {
        Authorization: `KioskSession ${secret}`,
      },
      body: {
        printer_status: 'unknown',
      },
    })
  }

  useEffect(() => {
    if (!hasSession) {
      setSessionChecked(true)
      return
    }

    const secret = localStorage.getItem(sessionStorageKey(deviceCode))
    if (!secret) {
      setHasSession(false)
      setSessionChecked(true)
      return
    }

    let cancelled = false
    setSessionChecked(false)

    void (async () => {
      try {
        const data = await validateHeartbeat(secret)
        if (cancelled) return
        setNeedsConfirmation(Boolean(data.confirmation_required && !data.confirmed))
      } catch (caught) {
        if (cancelled) return
        handleSessionFailure(caught)
      } finally {
        if (!cancelled) {
          setSessionChecked(true)
        }
      }
    })()

    return () => {
      cancelled = true
    }
  }, [deviceCode, hasSession])

  useEffect(() => {
    if (!sessionChecked) {
      return
    }

    if (!hasSession && step !== 'unlock') {
      goToStep('unlock', true)
      return
    }

    if (hasSession && needsConfirmation && step !== 'confirm') {
      goToStep('confirm', true)
      return
    }

    if (hasSession && !needsConfirmation && (step === 'unlock' || step === 'confirm')) {
      goToStep('scan', true)
      return
    }

    if (step === 'result' && scanResult === null) {
      goToStep('scan', true)
    }
  }, [step, hasSession, needsConfirmation, scanResult, sessionChecked])

  async function unlockSession(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    const trimmed = sessionSecret.trim()
    if (!trimmed || unlocking) return

    setUnlocking(true)
    setError(null)

    try {
      const data = await validateHeartbeat(trimmed)

      if (
        data.device_code
        && data.device_code.toUpperCase() !== deviceCode.toUpperCase()
      ) {
        setError('kiosk_session_wrong_device')
        return
      }

      if (
        data.kiosk_id
        && String(data.kiosk_id) !== String(kiosk.id)
      ) {
        setError('kiosk_session_wrong_device')
        return
      }

      localStorage.setItem(sessionStorageKey(deviceCode), trimmed)
      setHasSession(true)
      setSessionSecret('')
      const needsConfirm = Boolean(data.confirmation_required && !data.confirmed)
      setNeedsConfirmation(needsConfirm)
      setError(null)
      goToStep(needsConfirm ? 'confirm' : 'scan')
    } catch (caught) {
      if (caught instanceof ApiFetchError) {
        const code = caught.code ?? ''
        if (code === 'kiosk_session_invalid' || caught.status === 401) {
          setError('kiosk_session_invalid')
          return
        }
        if (code === 'kiosk_retired') {
          setError('kiosk_retired')
          return
        }
      }
      setError(caught instanceof ApiFetchError ? (caught.code ?? caught.message) : 'kiosk_session_invalid')
    } finally {
      setUnlocking(false)
    }
  }

  async function confirmSession(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    const trimmed = confirmationCode.trim()
    if (!trimmed || confirming) return

    setConfirming(true)
    setError(null)

    try {
      await apiFetch('/api/v1/kiosk/v1/session/confirm', {
        method: 'POST',
        skipAuthRedirect: true,
        headers: sessionAuthHeaders(),
        body: {
          confirmation_code: trimmed,
        },
      })
      setNeedsConfirmation(false)
      setConfirmationCode('')
      setError(null)
      goToStep('scan')
    } catch (caught) {
      handleSessionFailure(caught)
    } finally {
      setConfirming(false)
    }
  }

  function clearSession() {
    localStorage.removeItem(sessionStorageKey(deviceCode))
    setHasSession(false)
    setNeedsConfirmation(false)
    setConfirmationCode('')
    resetFlow()
    goToStep('unlock')
  }

  async function submitPayload(rawPayload: string) {
    const trimmed = normalizeScanPayload(rawPayload)
    if (submitting || trimmed === '') {
      return
    }

    setSubmitting(true)
    setError(null)
    persistPrintStatus(null)
    idempotencyKey.current ??= crypto.randomUUID()

    try {
      const data = await apiFetch<ScanResultView>('/api/v1/kiosk/v1/scans', {
        method: 'POST',
        skipAuthRedirect: true,
        headers: sessionAuthHeaders({ 'Idempotency-Key': idempotencyKey.current }),
        body: { qr_payload: trimmed },
      })

      setQrPayload(trimmed)
      persistResult(data)
      idempotencyKey.current = null
      goToStep('result')
    } catch (caught) {
      handleSessionFailure(caught)
    } finally {
      setSubmitting(false)
    }
  }

  async function submitScan(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    await submitPayload(qrPayload)
  }

  function handleCameraScan(value: string) {
    void submitPayload(value)
  }

  async function submitLookup(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    setError(null)
    setLookingUp(true)

    try {
      const query = lookupQuery
        .trim()
        .replace(/[\u200B-\u200F\uFEFF\u202A-\u202E\u2066-\u2069]/g, '')

      const data = await apiFetch<{ matches?: LookupMatch[]; data?: { matches?: LookupMatch[] } }>(
        '/api/v1/kiosk/v1/lookups',
        {
          method: 'POST',
          skipAuthRedirect: true,
          headers: sessionAuthHeaders(),
          body: { query },
        },
      )

      const matches = data.matches ?? data.data?.matches ?? []
      if (matches.length === 0) {
        setError('kiosk_lookup_no_matches')
        return
      }
      if (matches.length > 1) {
        setError('kiosk_lookup_too_many')
        return
      }

      const credentialId = matches[0]?.credential_id
      if (!credentialId) {
        setError('kiosk_lookup_no_credential')
        return
      }

      setQrPayload('')
      idempotencyKey.current ??= crypto.randomUUID()
      const scanData = await apiFetch<ScanResultView>('/api/v1/kiosk/v1/scans', {
        method: 'POST',
        skipAuthRedirect: true,
        headers: sessionAuthHeaders({ 'Idempotency-Key': idempotencyKey.current }),
        body: { credential_id: String(credentialId) },
      })
      persistResult(scanData)
      idempotencyKey.current = null
      goToStep('result')
    } catch (caught) {
      handleSessionFailure(caught)
    } finally {
      setLookingUp(false)
    }
  }

  function canPrintBadge(result: ScanResultView | null): boolean {
    if (!result) {
      return false
    }

    const checkedIn = result.result === 'accepted'
      || result.result === 'manual_override'
      || result.result === 'duplicate'

    return checkedIn && Boolean(result.attendee_id) && Boolean(result.credential_id)
  }

  function openPrintPreview() {
    if (!canPrintBadge(scanResult)) {
      setError('badge_print_checkin_required')
      return
    }

    setError(null)
    setPrintPreviewOpen(true)
  }

  async function loadKioskBadgePreview(overrides: BadgeFieldOverrides) {
    if (!scanResult?.attendee_id || !scanResult.credential_id) {
      throw new Error('preview_unavailable')
    }

    return apiFetch<{
      print_html: string
      fields: Record<string, string | null>
      editable_fields: string[]
    }>('/api/v1/kiosk/v1/badge-print-jobs/preview', {
      method: 'POST',
      skipAuthRedirect: true,
      headers: sessionAuthHeaders(),
      body: {
        attendee_id: String(scanResult.attendee_id),
        credential_id: String(scanResult.credential_id),
        field_overrides: overrides,
      },
    })
  }

  async function confirmPrintBadge(overrides: BadgeFieldOverrides) {
    if (!scanResult || !canPrintBadge(scanResult)) {
      setError('badge_print_checkin_required')
      return
    }

    setPrinting(true)
    setError(null)
    const printWindow = openBlankPrintWindow()

    try {
      const data = await apiFetch<PrintJobResult>('/api/v1/kiosk/v1/badge-print-jobs', {
        method: 'POST',
        skipAuthRedirect: true,
        idempotency: true,
        headers: sessionAuthHeaders(),
        body: {
          attendee_id: String(scanResult.attendee_id),
          credential_id: String(scanResult.credential_id),
          field_overrides: overrides,
        },
      })

      const opened = writeBadgePrintDocument(printWindow, data.print_html)
      if (!opened) {
        setError('kiosk_print_failed')
        persistPrintStatus(data.status ?? 'failed')
        return
      }

      persistPrintStatus(data.status ?? 'printed')
      setPrintPreviewOpen(false)
    } catch (caught) {
      printWindow?.close()
      if (caught instanceof ApiFetchError) {
        const code = caught.code ?? ''
        if (code === 'badge_print_checkin_required') {
          setError('badge_print_checkin_required')
          return
        }
        if (
          code === 'badge_template_not_active'
          || code === 'printer_unavailable'
          || code === 'printer_error'
          || code === 'payload_rejected'
          || code === 'validation_failed'
        ) {
          setError(code === 'validation_failed' ? 'kiosk_print_failed' : code)
          return
        }
      }
      handleSessionFailure(caught)
    } finally {
      setPrinting(false)
    }
  }

  function resetFlow() {
    setQrPayload('')
    setLookupQuery('')
    persistResult(null)
    setError(null)
    setPrintPreviewOpen(false)
  }

  function startOver() {
    resetFlow()
    goToStep('scan')
  }

  function errorTitle(code: string): string {
    switch (code) {
      case 'kiosk_session_invalid':
        return t('kioskModeSecretWrong')
      case 'kiosk_session_wrong_device':
        return t('kioskModeSecretWrongDevice')
      case 'kiosk_retired':
        return t('kioskModeRetired')
      case 'kiosk_session_unconfirmed':
        return t('kioskModeNeedsConfirm')
      case 'kiosk_confirmation_invalid':
        return t('kioskModeConfirmInvalid')
      case 'kiosk_lookup_no_matches':
        return t('kioskModeNoMatches')
      case 'kiosk_lookup_too_many':
        return t('kioskModeTooManyMatches')
      case 'kiosk_lookup_no_credential':
        return t('kioskModeLookupNoCredential')
      case 'badge_print_checkin_required':
        return t('kioskModePrintCheckinRequired')
      case 'badge_template_not_active':
      case 'printer_unavailable':
      case 'printer_error':
      case 'payload_rejected':
      case 'kiosk_print_failed':
        return t('kioskModePrintFailed')
      default:
        return code
    }
  }

  function errorDetail(code: string): string | undefined {
    switch (code) {
      case 'kiosk_session_invalid':
        return t('kioskModeSessionInvalid')
      case 'kiosk_session_wrong_device':
        return t('kioskModeSecretWrongDeviceDetail')
      case 'kiosk_session_unconfirmed':
        return t('kioskModeNeedsConfirmDescription')
      case 'csrf_token_mismatch':
        return t('kioskModeCsrfError')
      case 'badge_print_checkin_required':
        return t('kioskModePrintCheckinRequired')
      default:
        return undefined
    }
  }

  return (
    <div className="kiosk-shell">
      <div className="kiosk-shell__glow" aria-hidden />

      <header className="kiosk-hero">
        <div className="kiosk-hero__meta">
          <span className="kiosk-hero__code">{deviceCode}</span>
          <StatusBadge status={hasSession ? 'online' : 'offline'} label={hasSession ? t('kioskModePaired') : t('kioskModeUnpaired')} />
        </div>
        <h1 className="kiosk-hero__title">{event.name[locale] || event.name.en}</h1>
        <p className="kiosk-hero__device">{kiosk.device_name}</p>
      </header>

      <main className="kiosk-main">
        {error && (
          <div className="mb-4">
            <ErrorState
              title={errorTitle(error)}
              detail={errorDetail(error)}
            />
          </div>
        )}

        {step === 'unlock' && (
          <section className="kiosk-panel mx-auto max-w-lg">
            <div className="kiosk-panel__intro">
              <div className="kiosk-panel__icon">
                <KeyRound className="h-6 w-6" aria-hidden />
              </div>
              <div>
                <h2 className="kiosk-panel__title">{t('kioskModeUnlock')}</h2>
                <p className="kiosk-panel__copy">
                  {t('kioskModeUnlockDescription')}
                </p>
              </div>
            </div>
            <form className="space-y-4" onSubmit={(formEvent) => void unlockSession(formEvent)}>
              <TextInput
                label={t('kioskModeSessionSecret')}
                name="session_secret"
                value={sessionSecret}
                onChange={(changeEvent) => setSessionSecret(changeEvent.target.value)}
                required
                autoComplete="off"
              />
              <SubmitButtonWithLoader loading={unlocking} label={t('kioskModeUnlockButton')} />
            </form>
          </section>
        )}

        {step === 'confirm' && (
          <section className="kiosk-panel mx-auto max-w-lg">
            <div className="kiosk-panel__intro">
              <div className="kiosk-panel__icon">
                <ShieldCheck className="h-6 w-6" aria-hidden />
              </div>
              <div>
                <h2 className="kiosk-panel__title">{t('kioskModeNeedsConfirm')}</h2>
                <p className="kiosk-panel__copy">
                  {t('kioskModeNeedsConfirmDescription')}
                </p>
              </div>
            </div>
            <form className="space-y-4" onSubmit={(formEvent) => void confirmSession(formEvent)}>
              <TextInput
                label={t('kioskModeConfirmationCode')}
                name="confirmation_code"
                value={confirmationCode}
                onChange={(changeEvent) => setConfirmationCode(changeEvent.target.value)}
                required
                autoComplete="off"
                autoFocus
              />
              <SubmitButtonWithLoader loading={confirming} label={t('kioskModeConfirmButton')} />
            </form>
          </section>
        )}

        {(step === 'scan' || step === 'lookup') && (
          <div className="mx-auto mb-4 flex max-w-3xl gap-2">
            <button
              type="button"
              className={step === 'scan' ? 'button-primary flex-1' : 'button-secondary flex-1'}
              onClick={() => goToStep('scan')}
            >
              {t('kioskModeScanQr')}
            </button>
            <button
              type="button"
              className={step === 'lookup' ? 'button-primary flex-1' : 'button-secondary flex-1'}
              onClick={() => goToStep('lookup')}
            >
              {t('kioskModeLookupFallback')}
            </button>
          </div>
        )}

        {step === 'scan' && (
          <section className="kiosk-panel kiosk-panel--scan mx-auto max-w-3xl space-y-4">
            <div className="kiosk-panel__intro">
              <div className="kiosk-panel__icon">
                <QrCode className="h-6 w-6" aria-hidden />
              </div>
              <div>
                <h2 className="kiosk-panel__title">{t('kioskModeScanQr')}</h2>
                <p className="kiosk-panel__copy">
                  {t('kioskModeScanQrDescription')}
                </p>
              </div>
            </div>

            <QrCameraScanner
              active={step === 'scan' && hasSession && !needsConfirmation && !submitting}
              onScan={handleCameraScan}
              unavailableLabel={t('scanCameraUnavailable')}
              startingLabel={t('scanCameraStarting')}
              restartLabel={t('scanCameraRestart')}
            />

            <form className="space-y-4" onSubmit={(formEvent) => void submitScan(formEvent)}>
              <TextInput
                label={t('kioskModeManualEntry')}
                name="qr_payload"
                value={qrPayload}
                onChange={(changeEvent) => setQrPayload(changeEvent.target.value)}
                placeholder={t('kioskScanPlaceholder')}
                required
                autoComplete="off"
              />
              <SubmitButtonWithLoader loading={submitting} label={t('kioskCheckIn')} />
            </form>
          </section>
        )}

        {step === 'lookup' && (
          <form className="kiosk-panel mx-auto max-w-lg" onSubmit={(formEvent) => void submitLookup(formEvent)}>
            <div className="kiosk-panel__intro">
              <div className="kiosk-panel__icon kiosk-panel__icon--muted">
                <Search className="h-6 w-6" aria-hidden />
              </div>
              <div>
                <h2 className="kiosk-panel__title">{t('kioskModeLookupFallback')}</h2>
                <p className="kiosk-panel__copy">
                  {t('kioskModeLookupFallbackDescription')}
                </p>
              </div>
            </div>
            <TextInput
              label={t('kioskModeLookupFallback')}
              name="lookup_query"
              value={lookupQuery}
              onChange={(changeEvent) => setLookupQuery(changeEvent.target.value)}
              placeholder={t('kioskLookupPlaceholder')}
            />
            <div className="mt-4">
              <SubmitButtonWithLoader loading={lookingUp} label={t('kioskSearch')} />
            </div>
          </form>
        )}

        {step === 'result' && scanResult && (
          <section className="kiosk-panel kiosk-panel--result mx-auto max-w-lg space-y-4">
            <div className="flex items-center gap-2 text-sm font-medium text-[var(--muted)]">
              <ShieldCheck className="h-4 w-4 text-[var(--brand)]" aria-hidden />
              {t('kioskModeCheckInResult')}
            </div>
            <ScanResultCard result={scanResult} />
            {canPrintBadge(scanResult) && !printStatus && (
              <button type="button" className="button-primary inline-flex w-full items-center justify-center gap-2" onClick={openPrintPreview}>
                <Printer className="h-4 w-4" aria-hidden />
                {t('manualDeskPagePrintBadge')}
              </button>
            )}
            {!canPrintBadge(scanResult) && !printStatus && (
              <p className="text-center text-sm text-[var(--muted)]">
                {t('kioskModePrintCheckinRequired')}
              </p>
            )}
            {printStatus && (
              <div className="flex justify-center">
                <StatusBadge status={printStatus} label={`${t('manualDeskPageBadge')} ${printStatus}`} size="md" />
              </div>
            )}
            <button type="button" className="button-secondary inline-flex w-full items-center justify-center gap-2" onClick={startOver}>
              <RotateCcw className="h-4 w-4" aria-hidden />
              {t('kioskModeStartOver')}
            </button>
          </section>
        )}

        {scanResult?.attendee_id && scanResult.credential_id ? (
          <BadgePrintPreviewModal
            open={printPreviewOpen}
            attendeeId={String(scanResult.attendee_id)}
            credentialId={String(scanResult.credential_id)}
            attendeeName={scanResult.attendee_display_name}
            loading={printing}
            loadPreview={loadKioskBadgePreview}
            onCancel={() => setPrintPreviewOpen(false)}
            onConfirm={(result) => void confirmPrintBadge(result.overrides)}
          />
        ) : null}
      </main>

      {hasSession && (
        <footer className="kiosk-footer">
          <button type="button" className="kiosk-footer__action" onClick={clearSession}>
            {t('kioskModeClearSession')}
          </button>
        </footer>
      )}
    </div>
  )
}
