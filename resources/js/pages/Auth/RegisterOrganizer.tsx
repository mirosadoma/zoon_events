import { Head, useForm, usePage } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { CalendarDays, LayoutDashboard } from 'lucide-react'
import {
  FormActions,
  SubmitButtonWithLoader,
  TextInput,
  TextareaInput,
} from '@/components/forms'
import { useLocale } from '@/hooks/useLocale'
import en from '@/locales/en'
import ar from '@/locales/ar'

export default function RegisterOrganizer() {
  const { locale, direction, localizedPath } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const { flash } = usePage<{ flash: { status?: string } }>().props
  const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    organization_name: '',
    phone: '',
    message: '',
  })

  const submitted = flash?.status === 'registration-submitted'

  return (
    <main dir={direction} lang={locale} className="grid min-h-screen place-items-center bg-[var(--surface)] p-6">
      <Head title={messages.registerOrganizerTitle} />
      <form
        className="ta-card w-full max-w-lg space-y-5 p-8"
        onSubmit={(event) => {
          event.preventDefault()
          form.post(localizedPath('/register'), { onFinish: () => form.reset('password', 'password_confirmation') })
        }}
      >
        <div className="flex items-center gap-3">
          <span className="ta-sidebar-brand-icon" aria-hidden>
            <CalendarDays className="h-12 w-12" />
          </span>
          <div>
            <h1 className="text-2xl font-bold">{messages.registerOrganizerTitle}</h1>
            <p className="text-sm text-[var(--muted)]">{messages.registerOrganizerSubtitle}</p>
          </div>
        </div>

        {submitted && (
          <div className="ta-alert-success" role="status">
            {messages.registerOrganizerSuccess}
          </div>
        )}

        <TextInput
          label={messages.profileName}
          name="name"
          required
          value={form.data.name}
          error={form.errors.name}
          onChange={(event) => form.setData('name', event.target.value)}
        />
        <TextInput
          label={messages.profileEmail}
          name="email"
          type="email"
          required
          autoComplete="email"
          value={form.data.email}
          error={form.errors.email}
          onChange={(event) => form.setData('email', event.target.value)}
        />
        <TextInput
          label={messages.registerOrganization}
          name="organization_name"
          required
          value={form.data.organization_name}
          error={form.errors.organization_name}
          onChange={(event) => form.setData('organization_name', event.target.value)}
        />
        <TextInput
          label={messages.profilePhone}
          name="phone"
          value={form.data.phone}
          error={form.errors.phone}
          onChange={(event) => form.setData('phone', event.target.value)}
        />
        <TextInput
          label={messages.loginPassword}
          name="password"
          type="password"
          required
          autoComplete="new-password"
          value={form.data.password}
          error={form.errors.password}
          onChange={(event) => form.setData('password', event.target.value)}
        />
        <TextInput
          label={messages.registerConfirmPassword}
          name="password_confirmation"
          type="password"
          required
          autoComplete="new-password"
          value={form.data.password_confirmation}
          onChange={(event) => form.setData('password_confirmation', event.target.value)}
        />
        <TextareaInput
          label={messages.registerMessage}
          name="message"
          value={form.data.message}
          error={form.errors.message}
          onChange={(event) => form.setData('message', event.target.value)}
        />

        <FormActions>
          <SubmitButtonWithLoader label={messages.registerSubmit} loading={form.processing} />
          <LocalizedLink href={localizedPath('/login')} className="button-secondary inline-flex items-center gap-2">
            <LayoutDashboard className="h-4 w-4" />
            {messages.loginTitle}
          </LocalizedLink>
        </FormActions>
      </form>
    </main>
  )
}
