import type { ReactNode } from 'react'

type DetailsModalProps = {
  open: boolean
  title: string
  description?: string
  children: ReactNode
  closeLabel?: string
  onClose: () => void
}

export default function DetailsModal({
  open,
  title,
  description,
  children,
  closeLabel = 'Close',
  onClose,
}: DetailsModalProps) {
  if (!open) {
    return null
  }

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="details-modal-title"
    >
      <button type="button" className="absolute inset-0 cursor-default" aria-label={closeLabel} onClick={onClose} />
      <div className="ta-card relative w-full max-w-2xl shadow-xl">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 id="details-modal-title" className="text-lg font-semibold">{title}</h2>
            {description ? <p className="mt-1 text-sm text-[var(--muted)]">{description}</p> : null}
          </div>
          <button type="button" className="button-secondary px-3 py-1.5" onClick={onClose}>
            {closeLabel}
          </button>
        </div>
        <div className="mt-5">{children}</div>
      </div>
    </div>
  )
}
