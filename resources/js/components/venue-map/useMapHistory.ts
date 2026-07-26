import { useCallback, useState } from 'react'
import type { MapZone } from '@/components/venue-map/types'

const MAX_HISTORY = 50

export function useMapHistory(initial: MapZone[]) {
  const [past, setPast] = useState<MapZone[][]>([])
  const [present, setPresent] = useState<MapZone[]>(initial)
  const [future, setFuture] = useState<MapZone[][]>([])

  const commit = useCallback((next: MapZone[] | ((current: MapZone[]) => MapZone[])) => {
    setPresent((current) => {
      const resolved = typeof next === 'function' ? next(current) : next
      setPast((history) => [...history.slice(-(MAX_HISTORY - 1)), current])
      setFuture([])
      return resolved
    })
  }, [])

  const replace = useCallback((next: MapZone[]) => {
    setPresent(next)
    setPast([])
    setFuture([])
  }, [])

  const undo = useCallback(() => {
    setPast((history) => {
      if (history.length === 0) return history
      const previous = history[history.length - 1]
      setPresent((current) => {
        setFuture((nextFuture) => [current, ...nextFuture])
        return previous
      })
      return history.slice(0, -1)
    })
  }, [])

  const redo = useCallback(() => {
    setFuture((history) => {
      if (history.length === 0) return history
      const [next, ...rest] = history
      setPresent((current) => {
        setPast((pastHistory) => [...pastHistory, current])
        return next
      })
      return rest
    })
  }, [])

  return {
    zones: present,
    commit,
    replace,
    undo,
    redo,
    canUndo: past.length > 0,
    canRedo: future.length > 0,
  }
}
