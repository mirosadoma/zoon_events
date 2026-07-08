import { useState } from 'react'
import ButtonSpinner from '@/components/loaders/ButtonSpinner'

type SubmitButtonWithLoaderProps = {
  label: string
  loading?: boolean
  disabled?: boolean
  onClick?: () => void
  type?: 'button' | 'submit'
}

export default function SubmitButtonWithLoader({
  label,
  loading = false,
  disabled = false,
  onClick,
  type = 'submit',
}: SubmitButtonWithLoaderProps) {
  const [submitted, setSubmitted] = useState(false)

  const handleClick = () => {
    if (submitted || loading || disabled) {
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
      className="button-primary inline-flex items-center gap-2"
      disabled={disabled || loading || submitted}
      onClick={type === 'button' ? handleClick : undefined}
    >
      {loading && <ButtonSpinner />}
      {label}
    </button>
  )
}
