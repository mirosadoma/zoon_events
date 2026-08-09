import { FormEvent, useState } from 'react'
import { router, useForm } from '@inertiajs/react'
import { Eye, EyeOff } from 'lucide-react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { PageContent, PageHeader } from '@/components/layout'
import SelectInput from '@/components/forms/SelectInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import ValidationHintPopover from '@/components/feedback/ValidationHintPopover'
import TextInput from '@/components/forms/TextInput'
import { useInertiaFormValidation } from '@/hooks/useInertiaFormValidation'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { formFieldProps } from '@/lib/formatValidationErrors'
import en from '@/locales/en'
import ar from '@/locales/ar'

type Profile = {
  name: string
  email: string
  phone?: string | null
  role: string
  preferred_locale?: string
  tenant?: { id: string; name: string; slug: string } | null
  last_login_at?: string | null
}

type Props = {
  profile: Profile
}

function PasswordField({
  label,
  name,
  value,
  onChange,
  error,
  autoComplete,
  required,
}: {
  label: string
  name: string
  value: string
  onChange: (value: string) => void
  error?: string
  autoComplete?: string
  required?: boolean
}) {
  const { t } = useLocale()
  const [visible, setVisible] = useState(false)

  return (
    <div className="relative">
      <TextInput
        label={label}
        name={name}
        type={visible ? 'text' : 'password'}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        error={error}
        autoComplete={autoComplete}
        required={required}
        className="pe-11"
        {...formFieldProps(name)}
      />
      <button
        type="button"
        className="absolute end-2 top-[2.15rem] inline-flex h-8 w-8 items-center justify-center rounded-lg text-[var(--muted)] transition-colors hover:bg-[color-mix(in_srgb,var(--brand)_10%,transparent)] hover:text-[var(--brand)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--focus-ring)]/40"
        onClick={() => setVisible((current) => !current)}
        aria-label={visible ? t('visitorHidePassword') : t('visitorShowPassword')}
        tabIndex={-1}
      >
        {visible
          ? <EyeOff className="h-[1.125rem] w-[1.125rem]" strokeWidth={1.75} />
          : <Eye className="h-[1.125rem] w-[1.125rem]" strokeWidth={1.75} />}
      </button>
    </div>
  )
}

