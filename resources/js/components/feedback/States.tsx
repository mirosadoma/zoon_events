import type { ReactNode } from 'react'

type StateProps = { title: string; detail?: string; action?: ReactNode }

export function LoadingState() {
  return <div role="status" aria-label="Loading" className="ta-card h-24 animate-pulse bg-slate-200/70 dark:bg-slate-800" />
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

export function ForbiddenState({ title = 'Forbidden', detail = 'You do not have permission to view this capability.', action }: Partial<StateProps>) {
  return <ErrorState title={title} detail={detail} action={action} />
}

export function ConflictState({ title = 'Conflict', detail = 'The requested change conflicts with the current state.', action }: Partial<StateProps>) {
  return <ErrorState title={title} detail={detail} action={action} />
}

export function QueuedState({ title = 'Queued', detail = 'The operation is being processed safely.', action }: Partial<StateProps>) {
  return (
    <section role="status" className="state-panel border-sky-200 bg-sky-50/70 dark:border-sky-900 dark:bg-sky-950/20">
      <h2 className="text-lg font-semibold text-sky-800 dark:text-sky-200">{title}</h2>
      <p className="mt-2 text-slate-600 dark:text-slate-300">{detail}</p>
      {action && <div className="mt-4">{action}</div>}
    </section>
  )
}
