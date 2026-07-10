import type { BadgeTemplate } from '@/types/phase3'

interface TemplateListPanelProps {
  templates: BadgeTemplate[]
  onSelect: (template: BadgeTemplate) => void
}

export function TemplateListPanel({ templates, onSelect }: TemplateListPanelProps) {
  if (templates.length === 0) {
    return <p>No badge templates yet. Create one to get started.</p>
  }

  return (
    <ul>
      {templates.map(tpl => (
        <li key={tpl.id}>
          <span>{tpl.name}</span>
          <span> ({tpl.status})</span>
          <button type="button" onClick={() => onSelect(tpl)}>
            Edit
          </button>
        </li>
      ))}
    </ul>
  )
}