export default function Profile({ profile }: Props) {
  const { locale, t, localizedPath } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const { toast } = useToast()
  const form = useForm({
    name: profile.name,
    phone: profile.phone ?? '',
    preferred_locale: profile.preferred_locale ?? locale,
  })
  const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  })
  const validation = useInertiaFormValidation(form.errors, {
    titleKey: 'errorState',
    fieldLabels: {
      name: { en: messages.profileName, ar: messages.profileName },
      phone: { en: messages.profilePhone, ar: messages.profilePhone },
      preferred_locale: { en: messages.adminDefaultLocale, ar: messages.adminDefaultLocale },
    },
  })
  const passwordValidation = useInertiaFormValidation(passwordForm.errors, {
    titleKey: 'errorState',
    fieldLabels: {
      current_password: { en: messages.profileCurrentPassword, ar: messages.profileCurrentPassword },
      password: { en: messages.profileNewPassword, ar: messages.profileNewPassword },
      password_confirmation: { en: messages.profileConfirmPassword, ar: messages.profileConfirmPassword },
    },
  })

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    validation.clearValidation()
    form.patch('/profile', {
      preserveScroll: true,
      onSuccess: () => {
        toast(t('profilePageUpdated'), 'success')
        router.reload({ only: ['locale', 'direction', 'profile'] })
      },
      onError: () => toast(messages.errorState, 'error'),
    })
  }

  function handlePasswordSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    passwordValidation.clearValidation()
    passwordForm.put(localizedPath('/profile/password'), {
      preserveScroll: true,
      onSuccess: () => {
        toast(t('profilePasswordUpdated'), 'success')
        passwordForm.reset()
      },
      onError: () => toast(messages.errorState, 'error'),
    })
  }

  const lastLoginLabel = profile.last_login_at
    ? new Date(profile.last_login_at).toLocaleString(locale === 'ar' ? 'ar-EG' : 'en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
      })
    : '—'

  return (
    <DashboardLayout title={messages.profileTitle}>
      <PageHeader
        title={messages.profileTitle}
        breadcrumbs={[
          { label: messages.overview, href: '/dashboard' },
          { label: messages.profile },
        ]}
      />
      <PageContent>
        <div className="grid gap-6 lg:grid-cols-2">
          <form className="relative ta-card space-y-4" onSubmit={handleSubmit}>
            <div className="grid gap-4 sm:grid-cols-2">
              <TextInput
                label={messages.profileName}
                name="name"
                value={form.data.name}
                onChange={(event) => form.setData('name', event.target.value)}
                error={validation.fieldError('name') ?? form.errors.name}
                {...formFieldProps('name')}
                required
              />
              <TextInput label={messages.profileEmail} name="email" value={profile.email} disabled />
              <TextInput
                label={messages.profilePhone}
                name="phone"
                value={form.data.phone}
                onChange={(event) => form.setData('phone', event.target.value)}
                error={validation.fieldError('phone') ?? form.errors.phone}
                {...formFieldProps('phone')}
              />
              <SelectInput
                label={messages.adminDefaultLocale}
                name="preferred_locale"
                value={form.data.preferred_locale}
                onChange={(event) => form.setData('preferred_locale', event.target.value)}
                options={[
                  { value: 'en', label: 'English' },
                  { value: 'ar', label: 'العربية' },
                ]}
                error={validation.fieldError('preferred_locale') ?? form.errors.preferred_locale}
                {...formFieldProps('preferred_locale')}
              />
              <div className="grid gap-2 text-sm">
                <span className="font-medium text-[var(--ink)]">{messages.profileRole}</span>
                <span className="text-[var(--muted)]">{profile.role}</span>
              </div>
              <div className="grid gap-2 text-sm">
                <span className="font-medium text-[var(--ink)]">{messages.profileTenant}</span>
                <span className="text-[var(--muted)]">{profile.tenant?.name ?? '—'}</span>
              </div>
              <div className="grid gap-2 text-sm sm:col-span-2">
                <span className="font-medium text-[var(--ink)]">{messages.profileLastLogin}</span>
                <span className="text-[var(--muted)]">{lastLoginLabel}</span>
              </div>
            </div>
            <SubmitButtonWithLoader label={messages.save} loading={form.processing} />
          </form>

          <form className="relative ta-card space-y-4" onSubmit={handlePasswordSubmit}>
            <div className="space-y-1">
              <h2 className="text-base font-semibold text-[var(--ink)]">{messages.profilePasswordSection}</h2>
              <p className="text-sm text-[var(--muted)]">{messages.profilePasswordLead}</p>
            </div>
            <PasswordField
              label={messages.profileCurrentPassword}
              name="current_password"
              value={passwordForm.data.current_password}
              onChange={(value) => passwordForm.setData('current_password', value)}
              error={passwordValidation.fieldError('current_password') ?? passwordForm.errors.current_password}
              autoComplete="current-password"
              required
            />
            <PasswordField
              label={messages.profileNewPassword}
              name="password"
              value={passwordForm.data.password}
              onChange={(value) => passwordForm.setData('password', value)}
              error={passwordValidation.fieldError('password') ?? passwordForm.errors.password}
              autoComplete="new-password"
              required
            />
            <PasswordField
              label={messages.profileConfirmPassword}
              name="password_confirmation"
              value={passwordForm.data.password_confirmation}
              onChange={(value) => passwordForm.setData('password_confirmation', value)}
              error={passwordValidation.fieldError('password_confirmation') ?? passwordForm.errors.password_confirmation}
              autoComplete="new-password"
              required
            />
            <SubmitButtonWithLoader label={messages.save} loading={passwordForm.processing} />
          </form>
        </div>
        <ValidationHintPopover {...validation.hintProps} />
        <ValidationHintPopover {...passwordValidation.hintProps} />
      </PageContent>
    </DashboardLayout>
  )
}
