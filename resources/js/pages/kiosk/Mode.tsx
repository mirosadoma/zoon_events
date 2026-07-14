import { FormEvent, useRef, useState } from 'react'
import { KeyRound, Printer, QrCode, RotateCcw, Search, ShieldCheck } from 'lucide-react'
import { ErrorState } from '@/components/feedback'
import TextInput from '@/components/forms/TextInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { ScanResultCard, type ScanResultView } from '@/components/checkin/ScanResultCard'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type Props = {
  deviceCode: string
  kiosk: { id: string; device_name: string; confirmation_required: boolean }
  event: EventRow
}

type LookupMatch = {
  credential_id?: string | null
}

type PrintJobResult = {
  status?: string
}

const SESSION_KEY = 'kiosk_session_secret'

export default function KioskMode({ deviceCode, kiosk, event }: Props) {
  const { locale, t } = useLocale()
  const ar = locale === 'ar'
  const [sessionSecret, setSessionSecret] = useState('')
  const [hasSession, setHasSession] = useState(() => {
    if (typeof window === 'undefined') {
      return false
    }

    try {
      return Boolean(window.localStorage.getItem(SESSION_KEY))
    } catch {
      return false
    }
  })
  const [qrPayload, setQrPayload] = useState('')
  const [lookupQuery, setLookupQuery] = useState('')
  const [scanResult, setScanResult] = useState<ScanResultView | null>(null)
  const [printStatus, setPrintStatus] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [lookingUp, setLookingUp] = useState(false)
  const idempotencyKey = useRef<string | null>(null)

  function sessionAuthHeaders(extra: Record<string, string> = {}): HeadersInit {
    const secret = localStorage.getItem(SESSION_KEY)
    if (!secret) {
      throw new Error('missing_session')
    }

    return {
      Authorization: `KioskSession ${secret}`,
      ...extra,
    }
  }

  function handleSessionFailure(caught: unknown): void {
    const code = caught instanceof ApiFetchError
      ? (caught.code ?? caught.message)
      : 'kiosk_session_invalid'

    if (code === 'kiosk_session_invalid' || code === 'missing_session' || (caught instanceof ApiFetchError && caught.status === 401)) {
      setHasSession(false)
      setError('kiosk_session_invalid')
      return
    }

    setError(code || 'request_failed')
  }

  function unlockSession(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    const trimmed = sessionSecret.trim()
    if (!trimmed) return

    localStorage.setItem(SESSION_KEY, trimmed)
    setHasSession(true)
    setSessionSecret('')
    setError(null)
  }

  function clearSession() {
    localStorage.removeItem(SESSION_KEY)
    setHasSession(false)
    resetFlow()
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
      const data = await apiFetch<ScanResultView>('/api/v1/kiosk/v1/scans', {
        method: 'POST',
        skipAuthRedirect: true,
        headers: sessionAuthHeaders({ 'Idempotency-Key': idempotencyKey.current }),
        body: { qr_payload: qrPayload },
      })

      setScanResult(data)
      idempotencyKey.current = null
    } catch (caught) {
      handleSessionFailure(caught)
    } finally {
      setSubmitting(false)
    }
  }

  async function submitLookup(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    setError(null)
    setLookingUp(true)

    try {
      const data = await apiFetch<{ matches?: LookupMatch[] }>('/api/v1/kiosk/v1/lookups', {
        method: 'POST',
        skipAuthRedirect: true,
        headers: sessionAuthHeaders(),
        body: { query: lookupQuery },
      })

      const matches = data.matches ?? []
      if (matches.length === 0) {
        setError(ar ? 'لا توجد نتائج' : 'No matches found')
        return
      }
      if (matches.length > 1) {
        setError(ar ? 'نتائج كثيرة — ضيّق البحث' : 'Too many matches — refine your search')
        return
      }
      if (matches[0].credential_id) {
        setQrPayload('')
        idempotencyKey.current ??= crypto.randomUUID()
        const scanData = await apiFetch<ScanResultView>('/api/v1/kiosk/v1/scans', {
          method: 'POST',
          skipAuthRedirect: true,
          headers: sessionAuthHeaders({ 'Idempotency-Key': idempotencyKey.current }),
          body: { credential_id: matches[0].credential_id },
        })
        setScanResult(scanData)
        idempotencyKey.current = null
      }
    } catch (caught) {
      handleSessionFailure(caught)
    } finally {
      setLookingUp(false)
    }
  }

  async function requestPrint() {
    if (!scanResult || scanResult.result !== 'accepted') return

    try {
      const data = await apiFetch<PrintJobResult>('/api/v1/kiosk/v1/badge-print-jobs', {
        method: 'POST',
        skipAuthRedirect: true,
        idempotency: true,
        headers: sessionAuthHeaders(),
        body: {},
      })

      setPrintStatus(data.status ?? 'printed')
    } catch (caught) {
      handleSessionFailure(caught)
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
    <div className="kiosk-shell">
      <div className="kiosk-shell__glow" aria-hidden />

      <header className="kiosk-hero">
        <div className="kiosk-hero__meta">
          <span className="kiosk-hero__code">{deviceCode}</span>
          <StatusBadge status={hasSession ? 'online' : 'offline'} label={hasSession ? (ar ? 'مرتبط' : 'Paired') : (ar ? 'غير مرتبط' : 'Unpaired')} />
        </div>
        <h1 className="kiosk-hero__title">{event.name[locale] || event.name.en}</h1>
        <p className="kiosk-hero__device">{kiosk.device_name}</p>
      </header>

      <main className="kiosk-main">
        {error && (
          <div className="mb-4">
            <ErrorState
              title={error}
              detail={error === 'kiosk_session_invalid'
                ? (ar ? 'أدخل رمز جلسة الإقران مرة أخرى.' : 'Enter the pairing session secret again.')
                : error === 'csrf_token_mismatch'
                  ? (ar ? 'حدّث الصفحة ثم أعد المحاولة.' : 'Refresh the page and try again.')
                  : undefined}
            />
          </div>
        )}

        {!hasSession ? (
          <section className="kiosk-panel">
            <div className="kiosk-panel__intro">
              <div className="kiosk-panel__icon">
                <KeyRound className="h-6 w-6" aria-hidden />
              </div>
              <div>
                <h2 className="kiosk-panel__title">{ar ? 'تفعيل الجهاز' : 'Unlock this kiosk'}</h2>
                <p className="kiosk-panel__copy">
                  {ar
                    ? 'الصق رمز الجلسة الذي يظهر بعد الإقران من لوحة التحكم.'
                    : 'Paste the session secret shown after pairing from the admin console.'}
                </p>
              </div>
            </div>
            <form className="space-y-4" onSubmit={unlockSession}>
              <TextInput
                label={ar ? 'رمز الجلسة' : 'Session secret'}
                name="session_secret"
                value={sessionSecret}
                onChange={(changeEvent) => setSessionSecret(changeEvent.target.value)}
                required
                autoComplete="off"
              />
              <SubmitButtonWithLoader loading={false} label={ar ? 'تفعيل' : 'Unlock'} />
            </form>
          </section>
        ) : !scanResult ? (
          <div className="kiosk-grid">
            <form className="kiosk-panel kiosk-panel--scan max-w-lg" onSubmit={submitScan}>
              <div className="kiosk-panel__intro">
                <div className="kiosk-panel__icon">
                  <QrCode className="h-6 w-6" aria-hidden />
                </div>
                <div>
                  <h2 className="kiosk-panel__title">{ar ? 'مسح رمز QR' : 'Scan QR code'}</h2>
                  <p className="kiosk-panel__copy">
                    {ar ? 'وجّه الماسح أو الصق الحمولة ثم سجّل الحضور.' : 'Point the scanner or paste the payload, then check in.'}
                  </p>
                </div>
              </div>
              <TextInput
                label={ar ? 'مسح رمز QR' : 'Scan QR code'}
                name="qr_payload"
                value={qrPayload}
                onChange={(changeEvent) => setQrPayload(changeEvent.target.value)}
                placeholder={t('kioskScanPlaceholder')}
                required
                autoComplete="off"
                autoFocus
              />
              <SubmitButtonWithLoader loading={submitting} label={t('kioskCheckIn')} />
            </form>

            <form className="kiosk-panel max-w-lg" onSubmit={submitLookup}>
              <div className="kiosk-panel__intro">
                <div className="kiosk-panel__icon kiosk-panel__icon--muted">
                  <Search className="h-6 w-6" aria-hidden />
                </div>
                <div>
                  <h2 className="kiosk-panel__title">{ar ? 'بحث بديل' : 'Lookup fallback'}</h2>
                  <p className="kiosk-panel__copy">
                    {ar ? 'ابحث بالاسم أو البريد أو الهاتف إذا تعذّر المسح.' : 'Search by name, email, or phone if scanning fails.'}
                  </p>
                </div>
              </div>
              <TextInput
                label={ar ? 'بحث بديل' : 'Lookup fallback'}
                name="lookup_query"
                value={lookupQuery}
                onChange={(changeEvent) => setLookupQuery(changeEvent.target.value)}
                placeholder={t('kioskLookupPlaceholder')}
              />
              <SubmitButtonWithLoader loading={lookingUp} label={t('kioskSearch')} />
            </form>
          </div>
        ) : (
          <section className="kiosk-panel kiosk-panel--result mx-auto max-w-lg space-y-4">
            <div className="flex items-center gap-2 text-sm font-medium text-[var(--muted)]">
              <ShieldCheck className="h-4 w-4 text-[var(--brand)]" aria-hidden />
              {ar ? 'نتيجة الحضور' : 'Check-in result'}
            </div>
            <ScanResultCard result={scanResult} />
            {scanResult.result === 'accepted' && !printStatus && (
              <button type="button" className="button-primary inline-flex w-full items-center justify-center gap-2" onClick={() => void requestPrint()}>
                <Printer className="h-4 w-4" aria-hidden />
                {ar ? 'طباعة الشارة' : 'Print badge'}
              </button>
            )}
            {printStatus && (
              <div className="flex justify-center">
                <StatusBadge status={printStatus} label={`${ar ? 'الشارة' : 'Badge'} ${printStatus}`} size="md" />
              </div>
            )}
            <button type="button" className="button-secondary inline-flex w-full items-center justify-center gap-2" onClick={resetFlow}>
              <RotateCcw className="h-4 w-4" aria-hidden />
              {ar ? 'بدء من جديد' : 'Start over'}
            </button>
          </section>
        )}
      </main>

      {hasSession && (
        <footer className="kiosk-footer">
          <button type="button" className="kiosk-footer__action" onClick={clearSession}>
            {ar ? 'إلغاء ربط الجلسة' : 'Clear session'}
          </button>
        </footer>
      )}
    </div>
  )
}
