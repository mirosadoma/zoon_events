import { FormEvent, useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormActions, SubmitButtonWithLoader, TextInput } from '@/components/forms'
import AppBrand from '@/components/layout/AppBrand'
import { useLocale } from '@/hooks/useLocale'
import en from '@/locales/en'
import ar from '@/locales/ar'

type Props = {
  token: string
}

export default function ForgotPasswordOtp({ token }: Props) {
  const { locale, direction, localizedPath } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const form = useForm({ code: '' })
  const [digits, setDigits] = useState(['', '', '', '', '', ''])

  function applyCode(next: string[]) {
    setDigits(next)
    form.setData('code', next.join(''))
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    form.post(localizedPath(`/forgot-password/otp/${token}`))
  }

  return (
    <main dir={direction} lang={locale} className="grid min-h-screen place-items-center bg-[var(--surface)] p-6">
      <Head title={messages.forgotPasswordOtpTitle} />
      <form className="ta-card w-full max-w-md space-y-5 p-8" onSubmit={handleSubmit} dir="ltr">
        <AppBrand nameClassName="text-2xl font-bold" />
        <div dir={direction}>
          <h1 className="text-xl font-semibold text-[var(--ink)]">{messages.forgotPasswordOtpTitle}</h1>
          <p className="mt-2 text-sm text-[var(--muted)]">{messages.forgotPasswordOtpLead}</p>
        </div>
        <div className="flex justify-between gap-2">
          {digits.map((digit, index) => (
            <input
              key={index}
              type="text"
              inputMode="numeric"
              maxLength={1}
              value={digit}
              className="control h-12 w-11 text-center text-lg font-semibold"
              onChange={(e) => {
                const value = e.target.value.replace(/\D/g, '').slice(-1)
                const next = [...digits]
                next[index] = value
                applyCode(next)
              }}
              onPaste={(e) => {
                e.preventDefault()
                const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6)
                if (!pasted) return
                const next = ['', '', '', '', '', '']
                for (let i = 0; i < pasted.length; i += 1) next[i] = pasted[i] ?? ''
                applyCode(next)
              }}
              aria-label={`${messages.forgotPasswordOtpTitle} ${index + 1}`}
            />
          ))}
        </div>
        {form.errors.code ? <p className="text-sm text-red-600">{form.errors.code}</p> : null}
        <FormActions>
          <SubmitButtonWithLoader label={messages.forgotPasswordOtpVerify} loading={form.processing} />
          <LocalizedLink href={localizedPath('/forgot-password')} className="button-secondary">
            {messages.forgotPasswordResend}
          </LocalizedLink>
        </FormActions>
      </form>
    </main>
  )
}
