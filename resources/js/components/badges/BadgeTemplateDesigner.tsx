import { useState } from 'react'
import type { BadgeTemplate } from '@/types/phase3'
import { BADGE_TEMPLATE_ALLOWED_FIELDS, type BadgeTemplateField } from '@/types/phase3'
import TextInput from '@/components/forms/TextInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { useLocale } from '@/hooks/useLocale'

interface DesignerProps {
  eventId: string
  tenantId: string
  template?: BadgeTemplate
  onSaved?: (template: BadgeTemplate) => void
}

interface LayoutEntry {
  field: BadgeTemplateField
  x: number
  y: number
  width: number
  height: number
}

function layoutToEntries(layout: BadgeTemplate['layout'] | undefined): LayoutEntry[] {
  if (!layout) return []

  return Object.entries(layout as Record<string, Partial<{ x: number; y: number; width: number; height: number }>>).map(
    ([field, pos]) => ({
      field: field as BadgeTemplateField,
      x: pos?.x ?? 0,
      y: pos?.y ?? 0,
      width: pos?.width ?? 100,
      height: pos?.height ?? 30,
    }),
  )
}

function statusLabel(status: BadgeTemplate['status'], locale: 'en' | 'ar'): string {
  if (locale === 'ar') {
    return status === 'active' ? 'نشط' : status === 'draft' ? 'مسودة' : 'غير نشط'
  }

  return status
}

