import { FormEvent } from 'react'
import { Head, useForm } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormActions, SubmitButtonWithLoader, TextInput } from '@/components/forms'
import AppBrand from '@/components/layout/AppBrand'
import { useLocale } from '@/hooks/useLocale'
import en from '@/locales/en'
import ar from '@/locales/ar'

export default function ForgotPassword() {
  const { locale, direction, localizedPath } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const form = useForm({ email: '' })

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    form.post(localizedPath('/forgot-password'))
  }

  return (
    <main dir={direction} lang={locale} className="grid min-h-screen place-items-center bg-[var(--surface)] p-6">
      <Head title={messages.forgotPasswordTitle} />
      <form className="ta-card w-full max-w-md space-y-5 p-8" onSubmit={handleSubmit}>
        <AppBrand nameClassName="text-2xl font-bold" />
        <div>
          <h1 className="text-xl font-semibold text-[var(--ink)]">{messages.forgotPasswordTitle}</h1>
          <p className="mt-2 text-sm text-[var(--muted)]">{messages.forgotPasswordLead}</p>
        </div>
        <TextInput
          label={messages.profileEmail}
          type="email"
          required
          value={form.data.email}
          onChange={(e) => form.setData('email', e.target.value)}
          error={form.errors.email}
        />
        <FormActions>
          <SubmitButtonWithLoader label={messages.forgotPasswordSubmit} loading={form.processing} />
          <LocalizedLink href={localizedPath('/login')} className="button-secondary">
            {messages.backToLogin}
          </LocalizedLink>
        </FormActions>
      </form>
    </main>
  )
}
