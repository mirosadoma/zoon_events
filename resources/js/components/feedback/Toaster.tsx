import { useToastContext } from '@/hooks/useToast'

export default function Toaster() {
  const { toasts, dismiss } = useToastContext()

  if (toasts.length === 0) {
    return null
  }

  return (
    <div className="fixed bottom-4 end-4 z-50 flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-2" aria-live="polite">
      {toasts.map((toast) => (
        <div
          key={toast.id}
          role="status"
          className={`rounded-xl border px-4 py-3 text-sm shadow-xl backdrop-blur ${
            toast.variant === 'error'
              ? 'border-red-200 bg-red-700 text-white'
              : toast.variant === 'success'
                ? 'border-emerald-200 bg-emerald-700 text-white'
                : 'border-[var(--border)] bg-[var(--surface-elevated)] text-[var(--ink)]'
          }`}
        >
          <div className="flex items-start justify-between gap-3">
            <span>{toast.message}</span>
            <button type="button" className="opacity-70 hover:opacity-100" onClick={() => dismiss(toast.id)}>
              ×
            </button>
          </div>
        </div>
      ))}
    </div>
  )
}
