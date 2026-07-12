import { useLayoutEffect, useRef, useState } from 'react'
import ButtonSpinner from '@/components/loaders/ButtonSpinner'
import FormSavingOverlay from '@/components/loaders/FormSavingOverlay'

type SubmitButtonWithLoaderProps = {
  label: string
  loading?: boolean
  disabled?: boolean
  onClick?: () => void
  type?: 'button' | 'submit'
  variant?: 'primary' | 'secondary' | 'danger'
  formOverlay?: boolean
  savingLabel?: string
}

export default function SubmitButtonWithLoader({
  label,
  loading = false,
  disabled = false,
  onClick,
  type = 'submit',
  variant = 'primary',
  formOverlay = true,
  savingLabel,
}: SubmitButtonWithLoaderProps) {
  const [submitted, setSubmitted] = useState(false)
  const buttonRef = useRef<HTMLButtonElement>(null)
  const [formTarget, setFormTarget] = useState<HTMLElement | null>(null)
  const busy = loading || submitted
  const className = variant === 'danger'
    ? 'button-danger inline-flex items-center gap-2'
    : variant === 'secondary'
      ? 'button-secondary inline-flex items-center gap-2'
      : 'button-primary inline-flex items-center gap-2'

  useLayoutEffect(() => {
    const root = buttonRef.current?.closest('form')
      ?? buttonRef.current?.closest('.form-saving-scope-root')

    setFormTarget(root instanceof HTMLElement ? root : null)
  }, [])

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
    <>
      <FormSavingOverlay active={formOverlay && busy} target={formTarget} label={savingLabel} />
      <button
        ref={buttonRef}
        type={type}
        className={className}
        disabled={disabled || busy}
        aria-busy={busy}
        onClick={type === 'button' ? handleClick : undefined}
      >
        {busy && <ButtonSpinner />}
        {label}
      </button>
    </>
  )
}
