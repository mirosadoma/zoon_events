import { createContext, useCallback, useContext, useMemo, useState, type PropsWithChildren } from 'react'

export type Toast = {
  id: string
  message: string
  variant?: 'success' | 'error' | 'info'
}

type ToastContextValue = {
  toasts: Toast[]
  push: (message: string, variant?: Toast['variant']) => void
  dismiss: (id: string) => void
}

const ToastContext = createContext<ToastContextValue | null>(null)

export function ToastProvider({ children }: PropsWithChildren) {
  const [toasts, setToasts] = useState<Toast[]>([])

  const dismiss = useCallback((id: string) => {
    setToasts((current) => current.filter((toast) => toast.id !== id))
  }, [])

  const push = useCallback((message: string, variant: Toast['variant'] = 'info') => {
    const id = crypto.randomUUID()
    setToasts((current) => [...current, { id, message, variant }])
    window.setTimeout(() => dismiss(id), 4000)
  }, [dismiss])

  const value = useMemo(() => ({ toasts, push, dismiss }), [toasts, push, dismiss])

  return <ToastContext.Provider value={value}>{children}</ToastContext.Provider>
}

export function useToastContext() {
  const context = useContext(ToastContext)

  if (!context) {
    throw new Error('useToastContext must be used within ToastProvider')
  }

  return context
}

export function useToast() {
  const { push, dismiss } = useToastContext()

  return { toast: push, dismiss }
}
