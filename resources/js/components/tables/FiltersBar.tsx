import type { PropsWithChildren } from 'react'

export default function FiltersBar({ children }: PropsWithChildren) {
  return (
    <div className="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
      {children}
    </div>
  )
}
