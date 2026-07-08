import { useState } from 'react'
import type { BadgeTemplate } from '@/types/phase3'
import { BADGE_TEMPLATE_ALLOWED_FIELDS, type BadgeTemplateField } from '@/types/phase3'
import TextInput from '@/components/forms/TextInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'

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
  return Object.entries(layout as Record<string, { x: number; y: number; width: number; height: number }>).map(
    ([field, pos]) => ({ field: field as BadgeTemplateField, ...pos }),
  )
}

export default function BadgeTemplateDesigner({ eventId, tenantId, template, onSaved }: DesignerProps) {
  const [name, setName] = useState(template?.name ?? '')
  const [paperSize, setPaperSize] = useState(template?.paper_size ?? 'A6')
  const [printerType, setPrinterType] = useState(template?.printer_type ?? 'thermal')
  const [entries, setEntries] = useState<LayoutEntry[]>(layoutToEntries(template?.layout))
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState(false)

  const apiHeaders = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Tenant-ID': tenantId,
  }

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
    return Object.fromEntries(
      entries.map(({ field, x, y, width, height }) => [field, { x, y, width, height }]),
    )
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
      const res = await fetch(url, {
        method,
        credentials: 'include',
        headers: apiHeaders,
        body: JSON.stringify({ name, layout: buildLayout(), paper_size: paperSize, printer_type: printerType }),
      })

      if (!res.ok) {
        const data = await res.json()
        setError(data.title ?? 'Save failed')
        return
      }

      const data = await res.json()
      setSuccess(true)
      onSaved?.(data.data as BadgeTemplate)
    } catch {
      setError('Network error. Please try again.')
    } finally {
      setSaving(false)
    }
  }

  async function handleActivationToggle(action: 'activate' | 'deactivate') {
    if (!template) return
    setSaving(true)
    setError(null)

    try {
      const res = await fetch(`/api/v1/tenant/events/${eventId}/badge-templates/${template.id}/${action}`, {
        method: 'POST',
        credentials: 'include',
        headers: apiHeaders,
      })

      if (!res.ok) {
        const data = await res.json()
        setError(data.title ?? `Could not ${action} template`)
        return
      }

      const data = await res.json()
      onSaved?.(data.data as BadgeTemplate)
    } catch {
      setError('Network error. Please try again.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div>
      <h2 className="text-lg font-semibold">{template ? 'Edit badge template' : 'New badge template'}</h2>
      {template && <p>Status: {template.status}</p>}

      <div className="mt-4 grid gap-4 sm:grid-cols-3">
        <TextInput label="Template name" name="name" value={name} onChange={(event) => setName(event.target.value)} required />
        <TextInput label="Paper size" name="paper_size" value={paperSize} onChange={(event) => setPaperSize(event.target.value)} required />
        <TextInput label="Printer type" name="printer_type" value={printerType} onChange={(event) => setPrinterType(event.target.value)} required />
      </div>

      <section className="mt-6">
        <h3 className="font-medium">Available fields</h3>
        <div className="mt-2 flex flex-wrap gap-2">
          {BADGE_TEMPLATE_ALLOWED_FIELDS.filter((field) => !entries.some((entry) => entry.field === field)).map((field) => (
            <button key={field} type="button" className="button-secondary" onClick={() => addField(field)}>
              + {field}
            </button>
          ))}
        </div>
      </section>

      <section className="mt-6">
        <h3 className="font-medium">Layout</h3>
        {entries.length === 0 && <p>No fields added yet.</p>}
        {entries.length > 0 && (
          <table className="mt-2 w-full">
            <thead>
              <tr>
                <th>Field</th>
                <th>X</th>
                <th>Y</th>
                <th>Width</th>
                <th>Height</th>
                <th>Remove</th>
              </tr>
            </thead>
            <tbody>
              {entries.map((entry) => (
                <tr key={entry.field}>
                  <td>{entry.field}</td>
                  {(['x', 'y', 'width', 'height'] as const).map((key) => (
                    <td key={key}>
                      <input
                        type="number"
                        value={entry[key]}
                        onChange={(event) => updateEntry(entry.field, key, Number(event.target.value))}
                        className="w-20"
                      />
                    </td>
                  ))}
                  <td>
                    <button type="button" className="button-secondary" onClick={() => removeField(entry.field)}>
                      Remove
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>

      {error && <p className="mt-4 text-red-600" role="alert">{error}</p>}
      {success && <p className="mt-4 text-green-600" role="status">Saved successfully.</p>}

      <div className="mt-6 flex flex-wrap gap-3">
        <SubmitButtonWithLoader loading={saving} label="Save template" type="button" onClick={() => void handleSave()} />
        {template && template.status !== 'active' && (
          <button type="button" className="button-secondary" onClick={() => void handleActivationToggle('activate')} disabled={saving}>
            Activate
          </button>
        )}
        {template && template.status === 'active' && (
          <button type="button" className="button-secondary" onClick={() => void handleActivationToggle('deactivate')} disabled={saving}>
            Deactivate
          </button>
        )}
      </div>
    </div>
  )
}
