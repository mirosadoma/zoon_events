import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useEffect, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import CheckboxInput from '@/components/forms/CheckboxInput'
import SelectInput from '@/components/forms/SelectInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import FieldOptionsRepeater, {
  defaultFieldOptions,
  type FieldOptionRow,
} from '@/components/registration/FieldOptionsRepeater'
import ValidationHintPopover from '@/components/feedback/ValidationHintPopover'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { useFormValidation } from '@/hooks/useFormValidation'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import {
  REGISTRATION_BUILDER_FIELD_LABELS,
  formFieldProps,
  formatRegistrationValidationMessage,
  registrationFieldSelector,
  remapRegistrationApiErrors,
} from '@/lib/formatValidationErrors'
import {
  ADDABLE_FIELD_TYPE_LABELS,
  ADDABLE_REGISTRATION_FIELD_TYPES,
  REGISTRATION_SYSTEM_FIELDS,
  SYSTEM_FIELD_TYPE_LABELS,
  splitRegistrationFields,
} from '@/lib/registrationSystemFields'

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
  system?: boolean
  options?: FieldOptionRow[]
}

type Props = {
  event: EventRow
  tenantId: string
  formName: string
  privacyNoticeVersion: string
  termsVersion: string
  fields: RegistrationFieldRow[]
  hasUnpublishedChanges?: boolean
}

const CHOICE_TYPES = new Set(['select', 'multi_select', 'radio', 'checkbox'])

function isChoiceType(type: string): boolean {
  return CHOICE_TYPES.has(type)
}

function slugifyKey(label: string): string {
  const base = label
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 40)

  return base.length >= 2 ? base : `field_${Date.now()}`
}

function normalizeOptions(options: FieldOptionRow[] | undefined): FieldOptionRow[] {
  if (!options || options.length === 0) {
    return defaultFieldOptions()
  }

  return options.map((option) => ({
    id: option.id,
    label_en: option.label_en,
    label_ar: option.label_ar,
  }))
}

function optionsForSave(options: FieldOptionRow[]): Array<{ value: string; label_en: string; label_ar: string }> {
  return normalizeOptions(options).map((option) => ({
    value: option.id,
    label_en: option.label_en.trim(),
    label_ar: option.label_ar.trim(),
  }))
}

