import type { LucideIcon } from 'lucide-react'
import { clsx } from 'clsx'
import StatusBadge from '@/components/status/StatusBadge'

type StatAccent = 'brand' | 'emerald' | 'sky' | 'violet' | 'amber' | 'rose'

type StatCardProps = {
  label: string
  value: string | number
  icon?: LucideIcon
  delta?: string
  deltaTone?: 'up' | 'down' | 'neutral'
  description?: string
  status?: string
  accent?: StatAccent
  featured?: boolean
  className?: string
}

export default function StatCard({
  label,
  value,
  icon: Icon,
  delta,
  deltaTone = 'neutral',
  description,
  status,
  accent = 'brand',
  featured = false,
  className,
}: StatCardProps) {
  return (
    <article
      className={clsx(
        'ta-card ta-stat-card',
        featured && 'ta-stat-card-featured',
        accent !== 'brand' && `ta-stat-card-${accent}`,
        className,
      )}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <p className="ta-stat-label">{label}</p>
          <p className="ta-stat-value">{value}</p>
          {delta && (
            <p
              className={clsx('ta-stat-delta', {
                'text-emerald-600': deltaTone === 'up',
                'text-red-600': deltaTone === 'down',
                'text-slate-500': deltaTone === 'neutral',
              })}
            >
              {delta}
            </p>
          )}
          {description && <p className="mt-1 text-sm text-slate-500">{description}</p>}
        </div>
        {Icon && (
          <span className="ta-stat-icon" aria-hidden>
            <Icon className="h-5 w-5" />
          </span>
        )}
      </div>
      {status && (
        <div className="mt-3">
          <StatusBadge status={status} />
        </div>
      )}
    </article>
  )
}
