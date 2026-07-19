import { FormEvent, useState } from 'react'
import RegistrationPageControls from '@/components/registration/RegistrationPageControls'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import { useLocale } from '@/hooks/useLocale'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { formatMoney } from '@/lib/formatMoney'

type Props = {
  locale: 'en' | 'ar'
  event: {
    slug: string
    name: LocalizedText
  }
  publicReference: string
  accessToken: string
  totalMinor: number
  currency: string
  submitUrl: string
}

export default function Payment({
  locale,
  event,
  accessToken,
  totalMinor,
  currency,
  submitUrl,
}: Props) {
  const { t, direction } = useLocale()
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [form, setForm] = useState({
    card_name: '',
    card_number: '',
    card_expiry: '',
    card_cvv: '',
  })

  function update(field: keyof typeof form) {
    return (e: React.ChangeEvent<HTMLInputElement>) => {
      setForm((current) => ({ ...current, [field]: e.target.value }))
    }
  }

  async function handleSubmit(submitEvent: FormEvent) {
    submitEvent.preventDefault()
    setError(null)
    setSubmitting(true)

    try {
      const result = await apiFetch<{
        next?: string
        confirmation_url?: string
        failed_url?: string
      }>(submitUrl, {
        method: 'POST',
        body: {
          access_token: accessToken,
          ...form,
        },
      })

      if (result.failed_url) {
        window.location.assign(result.failed_url)
        return
      }

      if (result.confirmation_url) {
        window.location.assign(result.confirmation_url)
        return
      }

      setError(t('publicRegistrationFailed'))
    } catch (caught) {
      if (caught instanceof ApiFetchError) {
        setError(caught.message)
      } else {
        setError(t('publicRegistrationFailed'))
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
          <h1 className="text-3xl font-semibold text-[var(--ink)]">{t('publicRegistrationPaymentTitle')}</h1>
          <p className="mt-3 text-[var(--muted)]">{t('publicRegistrationPaymentLead')}</p>
          <p className="mt-4 text-lg font-semibold text-[var(--ink)]">
            {t('publicRegistrationTotal')}: {formatMoney(totalMinor, currency, locale)}
          </p>
          <p className="mt-2 text-xs text-[var(--muted)]">{t('publicRegistrationPaymentDemoHint')}</p>

          <form className="mt-8 space-y-4" onSubmit={handleSubmit}>
            <label className="block space-y-1.5">
              <span className="text-sm font-medium text-[var(--ink)]">{t('publicRegistrationPaymentCardName')}</span>
              <input
                className="control w-full"
                value={form.card_name}
                onChange={update('card_name')}
                required
                autoComplete="cc-name"
              />
            </label>
            <label className="block space-y-1.5">
              <span className="text-sm font-medium text-[var(--ink)]">{t('publicRegistrationPaymentCardNumber')}</span>
              <input
                className="control w-full"
                value={form.card_number}
                onChange={update('card_number')}
                required
                inputMode="numeric"
                autoComplete="cc-number"
                placeholder="4242 4242 4242 4242"
              />
            </label>
            <div className="grid grid-cols-2 gap-3">
              <label className="block space-y-1.5">
                <span className="text-sm font-medium text-[var(--ink)]">{t('publicRegistrationPaymentExpiry')}</span>
                <input
                  className="control w-full"
                  value={form.card_expiry}
                  onChange={update('card_expiry')}
                  required
                  placeholder="12/30"
                  autoComplete="cc-exp"
                />
              </label>
              <label className="block space-y-1.5">
                <span className="text-sm font-medium text-[var(--ink)]">{t('publicRegistrationPaymentCvv')}</span>
                <input
                  className="control w-full"
                  value={form.card_cvv}
                  onChange={update('card_cvv')}
                  required
                  inputMode="numeric"
                  autoComplete="cc-csc"
                />
              </label>
            </div>

            {error ? <p role="alert" className="registration-invite-error">{error}</p> : null}

            <button type="submit" className="button-primary w-full" disabled={submitting}>
              {submitting ? t('publicRegistrationPaymentPaying') : t('publicRegistrationPaymentPay')}
            </button>
          </form>
        </div>
      </main>
    </>
  )
}
