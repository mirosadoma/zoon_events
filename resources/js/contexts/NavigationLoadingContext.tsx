import { createContext, useContext, useEffect, useState, type PropsWithChildren } from 'react'
import { router } from '@inertiajs/react'

const NavigationLoadingContext = createContext(false)

export function useNavigationLoading() {
  return useContext(NavigationLoadingContext)
}

export function NavigationLoadingProvider({ children }: PropsWithChildren) {
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    const removeStart = router.on('start', () => setLoading(true))
    const removeFinish = router.on('finish', () => setLoading(false))
    const removeCancel = router.on('cancel', () => setLoading(false))
    const removeError = router.on('error', () => setLoading(false))

    return () => {
      removeStart()
      removeFinish()
      removeCancel()
      removeError()
    }
  }, [])

  return (
    <NavigationLoadingContext.Provider value={loading}>
      {children}
    </NavigationLoadingContext.Provider>
  )
}
