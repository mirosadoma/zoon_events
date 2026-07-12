import type { InputHTMLAttributes } from 'react'

type CheckboxInputProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string
}

export default function CheckboxInput({ label, id, disabled, ...props }: CheckboxInputProps) {
  const inputId = id ?? props.name

  return (
    <label className={`flex items-center gap-2 text-sm ${disabled ? 'cursor-not-allowed opacity-60' : ''}`} htmlFor={inputId}>
      <input id={inputId} type="checkbox" disabled={disabled} {...props} />
      <span>{label}</span>
    </label>
  )
}
