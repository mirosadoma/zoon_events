import { FormEvent } from 'react'
import { Head, useForm } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormActions, SubmitButtonWithLoader, TextInput } from '@/components/forms'
import AppBrand from '@/components/layout/AppBrand'
import { useLocale } from '@/hooks/useLocale'
import en from '@/locales/en'
import ar from '@/locales/ar'

type Props = {
  resetToken: string
  email: string
}

export default function ResetPassword({ resetToken, email }: Props) {
  const { locale, direction, localizedPath } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const form = useForm({
    password: '',
    password_confirmation: '',
  })

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    form.post(localizedPath(`/forgot-password/reset/${resetToken}`))
  }

  return (
    <main dir={direction} lang={locale} className="grid min-h-screen place-items-center bg-[var(--surface)] p-6">
      <Head title={messages.resetPasswordTitle} />
      <form className="ta-card w-full max-w-md space-y-5 p-8" onSubmit={handleSubmit}>
        <AppBrand nameClassName="text-2xl font-bold" />
        <div>
          <h1 className="text-xl font-semibold text-[var(--ink)]">{messages.resetPasswordTitle}</h1>
          <p className="mt-2 text-sm text-[var(--muted)]">
            {messages.resetPasswordLead.replace(':email', email)}
          </p>
        </div>
        <TextInput
          label={messages.visitorNewPassword}
          type="password"
          required
          value={form.data.password}
          onChange={(e) => form.setData('password', e.target.value)}
          error={form.errors.password}
        />
        <TextInput
          label={messages.visitorConfirmPassword}
          type="password"
          required
          value={form.data.password_confirmation}
          onChange={(e) => form.setData('password_confirmation', e.target.value)}
        />
        <FormActions>
          <SubmitButtonWithLoader label={messages.resetPasswordSubmit} loading={form.processing} />
          <LocalizedLink href={localizedPath('/login')} className="button-secondary">
            {messages.backToLogin}
          </LocalizedLink>
        </FormActions>
      </form>
    </main>
  )
}
