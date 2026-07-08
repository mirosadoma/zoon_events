import { useState } from 'react'
import ConsentNotice, { type ConsentDisclosures } from '@/components/identity/ConsentNotice'
import FaceCapturePanel from '@/components/identity/FaceCapturePanel'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import { useLocale } from '@/hooks/useLocale'

type VerificationStatus = {
  status: string
  verified_name?: string | null
  verified_nationality?: string | null
  verified_at?: string | null
}

type Props = {
  locale: 'en' | 'ar'
  event: { id: string; slug: string; name: LocalizedText }
  attendeeId: string
  accessToken: string
  noticeVersion: string
  residencyMode: string
  disclosures: ConsentDisclosures
  faceFallbackEnabled?: boolean
}

type Step = 'consent' | 'verifying' | 'face_capture' | 'result' | 'error'

export default function IdentityVerifyPage({
  locale,
  event,
  attendeeId,
  accessToken,
  noticeVersion,
  residencyMode,
  disclosures,
  faceFallbackEnabled = false,
}: Props) {
  const { t } = useLocale()
  const rtl = locale === 'ar'
  const [step, setStep] = useState<Step>('consent')
  const [status, setStatus] = useState<VerificationStatus | null>(null)
  const [errorCode, setErrorCode] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [consented, setConsented] = useState(false)

  const baseUrl = `/api/v1/tenant/events/${event.id}/attendees/${attendeeId}/identity`
  const headers = {
    'Content-Type': 'application/json',
    'X-Order-Access-Token': accessToken,
    'Idempotency-Key': crypto.randomUUID(),
  }

  async function postConsent(accepted: boolean) {
    setBusy(true)
    setErrorCode(null)
    try {
      const response = await fetch(`${baseUrl}/consent`, {
        method: 'POST',
        headers,
        body: JSON.stringify({
          notice_version: noticeVersion,
          residency_mode: residencyMode,
          consented: accepted,
        }),
      })
      const body = await response.json().catch(() => ({}))
      if (!response.ok) {
        setErrorCode(body?.code ?? 'error')
        setStep('error')
        return
      }
      if (!accepted) {
        setStatus({ status: 'pending' })
        setStep('result')
        return
      }
      setConsented(true)
      await startVerification()
    } finally {
      setBusy(false)
    }
  }

  async function startVerification() {
    setStep('verifying')
    setBusy(true)
    setErrorCode(null)
    try {
      const response = await fetch(`${baseUrl}/verification`, {
        method: 'POST',
        headers: { ...headers, 'Idempotency-Key': crypto.randomUUID() },
      })
      const body = await response.json().catch(() => ({}))
      if (response.status === 503 && body?.code === 'identity_provider_unavailable') {
        if (faceFallbackEnabled) {
          setStep('face_capture')
          return
        }
        setErrorCode('identity_provider_unavailable')
        setStep('error')
        return
      }
      if (!response.ok) {
        setErrorCode(body?.code ?? 'error')
        setStep('error')
        return
      }
      await refreshStatus()
    } finally {
      setBusy(false)
    }
  }

  async function submitFaceCapture(capture: string) {
    const response = await fetch(`${baseUrl}/face-capture`, {
      method: 'POST',
      headers: { ...headers, 'Idempotency-Key': crypto.randomUUID() },
      body: JSON.stringify({ capture }),
    })
    const body = await response.json().catch(() => ({}))
    if (!response.ok) {
      setErrorCode(body?.code ?? 'error')
      setStep('error')
      return
    }
    setStatus(body.data?.verification as VerificationStatus)
    setStep('result')
  }

  async function refreshStatus() {
    const response = await fetch(`${baseUrl}/verification`, {
      headers: { 'X-Order-Access-Token': accessToken },
    })
    const body = await response.json().catch(() => ({}))
    if (!response.ok) {
      setErrorCode(body?.code ?? 'error')
      setStep('error')
      return
    }
    setStatus(body.data as VerificationStatus)
    setStep('result')
  }

  return (
    <main className="identity-verify" lang={locale} dir={rtl ? 'rtl' : 'ltr'}>
      <header>
        <h1>{t('identityVerifyTitle')}</h1>
        <p>
          <LocalizedEventContent value={event.name} locale={locale} />
        </p>
      </header>

      {step === 'consent' ? (
        <>
          <ConsentNotice
            locale={locale}
            disclosures={disclosures}
            residencyMode={residencyMode}
            noticeVersion={noticeVersion}
          />
          <div className="identity-verify-actions">
            <button type="button" disabled={busy} onClick={() => postConsent(false)}>
              {t('identityConsentDecline')}
            </button>
            <button type="button" disabled={busy} onClick={() => postConsent(true)}>
              {t('identityConsentAccept')}
            </button>
          </div>
        </>
      ) : null}

      {step === 'verifying' ? <p role="status">{t('identityVerifyInProgress')}</p> : null}

      {step === 'face_capture' && consented ? (
        <FaceCapturePanel disabled={busy} onSubmit={submitFaceCapture} />
      ) : null}

      {step === 'result' && status ? (
        <section aria-live="polite">
          <p>{t('identityVerifyStatus')}: {status.status}</p>
          {status.verified_name ? <p>{status.verified_name}</p> : null}
          {status.verified_nationality ? <p>{status.verified_nationality}</p> : null}
        </section>
      ) : null}

      {step === 'error' ? (
        <p role="alert">
          {errorCode === 'identity_provider_unavailable'
            ? t('identityReasonProviderUnavailable')
            : t('identityVerifyFailed')}
        </p>
      ) : null}
    </main>
  )
}
