import type { PropsWithChildren, ReactNode } from 'react'
import { usePage } from '@inertiajs/react'
import type { PermissionMap } from '@/types/shell'

type PageProps = {
  can?: PermissionMap
}

type PermissionGateProps = PropsWithChildren<{
  permission: string | string[] | null
  fallback?: ReactNode
  requireAll?: boolean
}>

export default function PermissionGate({
  permission,
  fallback = null,
  requireAll = false,
  children,
}: PermissionGateProps) {
  const can = (usePage<PageProps>().props.can ?? {}) as PermissionMap

  if (permission === null) {
    return <>{children}</>
  }

  const keys = Array.isArray(permission) ? permission : [permission]
  const allowed = requireAll
    ? keys.every((key) => can[key] === true)
    : keys.some((key) => can[key] === true)

  return allowed ? <>{children}</> : <>{fallback}</>
}