export default function RegistrationBuilder({
  event,
  tenantId,
  formName: initialFormName,
  privacyNoticeVersion: initialPrivacy,
  termsVersion: initialTerms,
  fields: initialFields,
  hasUnpublishedChanges = false,
}: Props) {
  const { locale, t } = useLocale()
  const validation = useFormValidation({
    titleKey: 'couldNotSaveForm',
    fieldLabels: REGISTRATION_BUILDER_FIELD_LABELS,
    remapErrors: (errors) => remapRegistrationApiErrors(errors, REGISTRATION_SYSTEM_FIELDS.length),
    selectorForKey: registrationFieldSelector,
    formatMessage: formatRegistrationValidationMessage,
  })
  const { toast } = useToast()
  const [formName, setFormName] = useState(initialFormName)
  const [privacyNoticeVersion, setPrivacyNoticeVersion] = useState(initialPrivacy)
  const [termsVersion, setTermsVersion] = useState(initialTerms)
  const [customFields, setCustomFields] = useState<RegistrationFieldRow[]>(() =>
    splitRegistrationFields(
      initialFields.map((field) => ({
        ...field,
        options: isChoiceType(field.type) ? normalizeOptions(field.options) : undefined,
      })),
    ).customFields,
  )
  const [submitting, setSubmitting] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [newField, setNewField] = useState({
    label_en: '',
    label_ar: '',
    type: 'text',
    required: false,
    options: defaultFieldOptions(),
  })

  useEffect(() => {
    setFormName(initialFormName)
    setPrivacyNoticeVersion(initialPrivacy)
    setTermsVersion(initialTerms)
    setCustomFields(
      splitRegistrationFields(
        initialFields.map((field) => ({
          ...field,
          options: isChoiceType(field.type) ? normalizeOptions(field.options) : undefined,
        })),
      ).customFields,
    )
    setSubmitting(false)
    setPublishing(false)
    // Sync after Inertia reload; stringify avoids resetting on identical field content.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialFormName, initialPrivacy, initialTerms, JSON.stringify(initialFields)])

  const apiOptions = {
    tenantId,
    idempotency: true,
  } as const

  function moveField(index: number, direction: -1 | 1) {
    const target = index + direction
    if (target < 0 || target >= customFields.length) return
    setCustomFields((current) => {
      const next = [...current]
      const [item] = next.splice(index, 1)
      next.splice(target, 0, item)
      return next
    })
  }

  function removeField(index: number) {
    setCustomFields((current) => current.filter((_, i) => i !== index))
  }

  function toggleRequired(index: number) {
    setCustomFields((current) =>
      current.map((field, i) => (i === index ? { ...field, required: !field.required } : field)),
    )
  }

  function updateFieldOptions(index: number, options: FieldOptionRow[]) {
    setCustomFields((current) =>
      current.map((field, i) => (i === index ? { ...field, options } : field)),
    )
    validation.clearField(`fields.${index}.options`)
  }

  function updateField(index: number, patch: Partial<RegistrationFieldRow>) {
    setCustomFields((current) =>
      current.map((field, i) => (i === index ? { ...field, ...patch } : field)),
    )
    validation.clearField(`fields.${index}`)
  }

  function handleFieldTypeChange(index: number, type: string) {
    setCustomFields((current) =>
      current.map((field, i) => {
        if (i !== index) {
          return field
        }

        return {
          ...field,
          type,
          options: isChoiceType(type) ? normalizeOptions(field.options) : undefined,
        }
      }),
    )
  }

  function addField() {
    if (!newField.label_en.trim() || !newField.label_ar.trim()) {
      setError(locale === 'ar' ? 'أدخل التسميات بالإنجليزية والعربية.' : 'Enter English and Arabic labels.')
      return
    }

    if (isChoiceType(newField.type)) {
      const hasInvalidOption = newField.options.some(
        (option) => !option.label_en.trim() || !option.label_ar.trim(),
      )
      if (hasInvalidOption) {
        setError(locale === 'ar' ? 'أكمل تسميات الخيارات بالإنجليزية والعربية.' : 'Complete all option labels in English and Arabic.')
        return
      }
    }

    const key = slugifyKey(newField.label_en)
    if (customFields.some((field) => field.key === key) || REGISTRATION_SYSTEM_FIELDS.some((field) => field.key === key)) {
      setError(locale === 'ar' ? 'مفتاح الحقل مستخدم بالفعل.' : 'Field key already exists.')
      return
    }

    setCustomFields((current) => [
      ...current,
      {
        key,
        type: newField.type,
        label_en: newField.label_en.trim(),
        label_ar: newField.label_ar.trim(),
        required: newField.required,
        options: isChoiceType(newField.type) ? newField.options : undefined,
      },
    ])
    setNewField({ label_en: '', label_ar: '', type: 'text', required: false, options: defaultFieldOptions() })
    setError(null)
  }

  async function saveAndPublish() {
    const fields = [...REGISTRATION_SYSTEM_FIELDS, ...customFields]

    const hasInvalidField = customFields.some(
      (field) => !field.label_en.trim()
        || !field.label_ar.trim()
        || (isChoiceType(field.type)
          && (field.options ?? []).some((option) => !option.label_en.trim() || !option.label_ar.trim())),
    )
    if (hasInvalidField) {
      setError(locale === 'ar' ? 'أكمل تسميات الحقول والخيارات.' : 'Complete all field and option labels.')
      return
    }

    setSubmitting(true)
    setPublishing(true)
    setError(null)
    validation.clearValidation()

    const payload = {
      name: formName,
      fields: fields.map((field) => {
        const row: Record<string, unknown> = {
          key: field.key,
          type: field.type,
          label_en: field.label_en,
          label_ar: field.label_ar,
          required: field.required,
          visibility: field.type === 'hidden' ? 'internal' : 'public',
        }
        if (field.type === 'hidden') {
          row.default = ''
        }
        if (isChoiceType(field.type)) {
          row.options = optionsForSave(field.options ?? [])
        }
        return row
      }),
      privacy_notice_version: privacyNoticeVersion,
      terms_version: termsVersion,
    }

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/registration-form`, {
        method: 'PUT',
        ...apiOptions,
        body: payload,
      })

      toast(
        locale === 'ar'
          ? 'تم حفظ ونشر النموذج. الحقول ظاهرة الآن في صفحة التسجيل.'
          : 'Form saved and published. Fields are now live on the registration page.',
        'success',
      )
      router.reload({ preserveState: false })
    } catch (caught) {
      if (validation.applyApiError(caught)) {
        setError(null)
      } else {
        const message = caught instanceof ApiFetchError ? caught.message : 'save_failed'
        setError(message)
      }
      toast(locale === 'ar' ? 'تعذر حفظ أو نشر النموذج.' : 'Failed to save or publish form.', 'error')
    } finally {
      setSubmitting(false)
      setPublishing(false)
    }
  }

  function handleSaveSubmit(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    void saveAndPublish()
  }

  function handleNewFieldTypeChange(type: string) {
    setNewField((current) => ({
      ...current,
      type,
      options: isChoiceType(type) ? current.options : defaultFieldOptions(),
    }))
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
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/registration-preview`}>
            {locale === 'ar' ? 'معاينة' : 'Preview'}
          </LocalizedLink>
        }
      />
      <PageContent>
        {hasUnpublishedChanges ? (
          <div className="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100" role="status">
            {locale === 'ar'
              ? 'لديك تغييرات لم تُحفظ بعد. اضغط «حفظ ونشر النموذج» لتظهر في صفحة التسجيل.'
              : 'You have unsaved changes. Click «Save & publish form» to update the registration page.'}
          </div>
        ) : null}
        <form className="relative space-y-6" onSubmit={handleSaveSubmit}>
          <section className="state-panel grid gap-4 md:grid-cols-3">
            <TextInput
              label={locale === 'ar' ? 'اسم النموذج' : 'Form name'}
              name="form_name"
              value={formName}
              onChange={(e) => setFormName(e.target.value)}
              error={validation.fieldError('name')}
              {...formFieldProps('name')}
              required
            />
            <TextInput
              label={locale === 'ar' ? 'إصدار إشعار الخصوصية' : 'Privacy notice version'}
              name="privacy_notice_version"
              value={privacyNoticeVersion}
              onChange={(e) => setPrivacyNoticeVersion(e.target.value)}
              error={validation.fieldError('privacy_notice_version')}
              {...formFieldProps('privacy_notice_version')}
              required
            />
            <TextInput
              label={locale === 'ar' ? 'إصدار الشروط' : 'Terms version'}
              name="terms_version"
              value={termsVersion}
              onChange={(e) => setTermsVersion(e.target.value)}
              error={validation.fieldError('terms_version')}
              {...formFieldProps('terms_version')}
              required
            />
          </section>

          <section className="state-panel space-y-3">
            <div>
              <h2 className="text-lg font-semibold">{locale === 'ar' ? 'الحقول الأساسية' : 'Core fields'}</h2>
              <p className="mt-1 text-sm text-slate-600">
                {locale === 'ar'
                  ? 'الاسم الكامل والبريد الإلكتروني ورقم الجوال ثابتة في كل نموذج تسجيل ولا يمكن تعديلها أو حذفها.'
                  : 'Full name, email, and phone are fixed in every registration form and cannot be edited or removed.'}
              </p>
            </div>
            <ul className="space-y-3">
              {REGISTRATION_SYSTEM_FIELDS.map((field) => (
                <li
                  key={field.key}
                  className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/40"
                >
                  <div>
                    <p className="font-medium text-[var(--ink)]">
                      {locale === 'ar' ? field.label_ar : field.label_en}
                    </p>
                    <p className="text-sm text-slate-600">
                      {locale === 'ar' ? 'مفتاح الحقل' : 'Field key'}: <code>{field.key}</code>
                      {' · '}
                      {locale === 'ar' ? SYSTEM_FIELD_TYPE_LABELS[field.type]?.ar ?? field.type : SYSTEM_FIELD_TYPE_LABELS[field.type]?.en ?? field.type}
                      {' · '}
                      {locale === 'ar' ? 'مطلوب' : 'Required'}
                    </p>
                  </div>
                  <span className="rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                    {locale === 'ar' ? 'ثابت' : 'Fixed'}
                  </span>
                </li>
              ))}
            </ul>
          </section>

          <section className="state-panel space-y-3">
            <h2 className="text-lg font-semibold">{locale === 'ar' ? 'حقول إضافية' : 'Additional fields'}</h2>
            {customFields.length === 0 ? (
              <p className="text-sm text-slate-600">
                {locale === 'ar' ? 'لا توجد حقول إضافية بعد. أضف حقلًا أدناه.' : 'No additional fields yet. Add one below.'}
              </p>
            ) : (
              <ul className="space-y-4">
                {customFields.map((field, index) => (
                  <li key={field.key} className="space-y-4 rounded-lg border border-slate-200 p-4 dark:border-slate-700" style={{ background: '#293954' }}>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                      <p className="text-sm text-slate-600">
                        {locale === 'ar' ? 'مفتاح الحقل' : 'Field key'}: <code>{field.key}</code>
                      </p>
                      <div className="flex flex-wrap items-center gap-2">
                        <button type="button" className="button-secondary" onClick={() => moveField(index, -1)} disabled={index === 0}>
                          ↑
                        </button>
                        <button type="button" className="button-secondary" onClick={() => moveField(index, 1)} disabled={index === customFields.length - 1}>
                          ↓
                        </button>
                        <button type="button" className="button-secondary" onClick={() => removeField(index)}>
                          {locale === 'ar' ? 'حذف' : 'Delete'}
                        </button>
                      </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                      <TextInput
                        label={locale === 'ar' ? 'التسمية بالإنجليزية' : 'English label'}
                        name={`label_en_${field.key}`}
                        value={field.label_en}
                        onChange={(e) => updateField(index, { label_en: e.target.value })}
                        error={validation.fieldError(`fields.${index}.label_en`)}
                        {...formFieldProps(`fields.${index}.label_en`)}
                        required
                      />
                      <TextInput
                        label={locale === 'ar' ? 'التسمية بالعربية' : 'Arabic label'}
                        name={`label_ar_${field.key}`}
                        value={field.label_ar}
                        onChange={(e) => updateField(index, { label_ar: e.target.value })}
                        error={validation.fieldError(`fields.${index}.label_ar`)}
                        {...formFieldProps(`fields.${index}.label_ar`)}
                        required
                      />
                      <SelectInput
                        label={locale === 'ar' ? 'النوع' : 'Type'}
                        name={`type_${field.key}`}
                        value={field.type}
                        onChange={(e) => handleFieldTypeChange(index, e.target.value)}
                        error={validation.fieldError(`fields.${index}.type`)}
                        {...formFieldProps(`fields.${index}.type`)}
                        options={ADDABLE_REGISTRATION_FIELD_TYPES.map((type) => ({
                          value: type,
                          label: locale === 'ar' ? ADDABLE_FIELD_TYPE_LABELS[type].ar : ADDABLE_FIELD_TYPE_LABELS[type].en,
                        }))}
                      />
                      <CheckboxInput
                        label={locale === 'ar' ? 'مطلوب' : 'Required'}
                        name={`required_${field.key}`}
                        checked={field.required}
                        onChange={() => toggleRequired(index)}
                      />
                    </div>

                    {isChoiceType(field.type) ? (
                      <FieldOptionsRepeater
                        options={normalizeOptions(field.options)}
                        onChange={(options) => updateFieldOptions(index, options)}
                        fieldKeyPrefix={`fields.${index}`}
                        fieldError={(suffix) => validation.fieldError(`fields.${index}.${suffix}`)}
                      />
                    ) : null}
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
              onChange={(e) => handleNewFieldTypeChange(e.target.value)}
              options={ADDABLE_REGISTRATION_FIELD_TYPES.map((type) => ({
                value: type,
                label: locale === 'ar' ? ADDABLE_FIELD_TYPE_LABELS[type].ar : ADDABLE_FIELD_TYPE_LABELS[type].en,
              }))}
            />
            <CheckboxInput
              label={locale === 'ar' ? 'مطلوب' : 'Required'}
              name="new_required"
              checked={newField.required}
              onChange={(e) => setNewField((current) => ({ ...current, required: e.target.checked }))}
            />
            {isChoiceType(newField.type) ? (
              <div className="md:col-span-2">
                <FieldOptionsRepeater
                  options={newField.options}
                  onChange={(options) => setNewField((current) => ({ ...current, options }))}
                />
              </div>
            ) : null}
            <button type="button" className="button-secondary md:col-span-2" onClick={addField}>
              {locale === 'ar' ? 'إضافة الحقل' : 'Add field'}
            </button>
          </section>

          {error && <p className="text-red-600" role="alert">{error}</p>}

          <div className="flex flex-wrap gap-3">
            <SubmitButtonWithLoader
              loading={submitting || publishing}
              label={locale === 'ar' ? 'حفظ ونشر النموذج' : 'Save & publish form'}
            />
          </div>
        </form>
        <ValidationHintPopover {...validation.hintProps} />
      </PageContent>
    </DashboardLayout>
  )
}
