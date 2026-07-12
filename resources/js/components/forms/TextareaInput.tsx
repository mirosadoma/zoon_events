import { clsx } from 'clsx'
import { useId, type TextareaHTMLAttributes } from 'react'
import { controlClassName } from '@/lib/formFieldStyles'
import { ValidationError } from './TextInput'

type TextareaInputProps = TextareaHTMLAttributes<HTMLTextAreaElement> & {
  label: string
  error?: string
  hint?: string
  wrapperClassName?: string
}

export default function TextareaInput({
  label,
  error,
  hint,
  id,
  required,
  className,
  wrapperClassName,
  ...props
}: TextareaInputProps) {
  const generatedId = useId()
  const inputId = id ?? props.name ?? generatedId
  const errorId = `${inputId}-error`
  const hintId = `${inputId}-hint`

  return (
    <label className={clsx('grid gap-2 text-sm', wrapperClassName)} htmlFor={inputId}>
      <span className="font-medium text-[var(--ink)]">
        {label}
        {required ? <span className="ms-1 text-red-600">*</span> : null}
      </span>
      <textarea
        id={inputId}
        className={controlClassName(error, clsx(
          'control min-h-32 resize-y leading-relaxed focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)]/20',
          className,
        ))}
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
