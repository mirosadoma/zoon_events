import { useState, FormEvent, useCallback, useEffect } from 'react'
import { router } from '@inertiajs/react'
import { Trash2, ArrowLeft } from 'lucide-react'
import DashboardLayout from '@/layouts/DashboardLayout'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { PageHeader } from '@/components/layout'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import SimpleHtmlEditor from '@/components/email/SimpleHtmlEditor'

type Props = {
  event: {
    id: string
    name: { en: string; ar: string }
  }
  type: string
  template: {
    subject_en: string
    subject_ar: string
    html_body_en: string
    html_body_ar: string
  } | null
}

const TYPE_LABELS: Record<string, { en: string; ar: string }> = {
  invitation: { en: 'Invitation Email', ar: 'بريد الدعوة' },
  otp: { en: 'OTP Verification', ar: 'رمز التحقق' },
  confirmation: { en: 'Confirmation Email', ar: 'بريد التأكيد' },
}

const PLACEHOLDERS_BY_TYPE: Record<string, string[]> = {
  invitation: ['{{user_name}}', '{{user_email}}', '{{event_name}}', '{{registration_url}}'],
  otp: ['{{otp}}'],
  confirmation: ['{{user_name}}', '{{user_email}}', '{{user_phone}}', '{{event_name}}', '{{qr_code}}', '{{entry_card_url}}'],
}

