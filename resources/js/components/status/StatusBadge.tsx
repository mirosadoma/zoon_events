import { clsx } from 'clsx'

const VARIANTS: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100',
  published: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
  cancelled: 'bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100',
  active: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
  inactive: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100',
  pending: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
  not_required: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100',
  gov_verified: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
  face_verified: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
  manually_approved: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
  revoked: 'bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100',
  expired: 'bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100',
  reissued: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
  paid: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
  failed: 'bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100',
  printing: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
  printed: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
  accepted: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
  rejected: 'bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100',
  duplicate: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
  offline: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100',
  healthy: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
  degraded: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
  unknown: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100',
}

type StatusBadgeProps = {
  status: string
  label?: string
}

export default function StatusBadge({ status, label }: StatusBadgeProps) {
  const normalized = status.toLowerCase().replace(/\s+/g, '_')
  const variant = VARIANTS[normalized] ?? VARIANTS.unknown

  return (
    <span className={clsx('inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', variant)}>
      {label ?? status.replace(/_/g, ' ')}
    </span>
  )
}
