import { ChevronLeft, ChevronRight, Home } from 'lucide-react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { useLocale } from '@/hooks/useLocale'
import type { BreadcrumbItem } from '@/types/shell'

type BreadcrumbsProps = {
  items: BreadcrumbItem[]
}

export default function Breadcrumbs({ items }: BreadcrumbsProps) {
  const { locale, direction, t } = useLocale()
  const Separator = direction === 'rtl' ? ChevronLeft : ChevronRight

  if (items.length === 0) {
    return null
  }

  return (
    <nav
      aria-label={t('breadcrumbAriaLabel')}
      className="ta-breadcrumb"
    >
      <ol className="ta-breadcrumb__list">
        {items.map((item, index) => {
          const isFirst = index === 0
          const isLast = index === items.length - 1
          const content = (
            <>
              {isFirst ? <Home className="ta-breadcrumb__home" aria-hidden="true" /> : null}
              <span className="ta-breadcrumb__label">{item.label}</span>
            </>
          )

          return (
            <li key={`${item.label}-${index}`} className="ta-breadcrumb__item">
              {index > 0 ? (
                <Separator className="ta-breadcrumb__separator" aria-hidden="true" />
              ) : null}

              {item.href && !isLast ? (
                <LocalizedLink href={item.href} className="ta-breadcrumb__link">
                  {content}
                </LocalizedLink>
              ) : (
                <span
                  className={`ta-breadcrumb__current${isLast ? ' ta-breadcrumb__current--page' : ''}`}
                  aria-current={isLast ? 'page' : undefined}
                >
                  {content}
                </span>
              )}
            </li>
          )
        })}
      </ol>
    </nav>
  )
}
