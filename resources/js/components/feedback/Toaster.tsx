import { useToastContext } from '@/hooks/useToast'

export default function Toaster() {
  const { toasts, dismiss } = useToastContext()

  if (toasts.length === 0) {
    return null
  }

  return (
    <div className="fixed bottom-4 end-4 z-50 flex max-w-sm flex-col gap-2" aria-live="polite">
      {toasts.map((toast) => (
        <div
          key={toast.id}
          role="status"
          className={`rounded-lg px-4 py-3 text-sm text-white shadow-lg ${
            toast.variant === 'error'
              ? 'bg-red-700'
              : toast.variant === 'success'
                ? 'bg-emerald-700'
                : 'bg-slate-800'
          }`}
        >
          <div className="flex items-start justify-between gap-3">
            <span>{toast.message}</span>
            <button type="button" className="text-white/80 hover:text-white" onClick={() => dismiss(toast.id)}>
              ×
            </button>
          </div>
        </div>
      ))}
    </div>
  )
}
