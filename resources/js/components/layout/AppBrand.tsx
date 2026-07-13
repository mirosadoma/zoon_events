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
        {logoUrl ? (
            <span className="shrink-0" aria-hidden><img src={logoUrl} alt="" className="h-9 w-9 rounded object-contain" /></span>
        ) : (
            <span className={clsx('ta-sidebar-brand-icon shrink-0', iconClassName)} aria-hidden><LayoutDashboard className="h-4 w-4" /></span>
        )}
      {showName ? (
        <span className={clsx('ta-sidebar-brand-name', nameClassName)}>{appName}</span>
      ) : null}
    </span>
  )
}
