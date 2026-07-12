import type { ReactNode } from 'react'
import { useLocale } from '@/hooks/useLocale'

type StateProps = { title: string; detail?: string; action?: ReactNode }

export function LoadingState() {
  const { t } = useLocale()

  return <div role="status" aria-label={t('loading')} className="ta-card h-24 animate-pulse bg-slate-200/70 dark:bg-slate-800" />
}

export function EmptyState({ title, detail, action }: StateProps) {
  return (
    <section className="state-panel text-center">
      <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[var(--brand-soft)] text-[var(--brand)]">
        <span aria-hidden>∅</span>
      </div>
      <h2 className="text-lg font-semibold">{title}</h2>
      {detail && <p className="mx-auto mt-2 max-w-xl text-slate-600 dark:text-slate-300">{detail}</p>}
      {action && <div className="mt-4">{action}</div>}
    </section>
  )
}

export function ErrorState({ title, detail, action }: StateProps) {
  return (
    <section role="alert" className="state-panel border-red-300 bg-red-50/70 dark:border-red-800 dark:bg-red-950/20">
      <h2 className="text-lg font-semibold text-red-800 dark:text-red-200">{title}</h2>
      {detail && <p className="mt-2 text-slate-600 dark:text-slate-300">{detail}</p>}
      {action && <div className="mt-4">{action}</div>}
    </section>
  )
}

export function ForbiddenState({ title, detail, action }: Partial<StateProps>) {
  const { t } = useLocale()

  return <ErrorState title={title ?? t('forbiddenTitle')} detail={detail ?? t('forbiddenDetail')} action={action} />
}

export function ConflictState({ title, detail, action }: Partial<StateProps>) {
  const { t } = useLocale()

  return <ErrorState title={title ?? t('conflict')} detail={detail ?? t('conflictDetail')} action={action} />
}

export function QueuedState({ title, detail, action }: Partial<StateProps>) {
  const { t } = useLocale()

  return (
    <section role="status" className="state-panel border-sky-200 bg-sky-50/70 dark:border-sky-900 dark:bg-sky-950/20">
      <h2 className="text-lg font-semibold text-sky-800 dark:text-sky-200">{title ?? t('queued')}</h2>
      <p className="mt-2 text-slate-600 dark:text-slate-300">{detail ?? t('queuedDetail')}</p>
      {action && <div className="mt-4">{action}</div>}
    </section>
  )
}
