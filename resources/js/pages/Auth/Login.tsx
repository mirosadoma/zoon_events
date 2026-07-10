import { Head, useForm } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { CheckboxInput, FormActions, SubmitButtonWithLoader, TextInput } from '@/components/forms'
import AppBrand from '@/components/layout/AppBrand'
import { useLocale } from '@/hooks/useLocale'
import { useSiteBranding } from '@/hooks/useSiteBranding'
import en from '@/locales/en'
import ar from '@/locales/ar'

export default function Login() {
  const { locale, direction, localizedPath } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const form = useForm({ email: '', password: '', remember: false })

  return (
    <main dir={direction} lang={locale} className="grid min-h-screen place-items-center bg-[var(--surface)] p-6">
      <Head title={messages.loginTitle} />
      <form
        className="ta-card w-full max-w-md space-y-5 p-8"
        onSubmit={(event) => {
          event.preventDefault()
          form.post(localizedPath('/login'), { onFinish: () => form.reset('password') })
        }}
      >
        <div className="flex items-center gap-3">
          <AppBrand nameClassName="text-2xl font-bold" />
          <div>
            <p className="text-sm text-slate-500">{messages.loginTitle}</p>
          </div>
        </div>
        <TextInput
          label={messages.profileEmail}
          name="email"
          type="email"
          required
          autoComplete="username"
          value={form.data.email}
          onChange={(event) => form.setData('email', event.target.value)}
        />
        <TextInput
          label={messages.loginPassword}
          name="password"
          type="password"
          required
          autoComplete="current-password"
          value={form.data.password}
          onChange={(event) => form.setData('password', event.target.value)}
        />
        <CheckboxInput
          label={messages.loginRemember}
          name="remember"
          checked={form.data.remember}
          onChange={(event) => form.setData('remember', event.target.checked)}
        />
        {Object.keys(form.errors).length > 0 && (
          <div className="ta-alert-error" role="alert">{messages.loginFailed}</div>
        )}
        <FormActions>
          <SubmitButtonWithLoader label={messages.loginSubmit} loading={form.processing} />
          <LocalizedLink href={localizedPath('/register')} className="button-secondary">
            {messages.registerCta}
          </LocalizedLink>
        </FormActions>
      </form>
    </main>
  )
}
