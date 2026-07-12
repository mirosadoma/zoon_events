import { clsx } from 'clsx'
import { useId, type InputHTMLAttributes } from 'react'
import { controlClassName } from '@/lib/formFieldStyles'

type TextInputProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string
  error?: string
  hint?: string
  wrapperClassName?: string
}

export default function TextInput({ label, error, hint, id, required, wrapperClassName = '', ...props }: TextInputProps) {
  const generatedId = useId()
  const inputId = id ?? props.name ?? generatedId
  const errorId = `${inputId}-error`
  const hintId = `${inputId}-hint`

  return (
    <label className={`grid gap-2 text-sm ${wrapperClassName}`} htmlFor={inputId}>
      <span className="font-medium text-[var(--ink)]">
        {label}
        {required ? <span className="ms-1 text-red-600">*</span> : null}
      </span>
      <input
        id={inputId}
        className={controlClassName(error, 'control focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)]/20')}
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

export function ValidationError({ id, message }: { id?: string; message: string }) {
  return (
    <span id={id} role="alert" className="text-xs font-medium text-red-700 dark:text-red-300 hidden">
      {message}
    </span>
  )
}
