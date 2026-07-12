import { LayoutDashboard } from 'lucide-react'
import { clsx } from 'clsx'
import { useLocale } from '@/hooks/useLocale'
import { useSiteBranding } from '@/hooks/useSiteBranding'

type BrandedLoaderProps = {
  compact?: boolean
  label?: string
  className?: string
}

export default function BrandedLoader({ compact = false, label, className }: BrandedLoaderProps) {
  const { t } = useLocale()
  const { appName, logoUrl } = useSiteBranding()
  const resolvedLabel = label ?? t('loading')

  return (
    <div
      className={clsx(
        'flex flex-col items-center text-center',
        compact ? 'gap-3' : 'gap-4',
        className,
      )}
      role="status"
      aria-live="polite"
      aria-busy="true"
    >
      {logoUrl ? (
        <img
          src={logoUrl}
          alt=""
          className={clsx('rounded-xl object-contain shadow-sm', compact ? 'h-12 w-12' : 'h-16 w-16')}
        />
      ) : (
        <span
          className={clsx(
            'flex items-center justify-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)] shadow-sm',
            compact ? 'h-12 w-12' : 'h-16 w-16',
          )}
          aria-hidden
        >
          <LayoutDashboard className={compact ? 'h-6 w-6' : 'h-8 w-8'} />
        </span>
      )}

      <p className={clsx('font-semibold text-[var(--ink)]', compact ? 'text-sm' : 'text-base')}>
        {appName}
      </p>

      <div className="flex flex-col items-center gap-2">
        <span
          className={clsx(
            'animate-spin rounded-full border-[var(--brand)]/25 border-t-[var(--brand)]',
            compact ? 'size-6 border-2' : 'size-8 border-[3px]',
          )}
          aria-hidden
        />
        <span className={clsx('text-[var(--muted)]', compact ? 'text-xs' : 'text-sm')}>
          {resolvedLabel}
        </span>
      </div>
    </div>
  )
}
