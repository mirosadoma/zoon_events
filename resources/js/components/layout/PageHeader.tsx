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
    <header className="ta-page-header">
      {breadcrumbs.length > 0 ? (
        <div className="ta-page-header__crumbs">
          <Breadcrumbs items={breadcrumbs} />
        </div>
      ) : null}

      <div className="ta-page-header__body">
        <div className="ta-page-header__copy">
          <div className="ta-page-header__accent" aria-hidden="true" />
          <div className="ta-page-header__text">
            <h1 className="ta-page-header__title">{title}</h1>
            {description ? (
              <p className="ta-page-header__description">{description}</p>
            ) : null}
          </div>
        </div>

        {actions ? (
          <div className="ta-page-header__actions">{actions}</div>
        ) : null}
      </div>
    </header>
  )
}
