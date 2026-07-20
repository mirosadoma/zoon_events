import { FormEvent, useState } from 'react'
import { useForm } from '@inertiajs/react'
import { Eye, EyeOff } from 'lucide-react'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import VisitorShell, { VisitorPageHeader, VisitorPanel } from '@/layouts/VisitorShell'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'

function PasswordField({
  label,
  value,
  onChange,
  error,
  autoComplete,
  required,
}: {
  label: string
  value: string
  onChange: (value: string) => void
  error?: string
  autoComplete?: string
  required?: boolean
}) {
  const { t } = useLocale()
  const [visible, setVisible] = useState(false)

  return (
    <div className="visitor-password-field relative">
      <TextInput
        label={label}
        type={visible ? 'text' : 'password'}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        error={error}
        autoComplete={autoComplete}
        required={required}
        className="pe-11"
      />
      <button
        type="button"
        className="visitor-password-toggle"
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

export default function VisitorPassword() {
  const { t, localizedPath } = useLocale()
  const { toast } = useToast()
  const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  })

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    form.put(localizedPath('/visitor/password'), {
      preserveScroll: true,
      onSuccess: () => {
        toast(t('visitorPasswordUpdated'), 'success')
        form.reset()
      },
    })
  }

  return (
    <VisitorShell title={t('visitorPassword')}>
      <VisitorPageHeader
        title={t('visitorPassword')}
        lead={t('visitorPasswordLead')}
      />

      <VisitorPanel className="visitor-form-panel">
        <form className="visitor-form" onSubmit={handleSubmit}>
          <PasswordField
            label={t('visitorCurrentPassword')}
            value={form.data.current_password}
            onChange={(value) => form.setData('current_password', value)}
            error={form.errors.current_password}
            autoComplete="current-password"
            required
          />
          <PasswordField
            label={t('visitorNewPassword')}
            value={form.data.password}
            onChange={(value) => form.setData('password', value)}
            error={form.errors.password}
            autoComplete="new-password"
            required
          />
          <PasswordField
            label={t('visitorConfirmPassword')}
            value={form.data.password_confirmation}
            onChange={(value) => form.setData('password_confirmation', value)}
            error={form.errors.password_confirmation}
            autoComplete="new-password"
            required
          />
          <div className="visitor-form__actions">
            <SubmitButtonWithLoader label={t('save')} loading={form.processing} />
          </div>
        </form>
      </VisitorPanel>
    </VisitorShell>
  )
}
