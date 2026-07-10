import { usePage } from '@inertiajs/react'

type PageProps = {
  tenantId?: string | number
  session?: {
    tenant?: {
      id?: string | number
    } | null
  } | null
}

export function useTenantId(explicitTenantId?: string | number | null): string {
  const page = usePage<PageProps>().props
  const resolved = explicitTenantId ?? page.tenantId ?? page.session?.tenant?.id

  return resolved !== undefined && resolved !== null ? String(resolved) : ''
}