export default function BadgeTemplateDesigner({ eventId, tenantId, template, onSaved }: DesignerProps) {
  const { locale } = useLocale()
  const [name, setName] = useState(template?.name ?? '')
  const [paperSize, setPaperSize] = useState(template?.paper_size ?? 'A6')
  const [printerType, setPrinterType] = useState(template?.printer_type ?? 'thermal')
  const [entries, setEntries] = useState<LayoutEntry[]>(layoutToEntries(template?.layout))
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState(false)

  function addField(field: BadgeTemplateField) {
    if (entries.some((entry) => entry.field === field)) return
    setEntries((prev) => [...prev, { field, x: 0, y: 0, width: 100, height: 30 }])
  }

  function removeField(field: BadgeTemplateField) {
    setEntries((prev) => prev.filter((entry) => entry.field !== field))
  }

  function updateEntry(field: BadgeTemplateField, key: keyof Omit<LayoutEntry, 'field'>, value: number) {
    setEntries((prev) => prev.map((entry) => (entry.field === field ? { ...entry, [key]: value } : entry)))
  }

  function buildLayout() {
    const layout = Object.fromEntries(
      entries.map(({ field, x, y, width, height }) => [field, { x, y, width, height }]),
    )

    if (Object.keys(layout).length === 0) {
      return { attendee_name: { x: 20, y: 20, width: 220, height: 40 } }
    }

    return layout
  }

  async function handleSave() {
    setSaving(true)
    setError(null)
    setSuccess(false)

    const url = template
      ? `/api/v1/tenant/events/${eventId}/badge-templates/${template.id}`
      : `/api/v1/tenant/events/${eventId}/badge-templates`

    const method = template ? 'PATCH' : 'POST'

    try {
      const data = await apiFetch<BadgeTemplate>(url, {
        method,
        tenantId,
        idempotency: true,
        body: { name, layout: buildLayout(), paper_size: paperSize, printer_type: printerType },
      })

      setSuccess(true)
      onSaved?.(data)
    } catch (saveError) {
      setError(
        saveError instanceof ApiFetchError
          ? saveError.message
          : locale === 'ar'
            ? 'خطأ في الشبكة. حاول مرة أخرى.'
            : 'Network error. Please try again.',
      )
    } finally {
      setSaving(false)
    }
  }

  async function handleActivationToggle(action: 'activate' | 'deactivate') {
    if (!template) return
    setSaving(true)
    setError(null)

    try {
      const data = await apiFetch<BadgeTemplate>(
        `/api/v1/tenant/events/${eventId}/badge-templates/${template.id}/${action}`,
        {
          method: 'POST',
          tenantId,
          idempotency: true,
        },
      )

      onSaved?.(data)
    } catch (toggleError) {
      setError(
        toggleError instanceof ApiFetchError
          ? toggleError.message
          : locale === 'ar'
            ? 'خطأ في الشبكة. حاول مرة أخرى.'
            : 'Network error. Please try again.',
      )
    } finally {
      setSaving(false)
    }
  }

  const labels = {
    title: template
      ? locale === 'ar' ? 'تعديل قالب الشارة' : 'Edit badge template'
      : locale === 'ar' ? 'قالب شارة جديد' : 'New badge template',
    status: locale === 'ar' ? 'الحالة' : 'Status',
    availableFields: locale === 'ar' ? 'الحقول المتاحة' : 'Available fields',
    layout: locale === 'ar' ? 'التخطيط' : 'Layout',
    field: locale === 'ar' ? 'الحقل' : 'Field',
    width: locale === 'ar' ? 'العرض' : 'Width',
    height: locale === 'ar' ? 'الارتفاع' : 'Height',
    remove: locale === 'ar' ? 'إزالة' : 'Remove',
    emptyLayout: locale === 'ar' ? 'لم تُضف حقول بعد. اختر حقلاً من القائمة أعلاه.' : 'No fields added yet. Pick a field from the list above.',
    saved: locale === 'ar' ? 'تم حفظ القالب بنجاح.' : 'Template saved successfully.',
    save: locale === 'ar' ? 'حفظ القالب' : 'Save template',
    activate: locale === 'ar' ? 'تفعيل' : 'Activate',
    deactivate: locale === 'ar' ? 'إلغاء التفعيل' : 'Deactivate',
  }

  return (
    <div className="ta-card space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3 border-b border-[var(--border)] pb-4">
        <div>
          <h2 className="text-lg font-semibold text-[var(--ink)]">{labels.title}</h2>
          {template && (
            <p className="mt-1 text-sm text-[var(--muted)]">
              {labels.status}: <span className="font-medium text-[var(--ink)]">{statusLabel(template.status, locale)}</span>
            </p>
          )}
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <TextInput label={locale === 'ar' ? 'اسم القالب' : 'Template name'} name="name" value={name} onChange={(event) => setName(event.target.value)} required />
        <TextInput label={locale === 'ar' ? 'حجم الورق' : 'Paper size'} name="paper_size" value={paperSize} onChange={(event) => setPaperSize(event.target.value)} required />
        <TextInput label={locale === 'ar' ? 'نوع الطابعة' : 'Printer type'} name="printer_type" value={printerType} onChange={(event) => setPrinterType(event.target.value)} required />
      </div>

      <section className="space-y-3">
        <h3 className="text-sm font-semibold text-[var(--ink)]">{labels.availableFields}</h3>
        <div className="flex flex-wrap gap-2">
          {BADGE_TEMPLATE_ALLOWED_FIELDS.filter((field) => !entries.some((entry) => entry.field === field)).map((field) => (
            <button key={field} type="button" className="button-secondary" onClick={() => addField(field)}>
              + {field}
            </button>
          ))}
        </div>
      </section>

      <section className="space-y-3">
        <h3 className="text-sm font-semibold text-[var(--ink)]">{labels.layout}</h3>
        {entries.length === 0 ? (
          <p className="rounded-lg border border-dashed border-[var(--border)] px-4 py-8 text-center text-sm text-[var(--muted)]">
            {labels.emptyLayout}
          </p>
        ) : (
          <div className="ta-table-wrap rounded-[var(--radius-card)] border border-[var(--border)]">
            <table className="ta-table">
              <thead>
                <tr>
                  <th scope="col">{labels.field}</th>
                  <th scope="col">X</th>
                  <th scope="col">Y</th>
                  <th scope="col">{labels.width}</th>
                  <th scope="col">{labels.height}</th>
                  <th scope="col" className="text-end">{labels.remove}</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((entry) => (
                  <tr key={entry.field}>
                    <td className="font-medium text-[var(--ink)]">{entry.field}</td>
                    {(['x', 'y', 'width', 'height'] as const).map((key) => (
                      <td key={key}>
                        <input
                          type="number"
                          value={entry[key]}
                          onChange={(event) => updateEntry(entry.field, key, Number(event.target.value))}
                          className="control w-24"
                          aria-label={`${entry.field} ${key}`}
                        />
                      </td>
                    ))}
                    <td className="ta-table-actions">
                      <button type="button" className="ta-table-action" onClick={() => removeField(entry.field)}>
                        {labels.remove}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {error && (
        <p className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300" role="alert">
          {error}
        </p>
      )}
      {success && (
        <p className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300" role="status">
          {labels.saved}
        </p>
      )}

      <div className="flex flex-wrap gap-3 border-t border-[var(--border)] pt-4">
        <SubmitButtonWithLoader loading={saving} label={labels.save} type="button" onClick={() => void handleSave()} />
        {template && template.status !== 'active' && (
          <button type="button" className="button-secondary" onClick={() => void handleActivationToggle('activate')} disabled={saving}>
            {labels.activate}
          </button>
        )}
        {template && template.status === 'active' && (
          <button type="button" className="button-secondary" onClick={() => void handleActivationToggle('deactivate')} disabled={saving}>
            {labels.deactivate}
          </button>
        )}
      </div>
    </div>
  )
}
