import type { InputHTMLAttributes } from 'react'

type TextInputProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string
  error?: string
}

export default function TextInput({ label, error, id, ...props }: TextInputProps) {
  const inputId = id ?? props.name

  return (
    <label className="grid gap-2 text-sm" htmlFor={inputId}>
      <span>{label}</span>
      <input id={inputId} className="control" {...props} />
      {error && <span role="alert" className="text-red-700">{error}</span>}
    </label>
  )
}
