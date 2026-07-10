import type { InputHTMLAttributes } from 'react'
import TextInput from './TextInput'

type DateTimeInputProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string
  error?: string
}

export default function DateTimeInput({ label, error, id, ...props }: DateTimeInputProps) {
  return <TextInput id={id} label={label} error={error} type="datetime-local" {...props} />
}
