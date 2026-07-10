import { useId, type InputHTMLAttributes } from 'react'
import { ValidationError } from './TextInput'

type FileInputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & {
  label: string
  error?: string
  hint?: string
}

export default function FileInput({ label, error, hint, id, required, ...props }: FileInputProps) {
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
      <input
        id={inputId}
        type="file"
        className="control file:me-3 file:rounded-md file:border-0 file:bg-[var(--brand-soft)] file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-[var(--brand)]"
        aria-invalid={error ? 'true' : undefined}
        aria-describedby={[hint ? hintId : null, error ? errorId : null].filter(Boolean).join(' ') || undefined}
        required={required}
        {...props}
      />
      {hint ? <span id={hintId} className="text-xs text-[var(--muted)]">{hint}</span> : null}
      {error ? <ValidationError id={errorId} message={error} /> : null}
    </label>
  )
}
