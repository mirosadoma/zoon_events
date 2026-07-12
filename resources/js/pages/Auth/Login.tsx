import { Head, useForm } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { CheckboxInput, FormActions, SubmitButtonWithLoader, TextInput } from '@/components/forms'
import ValidationHintPopover from '@/components/feedback/ValidationHintPopover'
import AppBrand from '@/components/layout/AppBrand'
import { useInertiaFormValidation } from '@/hooks/useInertiaFormValidation'
import { useLocale } from '@/hooks/useLocale'
import { LOGIN_FIELD_LABELS, formFieldProps } from '@/lib/formatValidationErrors'
import en from '@/locales/en'
import ar from '@/locales/ar'

export default function Login() {
  const { locale, direction, localizedPath } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const form = useForm({ email: '', password: '', remember: false })
  const validation = useInertiaFormValidation(form.errors, {
    titleKey: 'loginFailed',
    fieldLabels: LOGIN_FIELD_LABELS,
  })

  return (
    <main dir={direction} lang={locale} className="grid min-h-screen place-items-center bg-[var(--surface)] p-6">
      <Head title={messages.loginTitle} />
      <form
        className="relative ta-card w-full max-w-md space-y-5 p-8"
        onSubmit={(event) => {
          event.preventDefault()
          validation.clearValidation()
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
          error={validation.fieldError('email') ?? form.errors.email}
          {...formFieldProps('email')}
        />
        <TextInput
          label={messages.loginPassword}
          name="password"
          type="password"
          required
          autoComplete="current-password"
          value={form.data.password}
          onChange={(event) => form.setData('password', event.target.value)}
          error={validation.fieldError('password') ?? form.errors.password}
          {...formFieldProps('password')}
        />
        <CheckboxInput
          label={messages.loginRemember}
          name="remember"
          checked={form.data.remember}
          onChange={(event) => form.setData('remember', event.target.checked)}
        />
        <FormActions>
          <SubmitButtonWithLoader label={messages.loginSubmit} loading={form.processing} />
          <LocalizedLink href={localizedPath('/register')} className="button-secondary">
            {messages.registerCta}
          </LocalizedLink>
        </FormActions>
        <ValidationHintPopover {...validation.hintProps} />
      </form>
    </main>
  )
}
