import { FormEvent, useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import { Eye, EyeOff } from 'lucide-react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { CheckboxInput, FormActions, SubmitButtonWithLoader, TextInput } from '@/components/forms'
import ValidationHintPopover from '@/components/feedback/ValidationHintPopover'
import AppBrand from '@/components/layout/AppBrand'
import { useInertiaFormValidation } from '@/hooks/useInertiaFormValidation'
import { useLocale } from '@/hooks/useLocale'
import { LOGIN_FIELD_LABELS, formFieldProps, normalizeInertiaErrors } from '@/lib/formatValidationErrors'
import en from '@/locales/en'
import ar from '@/locales/ar'

function loginClientErrors(email: string, password: string): Record<string, string> {
  const errors: Record<string, string> = {}
  const trimmedEmail = email.trim()

  if (trimmedEmail === '') {
    errors.email = 'The email field is required.'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedEmail)) {
    errors.email = 'The email field must be a valid email address.'
  }

  if (password === '') {
    errors.password = 'The password field is required.'
  }

  return errors
}

export default function Login() {
  const { locale, direction, localizedPath } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const form = useForm({ email: '', password: '', remember: false })
  const [showPassword, setShowPassword] = useState(false)
  const validation = useInertiaFormValidation(form.errors, {
    titleKey: 'loginFailed',
    fieldLabels: LOGIN_FIELD_LABELS,
  })

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    validation.clearValidation()

    const clientErrors = loginClientErrors(form.data.email, form.data.password)
    if (validation.applyErrors(clientErrors)) {
      return
    }

    form.post(localizedPath('/login'), {
      onError: (errors) => {
        validation.applyErrors(normalizeInertiaErrors(errors))
      },
      onFinish: () => form.reset('password'),
    })
  }

  return (
    <main dir={direction} lang={locale} className="grid min-h-screen place-items-center bg-[var(--surface)] p-6">
      <Head title={messages.loginTitle} />
      <form
        noValidate
        className="relative ta-card w-full max-w-md space-y-5 p-8"
        onSubmit={handleSubmit}
      >
        <div className="flex items-center gap-3">
          <a href="/" className="inline-flex items-center gap-2 transition-opacity hover:opacity-80">
            <AppBrand nameClassName="text-2xl font-bold" />
          </a>
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
        <div className="relative">
          <TextInput
            label={messages.loginPassword}
            name="password"
            type={showPassword ? 'text' : 'password'}
            required
            autoComplete="current-password"
            value={form.data.password}
            onChange={(event) => form.setData('password', event.target.value)}
            error={validation.fieldError('password') ?? form.errors.password}
            {...formFieldProps('password')}
          />
          <button
            type="button"
            className="absolute end-3 top-[2.15rem] flex h-8 w-8 items-center justify-center rounded-md text-[var(--muted)] transition-colors hover:bg-[var(--brand-soft)] hover:text-[var(--brand)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--focus-ring)]/40"
            onClick={() => setShowPassword((v) => !v)}
            aria-label={showPassword ? 'Hide password' : 'Show password'}
            tabIndex={-1}
          >
            {showPassword
              ? <EyeOff className="h-[1.125rem] w-[1.125rem]" strokeWidth={1.75} />
              : <Eye className="h-[1.125rem] w-[1.125rem]" strokeWidth={1.75} />}
          </button>
        </div>
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
