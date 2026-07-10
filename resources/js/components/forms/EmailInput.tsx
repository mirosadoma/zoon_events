import type { InputHTMLAttributes } from 'react'
import TextInput from './TextInput'

type EmailInputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & {
  label: string
  error?: string
  hint?: string
  wrapperClassName?: string
}

export default function EmailInput(props: EmailInputProps) {
  return <TextInput type="email" autoComplete="email" inputMode="email" {...props} />
}
