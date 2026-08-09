type Props = {
  label: string
  value: string
  onChange: (value: string) => void
  placeholder?: string
}

export default function BuilderColorField({ label, value, onChange, placeholder = '#hex' }: Props) {
  const safeColor = value && /^#[0-9a-fA-F]{6}$/.test(value) ? value : '#ffffff'

  return (
    <div className="builder-color-field space-y-1.5">
      <span className="block text-xs font-medium text-white/60">{label}</span>
      <div className="grid grid-cols-[40px_minmax(0,1fr)_28px] items-center gap-2">
        <input
          type="color"
          value={safeColor}
          onChange={(e) => onChange(e.target.value)}
          className="h-9 w-9 cursor-pointer rounded-md border border-white/15 bg-white/5 p-0.5"
          aria-label={label}
        />
        <input
          type="text"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          className="w-full min-w-0 rounded-md border border-white/10 bg-white/5 px-2.5 py-2 text-xs text-white placeholder:text-white/30"
        />
        <button
          type="button"
          onClick={() => onChange('')}
          className="flex h-9 w-7 items-center justify-center rounded text-sm text-white/35 hover:bg-white/10 hover:text-white"
          title="Clear"
        >
          ×
        </button>
      </div>
    </div>
  )
}
