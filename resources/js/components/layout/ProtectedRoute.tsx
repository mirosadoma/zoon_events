import type { PropsWithChildren, ReactNode } from 'react'
import { ForbiddenState } from '@/components/feedback/States'
import PermissionGate from './PermissionGate'

type ProtectedRouteProps = PropsWithChildren<{
  permission: string | string[] | null
  fallback?: ReactNode
}>

export default function ProtectedRoute({ permission, fallback, children }: ProtectedRouteProps) {
  return (
    <PermissionGate
      permission={permission}
      fallback={fallback ?? <ForbiddenState />}
    >
      {children}
    </PermissionGate>
  )
}
