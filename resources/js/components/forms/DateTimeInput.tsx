import type { InputHTMLAttributes } from 'react'

type DateTimeInputProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string
  error?: string
}

export default function DateTimeInput({ label, error, id, ...props }: DateTimeInputProps) {
  const inputId = id ?? props.name

  return (
    <label className="grid gap-2 text-sm" htmlFor={inputId}>
      <span>{label}</span>
      <input id={inputId} type="datetime-local" className="control" {...props} />
      {error && <span role="alert" className="text-red-700">{error}</span>}
    </label>
  )
}
