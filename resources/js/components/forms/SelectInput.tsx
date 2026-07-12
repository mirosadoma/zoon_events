import { useId, type SelectHTMLAttributes } from 'react'
import { controlClassName } from '@/lib/formFieldStyles'
import { ValidationError } from './TextInput'

type Option = { value: string; label: string }

type SelectInputProps = SelectHTMLAttributes<HTMLSelectElement> & {
  label: string
  options: Option[]
  error?: string
  hint?: string
}

export default function SelectInput({ label, options, error, hint, id, required, ...props }: SelectInputProps) {
  const generatedId = useId()
  const inputId = id ?? props.name ?? generatedId
  const errorId = `${inputId}-error`
  const hintId = `${inputId}-hint`

  return (
    <label className="grid gap-2 text-sm" htmlFor={inputId}>
      <span className="font-medium text-[var(--ink)]">
        {label}
        {required ? <span className="ms-1 text-red-600">*</span> : null}
      </span>
      <select
        id={inputId}
        className={controlClassName(error, 'control focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)]/20')}
        aria-invalid={error ? 'true' : undefined}
        aria-describedby={[hint ? hintId : null, error ? errorId : null].filter(Boolean).join(' ') || undefined}
        required={required}
        {...props}
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>{option.label}</option>
        ))}
      </select>
      {hint ? <span id={hintId} className="text-xs text-[var(--muted)]">{hint}</span> : null}
      {error ? <ValidationError id={errorId} message={error} /> : null}
    </label>
  )
}
