import { usePage } from '@inertiajs/react'

export default function GlobalRouteLoader() {
  const { component } = usePage()
  const loading = component === undefined

  if (!loading) {
    return null
  }

  return (
    <div className="fixed inset-x-0 top-0 z-50 h-1 overflow-hidden bg-[var(--brand-soft)]" role="status" aria-label="Loading page">
      <div className="h-full w-1/3 animate-pulse rounded-e-full bg-[var(--brand)] shadow-[0_0_18px_var(--brand)]" />
    </div>
  )
}
