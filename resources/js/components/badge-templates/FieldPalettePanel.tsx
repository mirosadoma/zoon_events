interface FieldPalettePanelProps {
  availableFields: string[]
  onAddField: (field: string) => void
}

export function FieldPalettePanel({ availableFields, onAddField }: FieldPalettePanelProps) {
  if (availableFields.length === 0) {
    return <p>All fields added.</p>
  }

  return (
    <div>
      <h3>Field Palette</h3>
      <ul>
        {availableFields.map(field => (
          <li key={field}>
            <button type="button" onClick={() => onAddField(field)}>
              {field}
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}