export default function EmailTemplateEditor({ event, type, template, tenantId }: Props & { tenantId?: string }) {
  const { locale, direction, t, localizedPath } = useLocale()
  const { toast } = useToast()

  const [subjectEn, setSubjectEn] = useState(template?.subject_en ?? '')
  const [subjectAr, setSubjectAr] = useState(template?.subject_ar ?? '')
  const [bodyEn, setBodyEn] = useState(template?.html_body_en ?? '')
  const [bodyAr, setBodyAr] = useState(template?.html_body_ar ?? '')
  /** Content-editing language only — must never change the app UI locale. */
  const [activeLang, setActiveLang] = useState<'en' | 'ar'>('en')
  const [submitting, setSubmitting] = useState(false)

  const placeholders = PLACEHOLDERS_BY_TYPE[type] ?? PLACEHOLDERS_BY_TYPE.confirmation
  const typeLabel = locale === 'ar' ? TYPE_LABELS[type]?.ar : TYPE_LABELS[type]?.en
  const eventName = locale === 'ar' ? (event.name.ar || event.name.en) : (event.name.en || event.name.ar)

  // Keep document lang/dir locked to the UI locale while content tabs change.
  useEffect(() => {
    document.documentElement.lang = locale
    document.documentElement.dir = direction
  }, [locale, direction, activeLang])

  const handleUploadImage = useCallback(async (file: File): Promise<string> => {
    try {
      const formData = new FormData()
      formData.append('image', file)
      const response = await apiFetch<{ path: string; url: string }>(
        `/api/v1/tenant/events/${event.id}/email-templates/images`,
        {
          method: 'POST',
          tenantId,
          idempotency: true,
          body: formData,
          headers: {},
        },
      )
      return response.url
    } catch (caught) {
      const msg = caught instanceof ApiFetchError ? caught.message : t('requestFailed')
      toast(msg, 'error')
      throw caught
    }
  }, [event.id, t, tenantId, toast])

  const handleSave = async (e: FormEvent) => {
    e.preventDefault()
    setSubmitting(true)

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/email-templates/${type}`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: {
          subject_en: subjectEn,
          subject_ar: subjectAr,
          html_body_en: bodyEn,
          html_body_ar: bodyAr,
        },
      })

      toast(t('emailTemplateSaved'), 'success')
      router.reload({ only: ['template'], preserveUrl: true })
    } catch (caught) {
      const msg = caught instanceof ApiFetchError ? caught.message : t('requestFailed')
      toast(msg, 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async () => {
    if (!confirm(t('emailTemplateDeleteConfirm'))) {
      return
    }

    setSubmitting(true)

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/email-templates/${type}`, {
        method: 'DELETE',
        tenantId,
        idempotency: true,
      })

      toast(t('emailTemplateDeleted'), 'success')
      router.visit(localizedPath(`/tenant/events/${event.id}/email-templates`))
    } catch (caught) {
      const msg = caught instanceof ApiFetchError ? caught.message : t('requestFailed')
      toast(msg, 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const isArabicTab = activeLang === 'ar'
  const subjectValue = isArabicTab ? subjectAr : subjectEn
  const setSubjectValue = isArabicTab ? setSubjectAr : setSubjectEn
  const bodyValue = isArabicTab ? bodyAr : bodyEn
  const setBodyValue = isArabicTab ? setBodyAr : setBodyEn

  return (
    <DashboardLayout title={typeLabel}>
      <PageHeader
        title={typeLabel}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: eventName, href: `/tenant/events/${event.id}` },
          { label: t('emailTemplates'), href: `/tenant/events/${event.id}/email-templates` },
          { label: typeLabel },
        ]}
      />

      <form onSubmit={handleSave} className="space-y-6">
        <div className="flex items-center justify-between gap-3">
          <LocalizedLink
            href={`/tenant/events/${event.id}/email-templates`}
            className="inline-flex items-center gap-1.5 text-sm text-[var(--muted)] transition hover:text-[var(--ink)]"
          >
            <ArrowLeft size={14} className={direction === 'rtl' ? 'rotate-180' : undefined} />
            {t('emailTemplatesBackToList')}
          </LocalizedLink>

          <div className="flex items-center gap-2">
            {template && (
              <button
                type="button"
                onClick={() => void handleDelete()}
                disabled={submitting}
                className="flex items-center gap-1.5 rounded-lg border border-[var(--danger)]/20 bg-[var(--danger-soft)] px-3 py-1.5 text-sm font-medium text-[var(--danger)] transition hover:bg-[var(--danger)]/10 disabled:opacity-50"
              >
                <Trash2 size={14} />
                {t('emailTemplateReset')}
              </button>
            )}
            <SubmitButtonWithLoader
              type="submit"
              label={submitting ? t('emailTemplateSaving') : t('emailTemplateSave')}
              loading={submitting}
              savingLabel={t('emailTemplateSaving')}
            />
          </div>
        </div>

        <div className="space-y-4 rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] p-6">
          <div
            className="inline-flex rounded-lg border border-[var(--border)] bg-[var(--surface)] p-1"
            role="tablist"
            aria-label={t('emailTemplateLanguageTabs')}
          >
            {(locale === 'ar' ? (['ar', 'en'] as const) : (['en', 'ar'] as const)).map((lang) => (
              <button
                key={lang}
                type="button"
                role="tab"
                aria-selected={activeLang === lang}
                onClick={() => setActiveLang(lang)}
                className={`rounded-md px-3 py-1.5 text-sm font-medium transition ${
                  activeLang === lang
                    ? 'bg-[var(--brand)] text-white'
                    : 'text-[var(--muted)] hover:text-[var(--ink)]'
                }`}
              >
                {lang === 'ar' ? t('emailTemplateArabic') : t('emailTemplateEnglish')}
              </button>
            ))}
          </div>

          <div className="space-y-4">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-[var(--ink)]">
                {isArabicTab ? t('emailTemplateSubjectAr') : t('emailTemplateSubjectEn')}
              </label>
              <input
                type="text"
                value={subjectValue}
                onChange={(e) => setSubjectValue(e.target.value)}
                required
                dir={isArabicTab ? 'rtl' : 'ltr'}
                lang={activeLang}
                className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none focus:ring-1 focus:ring-[var(--brand)]/20"
                placeholder={isArabicTab ? 'عنوان البريد بالعربية' : 'Email subject in English'}
              />
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-[var(--ink)]">
                {isArabicTab ? t('emailTemplateBodyAr') : t('emailTemplateBodyEn')}
              </label>
              <SimpleHtmlEditor
                key={activeLang}
                value={bodyValue}
                onChange={setBodyValue}
                availablePlaceholders={placeholders}
                contentLocale={activeLang}
                onUploadImage={handleUploadImage}
              />
            </div>
          </div>
        </div>

        <div className="rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] p-4">
          <p className="text-xs text-[var(--muted)]">
            {t('emailTemplateNote')}
          </p>
        </div>
      </form>
    </DashboardLayout>
  )
}
