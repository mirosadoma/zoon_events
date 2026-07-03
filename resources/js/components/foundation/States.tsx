type StateProps = { title: string; detail?: string }

export function LoadingState() {
  return <div role="status" aria-label="Loading" className="h-24 animate-pulse rounded-xl bg-slate-200 dark:bg-slate-800" />
}

export function EmptyState({ title, detail }: StateProps) {
  return <section className="state-panel"><h2>{title}</h2>{detail && <p>{detail}</p>}</section>
}

export function ErrorState({ title, detail }: StateProps) {
  return <section role="alert" className="state-panel border-red-300"><h2>{title}</h2>{detail && <p>{detail}</p>}</section>
}

export function ForbiddenState() {
  return <ErrorState title="Forbidden" detail="You do not have permission to view this capability." />
}

export function ConflictState() {
  return <ErrorState title="Conflict" detail="The requested change conflicts with the current state." />
}

export function QueuedState() {
  return <section role="status" className="state-panel"><h2>Queued</h2><p>The operation is being processed safely.</p></section>
}
