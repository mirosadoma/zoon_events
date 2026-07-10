import { useId } from 'react'
import { ValidationError } from './TextInput'

export type RadioOption = {
  value: string
  label: string
  hint?: string
  disabled?: boolean
}

type RadioGroupProps = {
  label: string
  name: string
  value: string
  options: RadioOption[]
  onChange: (value: string) => void
  error?: string
  hint?: string
  required?: boolean
}

export default function RadioGroup({
  label,
  name,
  value,
  options,
  onChange,
  error,
  hint,
  required = false,
}: RadioGroupProps) {
  const groupId = useId()
  const errorId = `${groupId}-error`
  const hintId = `${groupId}-hint`

  return (
    <fieldset
      className="grid gap-2 text-sm"
      aria-describedby={[hint ? hintId : null, error ? errorId : null].filter(Boolean).join(' ') || undefined}
    >
      <legend className="font-medium text-[var(--ink)]">
        {label}
        {required ? <span className="ms-1 text-red-600">*</span> : null}
      </legend>
      {hint ? <p id={hintId} className="text-xs text-[var(--muted)]">{hint}</p> : null}
      <div className="grid gap-2 sm:grid-cols-2">
        {options.map((option) => (
          <label
            key={option.value}
            className="flex cursor-pointer items-start gap-2 rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)] p-3 text-sm hover:border-[var(--brand)]"
          >
            <input
              type="radio"
              name={name}
              value={option.value}
              checked={value === option.value}
              disabled={option.disabled}
              required={required}
              onChange={() => onChange(option.value)}
              className="mt-1 accent-[var(--brand)]"
            />
            <span>
              <span className="block font-medium text-[var(--ink)]">{option.label}</span>
              {option.hint ? <span className="block text-xs text-[var(--muted)]">{option.hint}</span> : null}
            </span>
          </label>
        ))}
      </div>
      {error ? <ValidationError id={errorId} message={error} /> : null}
    </fieldset>
  )
}
