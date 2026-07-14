import type { InputHTMLAttributes } from 'react'
import TextInput from './TextInput'

type TimeInputProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string
  error?: string
}

export default function TimeInput({ label, error, id, ...props }: TimeInputProps) {
  return <TextInput id={id} label={label} error={error} type="time" {...props} />
}
