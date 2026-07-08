type SearchInputProps = {
  value: string
  onChange: (value: string) => void
  label?: string
  placeholder?: string
}

export default function SearchInput({ value, onChange, label = 'Search', placeholder }: SearchInputProps) {
  return (
    <label className="grid gap-1 text-sm">
      <span>{label}</span>
      <input
        type="search"
        className="control"
        value={value}
        placeholder={placeholder}
        onChange={(event) => onChange(event.target.value)}
      />
    </label>
  )
}
