import type { TextareaHTMLAttributes } from 'react'

type TextareaInputProps = TextareaHTMLAttributes<HTMLTextAreaElement> & {
  label: string
  error?: string
}

export default function TextareaInput({ label, error, id, ...props }: TextareaInputProps) {
  const inputId = id ?? props.name

  return (
    <label className="grid gap-2 text-sm" htmlFor={inputId}>
      <span>{label}</span>
      <textarea id={inputId} className="control min-h-24" {...props} />
      {error && <span role="alert" className="text-red-700">{error}</span>}
    </label>
  )
}
