import { useState } from 'react'
import type { BadgeTemplate } from '@/types/phase3'
import { BADGE_TEMPLATE_ALLOWED_FIELDS, type BadgeTemplateField } from '@/types/phase3'

interface DesignerProps {
  eventId: string
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

export default function BadgeTemplateDesigner({ eventId, template, onSaved }: DesignerProps) {
  const [name, setName] = useState(template?.name ?? '')
  const [paperSize, setPaperSize] = useState(template?.paper_size ?? 'A6')
  const [printerType, setPrinterType] = useState(template?.printer_type ?? 'thermal')
  const [entries, setEntries] = useState<LayoutEntry[]>(layoutToEntries(template?.layout))
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState(false)

  function addField(field: BadgeTemplateField) {
    if (entries.some(e => e.field === field)) return
    setEntries(prev => [...prev, { field, x: 0, y: 0, width: 100, height: 30 }])
  }

  function removeField(field: BadgeTemplateField) {
    setEntries(prev => prev.filter(e => e.field !== field))
  }

  function updateEntry(field: BadgeTemplateField, key: keyof Omit<LayoutEntry, 'field'>, value: number) {
    setEntries(prev => prev.map(e => (e.field === field ? { ...e, [key]: value } : e)))
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
        headers: { 'Content-Type': 'application/json' },
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
      <h1>{template ? 'Edit Badge Template' : 'New Badge Template'}</h1>
      {template && <p>Status: {template.status}</p>}

      <label>
        Template Name
        <input value={name} onChange={e => setName(e.target.value)} placeholder="e.g. General Admission" required />
      </label>

      <label>
        Paper Size
        <input value={paperSize} onChange={e => setPaperSize(e.target.value)} placeholder="A6" required />
      </label>

      <label>
        Printer Type
        <input value={printerType} onChange={e => setPrinterType(e.target.value)} placeholder="thermal" required />
      </label>

      <section>
        <h2>Available Fields</h2>
        <div>
          {BADGE_TEMPLATE_ALLOWED_FIELDS.filter(f => !entries.some(e => e.field === f)).map(field => (
            <button key={field} type="button" onClick={() => addField(field)}>
              + {field}
            </button>
          ))}
        </div>
      </section>

      <section>
        <h2>Layout</h2>
        {entries.length === 0 && <p>No fields added yet.</p>}
        <table>
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
            {entries.map(entry => (
              <tr key={entry.field}>
                <td>{entry.field}</td>
                {(['x', 'y', 'width', 'height'] as const).map(key => (
                  <td key={key}>
                    <input
                      type="number"
                      value={entry[key]}
                      onChange={e => updateEntry(entry.field, key, Number(e.target.value))}
                      style={{ width: 70 }}
                    />
                  </td>
                ))}
                <td>
                  <button type="button" onClick={() => removeField(entry.field)}>
                    Remove
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>

      {error && <p role="alert" style={{ color: 'red' }}>{error}</p>}
      {success && <p role="status" style={{ color: 'green' }}>Saved successfully.</p>}

      <button type="button" onClick={handleSave} disabled={saving || !name.trim()}>
        {saving ? 'Saving…' : 'Save Template'}
      </button>

      {template && template.status !== 'active' && (
        <button type="button" onClick={() => handleActivationToggle('activate')} disabled={saving}>
          Activate
        </button>
      )}
      {template && template.status === 'active' && (
        <button type="button" onClick={() => handleActivationToggle('deactivate')} disabled={saving}>
          Deactivate
        </button>
      )}
    </div>
  )
}
