import type { PropsWithChildren } from 'react'

export default function FiltersBar({ children }: PropsWithChildren) {
  return (
    <div className="ta-card flex flex-wrap items-end gap-3 !p-4">
      {children}
    </div>
  )
}
