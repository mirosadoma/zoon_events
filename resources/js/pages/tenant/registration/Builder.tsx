import { Link, router } from '@inertiajs/react'
import { FormEvent, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import CheckboxInput from '@/components/forms/CheckboxInput'
import SelectInput from '@/components/forms/SelectInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type RegistrationFieldRow = {
  key: string
  type: string
  label_en: string
  label_ar: string
  required: boolean
}

type Props = {
  event: EventRow
  tenantId: string
  formName: string
  privacyNoticeVersion: string
  termsVersion: string
  fields: RegistrationFieldRow[]
}

const FIELD_TYPES = [
  'text',
  'email',
  'phone',
  'number',
  'date',
  'select',
  'dropdown',
  'multi_select',
  'checkbox',
  'hidden',
  'consent',
] as const

function slugifyKey(label: string): string {
  const base = label
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 40)

  return base.length >= 2 ? base : `field_${Date.now()}`
}

export default function RegistrationBuilder({
  event,
  tenantId,
  formName: initialFormName,
  privacyNoticeVersion: initialPrivacy,
  termsVersion: initialTerms,
  fields: initialFields,
}: Props) {
  const { locale } = useLocale()
  const { toast } = useToast()
  const [formName, setFormName] = useState(initialFormName)
  const [privacyNoticeVersion, setPrivacyNoticeVersion] = useState(initialPrivacy)
  const [termsVersion, setTermsVersion] = useState(initialTerms)
  const [fields, setFields] = useState<RegistrationFieldRow[]>(initialFields)
  const [submitting, setSubmitting] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [newField, setNewField] = useState({
    label_en: '',
    label_ar: '',
    type: 'text',
    required: false,
  })

  const apiHeaders = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Tenant-ID': tenantId,
  }

  function moveField(index: number, direction: -1 | 1) {
    const target = index + direction
    if (target < 0 || target >= fields.length) return
    setFields((current) => {
      const next = [...current]
      const [item] = next.splice(index, 1)
      next.splice(target, 0, item)
      return next
    })
  }

  function removeField(index: number) {
    setFields((current) => current.filter((_, i) => i !== index))
  }

  function toggleRequired(index: number) {
    setFields((current) =>
      current.map((field, i) => (i === index ? { ...field, required: !field.required } : field)),
    )
  }

  function addField() {
    if (!newField.label_en.trim() || !newField.label_ar.trim()) {
      setError(locale === 'ar' ? 'أدخل التسميات بالإنجليزية والعربية.' : 'Enter English and Arabic labels.')
      return
    }

    const key = slugifyKey(newField.label_en)
    if (fields.some((field) => field.key === key)) {
      setError(locale === 'ar' ? 'مفتاح الحقل مستخدم بالفعل.' : 'Field key already exists.')
      return
    }

    setFields((current) => [
      ...current,
      {
        key,
        type: newField.type,
        label_en: newField.label_en.trim(),
        label_ar: newField.label_ar.trim(),
        required: newField.required,
      },
    ])
    setNewField({ label_en: '', label_ar: '', type: 'text', required: false })
    setError(null)
  }

  async function saveDraft() {
    if (fields.length === 0) {
      setError(locale === 'ar' ? 'أضف حقلًا واحدًا على الأقل.' : 'Add at least one field.')
      return
    }

    setSubmitting(true)
    setError(null)

    const payload = {
      name: formName,
      fields: fields.map((field) => {
        const row: Record<string, unknown> = {
          key: field.key,
          type: field.type,
          label_en: field.label_en,
          label_ar: field.label_ar,
          required: field.required,
          visibility: 'public',
        }
        if (['select', 'dropdown', 'multi_select'].includes(field.type)) {
          row.options = [{ value: 'option_1', label_en: 'Option 1', label_ar: 'خيار 1' }]
        }
        return row
      }),
      privacy_notice_version: privacyNoticeVersion,
      terms_version: termsVersion,
    }

    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/registration-form`, {
        method: 'PUT',
        credentials: 'include',
        headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
        body: JSON.stringify(payload),
      })
      const body = await response.json()

      if (!response.ok) {
        setError(body.detail ?? body.code ?? body.title ?? 'save_failed')
        toast(locale === 'ar' ? 'تعذر حفظ النموذج.' : 'Failed to save form.', 'error')
        setSubmitting(false)
        return
      }

      toast(locale === 'ar' ? 'تم حفظ مسودة النموذج.' : 'Form draft saved.', 'success')
      router.reload()
    } catch {
      setError('save_failed')
      toast(locale === 'ar' ? 'تعذر حفظ النموذج.' : 'Failed to save form.', 'error')
      setSubmitting(false)
    }
  }

  async function publishForm() {
    setPublishing(true)
    setError(null)

    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/registration-form/publish`, {
        method: 'POST',
        credentials: 'include',
        headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
      })
      const body = await response.json()

      if (!response.ok) {
        setError(body.detail ?? body.code ?? body.title ?? 'publish_failed')
        toast(locale === 'ar' ? 'تعذر نشر النموذج.' : 'Failed to publish form.', 'error')
        setPublishing(false)
        return
      }

      toast(locale === 'ar' ? 'تم نشر نموذج التسجيل.' : 'Registration form published.', 'success')
      router.reload()
    } catch {
      setError('publish_failed')
      toast(locale === 'ar' ? 'تعذر نشر النموذج.' : 'Failed to publish form.', 'error')
      setPublishing(false)
    }
  }

  function handleSaveSubmit(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    void saveDraft()
  }

  return (
    <DashboardLayout title={locale === 'ar' ? 'نموذج التسجيل' : 'Registration form'}>
      <PageHeader
        title={locale === 'ar' ? 'نموذج التسجيل' : 'Registration form'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'نموذج التسجيل' : 'Registration form' },
        ]}
        actions={
          <Link className="button-secondary" href={`/tenant/events/${event.id}/registration-preview`}>
            {locale === 'ar' ? 'معاينة' : 'Preview'}
          </Link>
        }
      />
      <PageContent>
        <form className="space-y-6" onSubmit={handleSaveSubmit}>
          <section className="state-panel grid gap-4 md:grid-cols-3">
            <TextInput
              label={locale === 'ar' ? 'اسم النموذج' : 'Form name'}
              name="form_name"
              value={formName}
              onChange={(e) => setFormName(e.target.value)}
              required
            />
            <TextInput
              label={locale === 'ar' ? 'إصدار إشعار الخصوصية' : 'Privacy notice version'}
              name="privacy_notice_version"
              value={privacyNoticeVersion}
              onChange={(e) => setPrivacyNoticeVersion(e.target.value)}
              required
            />
            <TextInput
              label={locale === 'ar' ? 'إصدار الشروط' : 'Terms version'}
              name="terms_version"
              value={termsVersion}
              onChange={(e) => setTermsVersion(e.target.value)}
              required
            />
          </section>

          <section className="state-panel space-y-3">
            <h2 className="text-lg font-semibold">{locale === 'ar' ? 'الحقول' : 'Fields'}</h2>
            {fields.length === 0 ? (
              <p className="text-sm text-slate-600">
                {locale === 'ar' ? 'لا توجد حقول بعد. أضف حقلًا أدناه.' : 'No fields yet. Add one below.'}
              </p>
            ) : (
              <ul className="space-y-2">
                {fields.map((field, index) => (
                  <li key={field.key} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <div>
                      <strong>{locale === 'ar' ? field.label_ar : field.label_en}</strong>
                      <p className="text-sm text-slate-600">
                        {field.key} · {field.type}
                        {field.required ? (locale === 'ar' ? ' · مطلوب' : ' · required') : ''}
                      </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                      <button type="button" className="button-secondary" onClick={() => moveField(index, -1)} disabled={index === 0}>
                        ↑
                      </button>
                      <button type="button" className="button-secondary" onClick={() => moveField(index, 1)} disabled={index === fields.length - 1}>
                        ↓
                      </button>
                      <CheckboxInput
                        label={locale === 'ar' ? 'مطلوب' : 'Required'}
                        name={`required_${field.key}`}
                        checked={field.required}
                        onChange={() => toggleRequired(index)}
                      />
                      <button type="button" className="button-secondary" onClick={() => removeField(index)}>
                        {locale === 'ar' ? 'حذف' : 'Delete'}
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section className="state-panel grid gap-4 md:grid-cols-2">
            <h2 className="text-lg font-semibold md:col-span-2">{locale === 'ar' ? 'إضافة حقل' : 'Add field'}</h2>
            <TextInput
              label={locale === 'ar' ? 'التسمية بالإنجليزية' : 'English label'}
              name="new_label_en"
              value={newField.label_en}
              onChange={(e) => setNewField((current) => ({ ...current, label_en: e.target.value }))}
            />
            <TextInput
              label={locale === 'ar' ? 'التسمية بالعربية' : 'Arabic label'}
              name="new_label_ar"
              value={newField.label_ar}
              onChange={(e) => setNewField((current) => ({ ...current, label_ar: e.target.value }))}
            />
            <SelectInput
              label={locale === 'ar' ? 'النوع' : 'Type'}
              name="new_type"
              value={newField.type}
              onChange={(e) => setNewField((current) => ({ ...current, type: e.target.value }))}
              options={FIELD_TYPES.map((type) => ({ value: type, label: type }))}
            />
            <CheckboxInput
              label={locale === 'ar' ? 'مطلوب' : 'Required'}
              name="new_required"
              checked={newField.required}
              onChange={(e) => setNewField((current) => ({ ...current, required: e.target.checked }))}
            />
            <button type="button" className="button-secondary md:col-span-2" onClick={addField}>
              {locale === 'ar' ? 'إضافة الحقل' : 'Add field'}
            </button>
          </section>

          {error && <p className="text-red-600" role="alert">{error}</p>}

          <div className="flex flex-wrap gap-3">
            <SubmitButtonWithLoader loading={submitting} label={locale === 'ar' ? 'حفظ المسودة' : 'Save draft'} />
            <button
              type="button"
              className="button-secondary"
              disabled={publishing || fields.length === 0}
              onClick={() => void publishForm()}
            >
              {publishing ? (locale === 'ar' ? 'جارٍ النشر...' : 'Publishing...') : (locale === 'ar' ? 'نشر النموذج' : 'Publish form')}
            </button>
          </div>
        </form>
      </PageContent>
    </DashboardLayout>
  )
}
