import { LayoutDashboard } from 'lucide-react'
import { clsx } from 'clsx'
import { useSiteBranding } from '@/hooks/useSiteBranding'

type AppBrandProps = {
  showName?: boolean
  className?: string
  iconClassName?: string
  nameClassName?: string
}

export default function AppBrand({
  showName = true,
  className,
  iconClassName,
  nameClassName,
}: AppBrandProps) {
  const { appName, logoUrl } = useSiteBranding()

  return (
    <span className={clsx('inline-flex min-w-0 items-center gap-2', className)}>
      <span className={clsx('ta-sidebar-brand-icon shrink-0', iconClassName)} aria-hidden>
        {logoUrl ? (
          <img src={logoUrl} alt="" className="h-5 w-5 rounded object-contain" />
        ) : (
          <LayoutDashboard className="h-5 w-5" />
        )}
      </span>
      {showName ? (
        <span className={clsx('truncate font-semibold', nameClassName)}>{appName}</span>
      ) : null}
    </span>
  )
}
