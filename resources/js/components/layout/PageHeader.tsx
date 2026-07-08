import type { ReactNode } from 'react'
import Breadcrumbs from './Breadcrumbs'
import type { BreadcrumbItem } from '@/types/shell'

type PageHeaderProps = {
  title: string
  description?: string
  breadcrumbs?: BreadcrumbItem[]
  actions?: ReactNode
}

export default function PageHeader({ title, description, breadcrumbs = [], actions }: PageHeaderProps) {
  return (
    <header className="mb-6 space-y-3">
      {breadcrumbs.length > 0 && <Breadcrumbs items={breadcrumbs} />}
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
          {description && <p className="mt-1 text-slate-600 dark:text-slate-300">{description}</p>}
        </div>
        {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
      </div>
    </header>
  )
}
