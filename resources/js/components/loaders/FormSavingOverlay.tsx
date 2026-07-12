import { useEffect } from 'react'
import { createPortal } from 'react-dom'
import BrandedLoader from '@/components/loaders/BrandedLoader'
import { useLocale } from '@/hooks/useLocale'

type FormSavingOverlayProps = {
  active: boolean
  target?: HTMLElement | null
  label?: string
}

export default function FormSavingOverlay({ active, target = null, label }: FormSavingOverlayProps) {
  const { t } = useLocale()

  useEffect(() => {
    if (!target || !active) {
      return
    }

    target.classList.add('form-saving-scope')

    return () => {
      target.classList.remove('form-saving-scope')
    }
  }, [active, target])

  if (!active || !target) {
    return null
  }

  return createPortal(
    <div className="form-saving-overlay" role="status" aria-live="polite" aria-busy="true">
      <BrandedLoader compact label={label ?? t('saving')} />
    </div>,
    target,
  )
}
