import { FormEvent } from 'react'
import { router, useForm } from '@inertiajs/react'
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

export default function Profile({ profile }: Props) {
  const { locale, t } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const { toast } = useToast()
  const form = useForm({
    name: profile.name,
    preferred_locale: profile.preferred_locale ?? locale,
  })
  const validation = useInertiaFormValidation(form.errors, {
    titleKey: 'errorState',
    fieldLabels: {
      name: { en: messages.profileName, ar: messages.profileName },
      preferred_locale: { en: messages.adminDefaultLocale, ar: messages.adminDefaultLocale },
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
        <form className="relative ta-card max-w-2xl space-y-4" onSubmit={handleSubmit}>
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
          <div className="grid gap-2 text-sm">
            <span className="font-medium text-[var(--ink)]">{messages.profileLastLogin}</span>
            <span className="text-[var(--muted)]">{lastLoginLabel}</span>
          </div>
          <SubmitButtonWithLoader label={messages.save} loading={form.processing} />
        </form>
        <ValidationHintPopover {...validation.hintProps} />
      </PageContent>
    </DashboardLayout>
  )
}
