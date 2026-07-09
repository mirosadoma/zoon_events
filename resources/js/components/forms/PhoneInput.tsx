import type { InputHTMLAttributes } from 'react'
import TextInput from './TextInput'

type PhoneInputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & {
  label: string
  error?: string
  hint?: string
  wrapperClassName?: string
}

export default function PhoneInput(props: PhoneInputProps) {
  return <TextInput type="tel" autoComplete="tel" inputMode="tel" {...props} />
}
