import { Search } from 'lucide-react'

type SearchInputProps = {
  value: string
  onChange: (value: string) => void
  label?: string
  placeholder?: string
  compact?: boolean
}

export default function SearchInput({
  value,
  onChange,
  label,
  placeholder = 'Search...',
  compact = false,
}: SearchInputProps) {
  if (compact) {
    return (
      <label className="ta-search flex min-h-9 max-w-xs">
        <Search className="h-4 w-4 shrink-0" aria-hidden />
        <input
          type="search"
          className="w-full border-0 bg-transparent text-sm outline-none"
          value={value}
          placeholder={placeholder}
          aria-label={label ?? placeholder}
          onChange={(event) => onChange(event.target.value)}
        />
      </label>
    )
  }

  return (
    <label className="grid gap-1 text-sm">
      {label && <span className="font-medium text-[var(--ink)]">{label}</span>}
      <div className="ta-search">
        <Search className="h-4 w-4 shrink-0" aria-hidden />
        <input
          type="search"
          className="w-full border-0 bg-transparent text-sm outline-none"
          value={value}
          placeholder={placeholder}
          onChange={(event) => onChange(event.target.value)}
        />
      </div>
    </label>
  )
}
