import { ClipboardEvent, FormEvent, useMemo, useRef, useState } from 'react'
import RegistrationPageControls from '@/components/registration/RegistrationPageControls'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import { useLocale } from '@/hooks/useLocale'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'

const OTP_LENGTH = 6

type Props = {
  locale: 'en' | 'ar'
  event: {
    id: string
    slug: string
    name: LocalizedText
  }
  email: string
  token: string
  submitUrl: string
  registerUrl: string
}

export default function PublicRegistrationOtp({
  locale,
  event,
  email,
  submitUrl,
  registerUrl,
}: Props) {
  const { t } = useLocale()
  const direction = locale === 'ar' ? 'rtl' : 'ltr'
  const [digits, setDigits] = useState(() => Array.from({ length: OTP_LENGTH }, () => ''))
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const inputs = useRef<Array<HTMLInputElement | null>>([])

  const code = useMemo(() => digits.join(''), [digits])

  function applyOtpDigits(raw: string, startIndex = 0) {
    const cleaned = raw.replace(/\D/g, '').slice(0, OTP_LENGTH - startIndex)
    if (!cleaned) {
      return
    }

    setDigits((current) => {
      const next = [...current]
      for (let offset = 0; offset < cleaned.length; offset += 1) {
        next[startIndex + offset] = cleaned[offset] ?? ''
      }
      return next
    })

    const focusIndex = Math.min(startIndex + cleaned.length, OTP_LENGTH - 1)
    requestAnimationFrame(() => {
      inputs.current[focusIndex]?.focus()
    })
  }

  function updateDigit(index: number, value: string) {
    const cleaned = value.replace(/\D/g, '')
    if (cleaned.length > 1) {
      applyOtpDigits(cleaned, index)
      return
    }

    setDigits((current) => {
      const next = [...current]
      next[index] = cleaned.slice(-1)
      return next
    })
    if (cleaned && index < OTP_LENGTH - 1) {
      inputs.current[index + 1]?.focus()
    }
  }

  function handlePaste(index: number, event: ClipboardEvent<HTMLInputElement>) {
    event.preventDefault()
    const pasted = event.clipboardData.getData('text')
    const cleaned = pasted.replace(/\D/g, '').slice(0, OTP_LENGTH)
    if (!cleaned) {
      return
    }

    // Full codes always fill from the first box; partial pastes continue from focus.
    applyOtpDigits(cleaned, cleaned.length >= OTP_LENGTH ? 0 : index)
  }

  function handleKeyDown(index: number, key: string) {
    if (key === 'Backspace' && digits[index] === '' && index > 0) {
      inputs.current[index - 1]?.focus()
    }
  }

  async function handleSubmit(submitEvent: FormEvent) {
    submitEvent.preventDefault()
    setError(null)

    if (code.length !== OTP_LENGTH) {
      setError(t('publicRegistrationOtpInvalid'))
      return
    }

    setSubmitting(true)
    try {
      const result = await apiFetch<{
        next?: string
        payment_url?: string
        confirmation_url?: string
      }>(submitUrl, {
        method: 'POST',
        body: { code },
      })

      if (result.payment_url) {
        window.location.assign(result.payment_url)
        return
      }

      if (result.confirmation_url) {
        window.location.assign(result.confirmation_url)
        return
      }

      setError(t('publicRegistrationOtpInvalid'))
    } catch (caught) {
      if (caught instanceof ApiFetchError) {
        setError(caught.message || t('publicRegistrationOtpInvalid'))
      } else {
        setError(t('publicRegistrationOtpInvalid'))
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <>
      <RegistrationPageControls locale={locale} />
      <main className="registration-invite min-h-screen px-4 py-10" lang={locale} dir={direction}>
        <div className="registration-invite-card mx-auto max-w-lg">
          <p className="registration-invite-kicker">
            <LocalizedEventContent value={event.name} locale={locale} />
          </p>
          <h1 className="text-3xl font-semibold text-[var(--ink)]">{t('publicRegistrationOtpTitle')}</h1>
          <p className="mt-3 text-[var(--muted)]">
            {t('publicRegistrationOtpLead').replace(':email', email)}
          </p>

          <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
            <div>
              <label className="mb-2 block text-sm font-medium text-[var(--ink)]">
                {t('publicRegistrationOtpCode')}
              </label>
              <div className="flex justify-between gap-2" dir="ltr">
                {digits.map((digit, index) => (
                  <input
                    key={index}
                    ref={(node) => {
                      inputs.current[index] = node
                    }}
                    type="text"
                    inputMode="numeric"
                    autoComplete={index === 0 ? 'one-time-code' : 'off'}
                    maxLength={1}
                    value={digit}
                    onChange={(e) => updateDigit(index, e.target.value)}
                    onPaste={(e) => handlePaste(index, e)}
                    onKeyDown={(e) => handleKeyDown(index, e.key)}
                    className="control h-12 w-11 text-center text-lg font-semibold"
                    aria-label={`${t('publicRegistrationOtpCode')} ${index + 1}`}
                  />
                ))}
              </div>
            </div>

            {error ? <p role="alert" className="registration-invite-error">{error}</p> : null}

            <button type="submit" className="button-primary w-full" disabled={submitting}>
              {submitting ? t('publicRegistrationOtpVerifying') : t('publicRegistrationOtpVerify')}
            </button>
          </form>

          <p className="mt-6 text-sm text-[var(--muted)]">{t('publicRegistrationOtpResendHint')}</p>
          <a href={registerUrl} className="mt-3 inline-block text-sm text-[var(--brand)] underline">
            {t('publicRegistrationPaymentFailedBack')}
          </a>
        </div>
      </main>
    </>
  )
}
