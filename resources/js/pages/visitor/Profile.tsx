import { FormEvent } from 'react'
import { useForm } from '@inertiajs/react'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import SelectInput from '@/components/forms/SelectInput'
import TextInput from '@/components/forms/TextInput'
import VisitorShell, { VisitorPageHeader, VisitorPanel } from '@/layouts/VisitorShell'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'

type Props = {
  profile: {
    name: string
    email: string
    preferred_locale: string
  }
}

export default function VisitorProfile({ profile }: Props) {
  const { t, localizedPath } = useLocale()
  const { toast } = useToast()
  const form = useForm({
    name: profile.name,
    preferred_locale: profile.preferred_locale || 'en',
  })

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    form.patch(localizedPath('/visitor/profile'), {
      preserveScroll: true,
      onSuccess: () => toast(t('visitorProfileUpdated'), 'success'),
    })
  }

  return (
    <VisitorShell title={t('visitorProfile')}>
      <VisitorPageHeader
        title={t('visitorProfile')}
        lead={t('visitorProfileLead')}
      />

      <VisitorPanel className="visitor-form-panel">
        <form className="visitor-form" onSubmit={handleSubmit}>
          <TextInput
            label={t('profileName')}
            value={form.data.name}
            onChange={(e) => form.setData('name', e.target.value)}
            error={form.errors.name}
            required
          />
          <TextInput
            label={t('profileEmail')}
            value={profile.email}
            hint={t('visitorEmailReadOnly')}
            readOnly
            disabled
          />
          <SelectInput
            label={t('adminDefaultLocale')}
            value={form.data.preferred_locale}
            onChange={(e) => form.setData('preferred_locale', e.target.value)}
            error={form.errors.preferred_locale}
            options={[
              { value: 'en', label: 'English' },
              { value: 'ar', label: 'العربية' },
            ]}
          />
          <div className="visitor-form__actions">
            <SubmitButtonWithLoader label={t('save')} loading={form.processing} />
          </div>
        </form>
      </VisitorPanel>
    </VisitorShell>
  )
}
