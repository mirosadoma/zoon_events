import type { InputHTMLAttributes } from 'react'

type CheckboxInputProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string
}

export default function CheckboxInput({ label, id, ...props }: CheckboxInputProps) {
  const inputId = id ?? props.name

  return (
    <label className="flex items-center gap-2 text-sm" htmlFor={inputId}>
      <input id={inputId} type="checkbox" {...props} />
      <span>{label}</span>
    </label>
  )
}
