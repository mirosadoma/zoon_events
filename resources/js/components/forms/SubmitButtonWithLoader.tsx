import { useState } from 'react'
import ButtonSpinner from '@/components/loaders/ButtonSpinner'

type SubmitButtonWithLoaderProps = {
  label: string
  loading?: boolean
  disabled?: boolean
  onClick?: () => void
  type?: 'button' | 'submit'
  variant?: 'primary' | 'secondary' | 'danger'
}

export default function SubmitButtonWithLoader({
  label,
  loading = false,
  disabled = false,
  onClick,
  type = 'submit',
  variant = 'primary',
}: SubmitButtonWithLoaderProps) {
  const [submitted, setSubmitted] = useState(false)
  const busy = loading || submitted
  const className = variant === 'danger'
    ? 'button-danger inline-flex items-center gap-2'
    : variant === 'secondary'
      ? 'button-secondary inline-flex items-center gap-2'
      : 'button-primary inline-flex items-center gap-2'

  const handleClick = () => {
    if (busy || disabled) {
      return
    }

    setSubmitted(true)
    onClick?.()

    window.setTimeout(() => {
      setSubmitted(false)
    }, 1500)
  }

  return (
    <button
      type={type}
      className={className}
      disabled={disabled || busy}
      aria-busy={busy}
      onClick={type === 'button' ? handleClick : undefined}
    >
      {busy && <ButtonSpinner />}
      {label}
    </button>
  )
}
