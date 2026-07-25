import { useLayoutEffect, useRef, useState, type ReactNode } from 'react'
import ButtonSpinner from '@/components/loaders/ButtonSpinner'
import FormSavingOverlay from '@/components/loaders/FormSavingOverlay'

type ConfirmModalProps = {
  open: boolean
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  onConfirm: () => void
  onCancel: () => void
  loading?: boolean
  loadingLabel?: string
  confirmDisabled?: boolean
  children?: ReactNode
}

export default function ConfirmModal({
  open,
  title,
  message,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  onConfirm,
  onCancel,
  loading = false,
  loadingLabel,
  confirmDisabled = false,
  children,
}: ConfirmModalProps) {
  const cardRef = useRef<HTMLDivElement>(null)
  const [overlayTarget, setOverlayTarget] = useState<HTMLElement | null>(null)

  useLayoutEffect(() => {
    setOverlayTarget(open ? cardRef.current : null)
  }, [open])

  if (!open) {
    return null
  }

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
      <div ref={cardRef} className="ta-card form-saving-scope-root relative w-full max-w-md shadow-xl">
        <FormSavingOverlay active={loading} target={overlayTarget} label={loadingLabel} />
        <h2 id="confirm-title" className="text-lg font-semibold">{title}</h2>
        <p className="mt-2 text-slate-600 dark:text-slate-300">{message}</p>
        {children ? <div className="mt-4">{children}</div> : null}
        <div className="mt-6 flex justify-end gap-3">
          <button type="button" className="button-secondary" onClick={onCancel} disabled={loading}>
            {cancelLabel}
          </button>
          <button
            type="button"
            className="button-primary inline-flex items-center gap-2"
            onClick={onConfirm}
            disabled={loading || confirmDisabled}
            aria-busy={loading}
          >
            {loading ? <ButtonSpinner /> : null}
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}
