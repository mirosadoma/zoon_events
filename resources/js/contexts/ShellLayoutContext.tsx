import { createContext, useCallback, useContext, useMemo, useState, type PropsWithChildren } from 'react'

type ShellLayoutContextValue = {
  sidebarCollapsed: boolean
  mobileSidebarOpen: boolean
  toggleSidebar: () => void
  toggleMobileSidebar: () => void
  closeMobileSidebar: () => void
}

const ShellLayoutContext = createContext<ShellLayoutContextValue | null>(null)

const STORAGE_KEY = 'ta-sidebar-collapsed'

export function ShellLayoutProvider({ children }: PropsWithChildren) {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
    if (typeof window === 'undefined') {
      return false
    }

    return window.localStorage.getItem(STORAGE_KEY) === '1'
  })
  const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false)

  const toggleSidebar = useCallback(() => {
    setSidebarCollapsed((current) => {
      const next = !current
      window.localStorage.setItem(STORAGE_KEY, next ? '1' : '0')

      return next
    })
  }, [])

  const toggleMobileSidebar = useCallback(() => {
    setMobileSidebarOpen((current) => !current)
  }, [])

  const closeMobileSidebar = useCallback(() => {
    setMobileSidebarOpen(false)
  }, [])

  const value = useMemo(
    () => ({
      sidebarCollapsed,
      mobileSidebarOpen,
      toggleSidebar,
      toggleMobileSidebar,
      closeMobileSidebar,
    }),
    [sidebarCollapsed, mobileSidebarOpen, toggleSidebar, toggleMobileSidebar, closeMobileSidebar],
  )

  return <ShellLayoutContext.Provider value={value}>{children}</ShellLayoutContext.Provider>
}

export function useShellLayout() {
  const context = useContext(ShellLayoutContext)

  if (!context) {
    throw new Error('useShellLayout must be used within ShellLayoutProvider')
  }

  return context
}
