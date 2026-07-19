import { FormEvent, useState } from 'react'
import { useForm } from '@inertiajs/react'
import DashboardLayout from '@/layouts/DashboardLayout'
import {
  CheckboxInput,
  FileInput,
  FormActions,
  FormSection,
  SubmitButtonWithLoader,
  TextInput,
  TextareaInput,
} from '@/components/forms'
import ValidationHintPopover from '@/components/feedback/ValidationHintPopover'
import { PageContent, PageHeader } from '@/components/layout'
import { useInertiaFormValidation } from '@/hooks/useInertiaFormValidation'
import { useLocale } from '@/hooks/useLocale'
import { formFieldProps } from '@/lib/formatValidationErrors'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { useToast } from '@/hooks/useToast'
import en from '@/locales/en'
import ar from '@/locales/ar'

type Settings = {
  app_name_en: string
  app_name_ar: string
  logo_url?: string | null
  favicon_url?: string | null
  support_email: string | null
  support_phone: string | null
  about_en: string | null
  about_ar: string | null
  maintenance_enabled: boolean
  maintenance_message_en: string | null
  maintenance_message_ar: string | null
}

type Props = {
  settings: Settings
  canManage: boolean
}

export default function SiteSettings({ settings, canManage }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const localizedRouter = useLocalizedRouter()
  const [logoFile, setLogoFile] = useState<File | null>(null)
  const [faviconFile, setFaviconFile] = useState<File | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const form = useForm({
    app_name_en: settings.app_name_en,
    app_name_ar: settings.app_name_ar,
    support_email: settings.support_email ?? '',
    support_phone: settings.support_phone ?? '',
    about_en: settings.about_en ?? '',
    about_ar: settings.about_ar ?? '',
    maintenance_enabled: settings.maintenance_enabled,
    maintenance_message_en: settings.maintenance_message_en ?? '',
    maintenance_message_ar: settings.maintenance_message_ar ?? '',
  })
  const validation = useInertiaFormValidation(form.errors, {
    titleKey: 'errorState',
    fieldLabels: {
      app_name_en: { en: t('appNameEn'), ar: t('appNameEn') },
      app_name_ar: { en: t('appNameAr'), ar: t('appNameAr') },
      support_email: { en: t('profileEmail'), ar: t('profileEmail') },
      support_phone: { en: t('profilePhone'), ar: t('profilePhone') },
      about_en: { en: t('aboutEn'), ar: t('aboutEn') },
      about_ar: { en: t('aboutAr'), ar: t('aboutAr') },
      maintenance_message_en: { en: t('maintenanceMessageEn'), ar: t('maintenanceMessageEn') },
      maintenance_message_ar: { en: t('maintenanceMessageAr'), ar: t('maintenanceMessageAr') },
      logo: { en: t('siteSettingsLogo'), ar: t('siteSettingsLogo') },
      favicon: { en: t('siteSettingsFavicon'), ar: t('siteSettingsFavicon') },
    },
  })

  function submit(event: FormEvent) {
    event.preventDefault()
    validation.clearValidation()
    setSubmitting(true)

    const payload = new FormData()
    payload.append('_method', 'patch')
    payload.append('app_name_en', form.data.app_name_en)
    payload.append('app_name_ar', form.data.app_name_ar)
    payload.append('support_email', form.data.support_email)
    payload.append('support_phone', form.data.support_phone)
    payload.append('about_en', form.data.about_en)
    payload.append('about_ar', form.data.about_ar)
    payload.append('maintenance_enabled', form.data.maintenance_enabled ? '1' : '0')
    payload.append('maintenance_message_en', form.data.maintenance_message_en)
    payload.append('maintenance_message_ar', form.data.maintenance_message_ar)

    if (logoFile) {
      payload.append('logo', logoFile)
    }

    if (faviconFile) {
      payload.append('favicon', faviconFile)
    }

    localizedRouter.post('/platform/site-settings', payload, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        setLogoFile(null)
        setFaviconFile(null)
        toast(t('siteSettingsSaved'), 'success')
      },
      onError: () => toast(t('actionFailed'), 'error'),
      onFinish: () => setSubmitting(false),
    })
  }

  return (
    <DashboardLayout title={t('siteSettingsTitle')}>
      <PageHeader title={t('siteSettingsTitle')} description={t('siteSettingsDescription')} />
      <PageContent>
        <form className="relative space-y-8" onSubmit={submit}>
          <FormSection title={t('siteSettingsBranding')}>
            <div className="grid gap-4 md:grid-cols-2">
              <TextInput
                label={t('appNameEn')}
                name="app_name_en"
                value={form.data.app_name_en}
                disabled={!canManage}
                error={validation.fieldError('app_name_en') ?? form.errors.app_name_en}
                {...formFieldProps('app_name_en')}
                onChange={(event: React.ChangeEvent<HTMLInputElement>) => form.setData('app_name_en', event.target.value)}
              />
              <TextInput
                label={t('appNameAr')}
                name="app_name_ar"
                value={form.data.app_name_ar}
                disabled={!canManage}
                error={validation.fieldError('app_name_ar') ?? form.errors.app_name_ar}
                {...formFieldProps('app_name_ar')}
                onChange={(event: React.ChangeEvent<HTMLInputElement>) => form.setData('app_name_ar', event.target.value)}
              />
              <FileInput
                label={t('siteSettingsLogo')}
                name="logo"
                accept="image/png,image/jpeg,image/webp,image/svg+xml"
                disabled={!canManage}
                hint={settings.logo_url ? t('siteSettingsCurrentLogo') : undefined}
                onChange={(event) => setLogoFile(event.target.files?.[0] ?? null)}
              />
              <FileInput
                label={t('siteSettingsFavicon')}
                name="favicon"
                accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml,.ico"
                disabled={!canManage}
                hint={settings.favicon_url ? t('siteSettingsCurrentFavicon') : undefined}
                onChange={(event) => setFaviconFile(event.target.files?.[0] ?? null)}
              />
            </div>
            {(settings.logo_url || settings.favicon_url) && (
              <div className="mt-4 flex flex-wrap items-center gap-6">
                {settings.logo_url ? (
                  <img src={settings.logo_url} alt={t('siteSettingsLogo')} className="h-12 w-auto rounded border border-[var(--border)] bg-white p-2" />
                ) : null}
                {settings.favicon_url ? (
                  <img src={settings.favicon_url} alt={t('siteSettingsFavicon')} className="h-10 w-10 rounded border border-[var(--border)] bg-white p-1" />
                ) : null}
              </div>
            )}
          </FormSection>

          <FormSection title={t('siteSettingsContact')}>
            <div className="grid gap-4 md:grid-cols-2">
              <TextInput
                label={t('profileEmail')}
                name="support_email"
                type="email"
                value={form.data.support_email}
                disabled={!canManage}
                error={form.errors.support_email}
                onChange={(event: React.ChangeEvent<HTMLInputElement>) => form.setData('support_email', event.target.value)}
              />
              <TextInput
                label={t('profilePhone')}
                name="support_phone"
                value={form.data.support_phone}
                disabled={!canManage}
                error={form.errors.support_phone}
                onChange={(event: React.ChangeEvent<HTMLInputElement>) => form.setData('support_phone', event.target.value)}
              />
            </div>
          </FormSection>

          <FormSection title={t('siteSettingsAbout')}>
            <div className="grid gap-4 md:grid-cols-2">
              <TextareaInput
                label={t('aboutEn')}
                name="about_en"
                value={form.data.about_en}
                disabled={!canManage}
                error={form.errors.about_en}
                onChange={(event: React.ChangeEvent<HTMLTextAreaElement>) => form.setData('about_en', event.target.value)}
              />
              <TextareaInput
                label={t('aboutAr')}
                name="about_ar"
                value={form.data.about_ar}
                disabled={!canManage}
                error={form.errors.about_ar}
                onChange={(event: React.ChangeEvent<HTMLTextAreaElement>) => form.setData('about_ar', event.target.value)}
              />
            </div>
          </FormSection>
          <hr className="my-8 border-t border-[var(--border)]" style={{ borderColor: '#0069ff' }} />
          <FormSection title={t('siteSettingsMaintenance')} style={{ borderColor: '#0069ff' }}>
            <CheckboxInput
              label={t('maintenanceEnabled')}
              name="maintenance_enabled"
              checked={form.data.maintenance_enabled}
              disabled={!canManage}
              onChange={(event: React.ChangeEvent<HTMLInputElement>) => form.setData('maintenance_enabled', event.target.checked)}
            />
            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <TextareaInput
                label={t('maintenanceMessageEn')}
                name="maintenance_message_en"
                value={form.data.maintenance_message_en}
                disabled={!canManage}
                error={form.errors.maintenance_message_en}
                onChange={(event: React.ChangeEvent<HTMLTextAreaElement>) => form.setData('maintenance_message_en', event.target.value)}
              />
              <TextareaInput
                label={t('maintenanceMessageAr')}
                name="maintenance_message_ar"
                value={form.data.maintenance_message_ar}
                disabled={!canManage}
                error={form.errors.maintenance_message_ar}
                onChange={(event: React.ChangeEvent<HTMLTextAreaElement>) => form.setData('maintenance_message_ar', event.target.value)}
              />
            </div>
          </FormSection>

          {canManage && (
            <FormActions>
              <SubmitButtonWithLoader label={t('saveChanges')} loading={submitting || form.processing} />
            </FormActions>
          )}
        </form>
        <ValidationHintPopover {...validation.hintProps} />
      </PageContent>
    </DashboardLayout>
  )
}
