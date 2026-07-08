import { useState } from 'react'
import TextareaInput from '@/components/forms/TextareaInput'

type ReasonModalProps = {
  open: boolean
  title: string
  message: string
  reasonLabel?: string
  confirmLabel?: string
  cancelLabel?: string
  onConfirm: (reason: string) => void
  onCancel: () => void
  loading?: boolean
}

export default function ReasonModal({
  open,
  title,
  message,
  reasonLabel = 'Reason',
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  onConfirm,
  onCancel,
  loading = false,
}: ReasonModalProps) {
  const [reason, setReason] = useState('')
  const trimmed = reason.trim()
  const canConfirm = trimmed.length > 0 && !loading

  if (!open) {
    return null
  }

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="reason-title">
      <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900">
        <h2 id="reason-title" className="text-lg font-semibold">{title}</h2>
        <p className="mt-2 text-slate-600 dark:text-slate-300">{message}</p>
        <div className="mt-4">
          <TextareaInput
            label={reasonLabel}
            name="reason"
            value={reason}
            required
            onChange={(event) => setReason(event.target.value)}
          />
        </div>
        <div className="mt-6 flex justify-end gap-3">
          <button type="button" className="button-secondary" onClick={onCancel} disabled={loading}>
            {cancelLabel}
          </button>
          <button
            type="button"
            className="button-primary"
            disabled={!canConfirm}
            onClick={() => onConfirm(trimmed)}
          >
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}
