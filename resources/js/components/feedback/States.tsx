type StateProps = { title: string; detail?: string; action?: React.ReactNode }

export function LoadingState() {
  return <div role="status" aria-label="Loading" className="h-24 animate-pulse rounded-xl bg-slate-200 dark:bg-slate-800" />
}

export function EmptyState({ title, detail, action }: StateProps) {
  return (
    <section className="state-panel text-center">
      <h2 className="text-lg font-semibold">{title}</h2>
      {detail && <p className="mt-2 text-slate-600 dark:text-slate-300">{detail}</p>}
      {action && <div className="mt-4">{action}</div>}
    </section>
  )
}

export function ErrorState({ title, detail, action }: StateProps) {
  return (
    <section role="alert" className="state-panel border-red-300 dark:border-red-800">
      <h2 className="text-lg font-semibold text-red-800 dark:text-red-200">{title}</h2>
      {detail && <p className="mt-2 text-slate-600 dark:text-slate-300">{detail}</p>}
      {action && <div className="mt-4">{action}</div>}
    </section>
  )
}

export function ForbiddenState({ title = 'Forbidden', detail = 'You do not have permission to view this capability.' }: Partial<StateProps>) {
  return <ErrorState title={title} detail={detail} />
}

export function ConflictState() {
  return <ErrorState title="Conflict" detail="The requested change conflicts with the current state." />
}

export function QueuedState() {
  return (
    <section role="status" className="state-panel">
      <h2 className="text-lg font-semibold">Queued</h2>
      <p className="mt-2 text-slate-600 dark:text-slate-300">The operation is being processed safely.</p>
    </section>
  )
}
