import { Head, useForm } from '@inertiajs/react'
import { CheckboxInput, FormActions, SubmitButtonWithLoader, TextInput } from '@/components/forms'
import { useLocale } from '@/hooks/useLocale'
import en from '@/locales/en'
import ar from '@/locales/ar'

export default function Login() {
  const { locale, direction } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const form = useForm({ email: '', password: '', remember: false })

  return (
    <main dir={direction} lang={locale} className="grid min-h-screen place-items-center bg-slate-100 p-6 dark:bg-slate-950">
      <Head title={messages.loginTitle} />
      <form
        className="w-full max-w-md space-y-5 rounded-2xl bg-white p-8 shadow-xl dark:bg-slate-900"
        onSubmit={(event) => {
          event.preventDefault()
          form.post('/login', { onFinish: () => form.reset('password') })
        }}
      >
        <h1 className="text-2xl font-semibold">{messages.loginTitle}</h1>
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
          <p role="alert" className="text-red-700">{messages.loginFailed}</p>
        )}
        <FormActions>
          <SubmitButtonWithLoader label={messages.loginSubmit} loading={form.processing} />
        </FormActions>
      </form>
    </main>
  )
}
