import type { SelectHTMLAttributes } from 'react'

type Option = { value: string; label: string }

type SelectInputProps = SelectHTMLAttributes<HTMLSelectElement> & {
  label: string
  options: Option[]
  error?: string
}

export default function SelectInput({ label, options, error, id, ...props }: SelectInputProps) {
  const inputId = id ?? props.name

  return (
    <label className="grid gap-2 text-sm" htmlFor={inputId}>
      <span>{label}</span>
      <select id={inputId} className="control" {...props}>
        {options.map((option) => (
          <option key={option.value} value={option.value}>{option.label}</option>
        ))}
      </select>
      {error && <span role="alert" className="text-red-700">{error}</span>}
    </label>
  )
}
